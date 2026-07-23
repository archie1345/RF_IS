<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsPresentationData;
use App\Models\Athlete;
use App\Models\InvoiceTemplate;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Presenters\PaymentRowPresenter;
use App\Services\PaymentVisibilityService;
use App\Support\Domain\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class PaymentPageController extends Controller
{
    use FormatsPresentationData;

    public function __construct(
        private readonly PaymentVisibilityService $paymentVisibility,
        private readonly PaymentRowPresenter $paymentRows,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = $user?->primaryRole() ?? 'athlete';
        $isAdmin = $role === 'admin';
        $payments = $this->paymentVisibility
            ->visiblePaymentsQuery($request, $role)
            ->with([
                'athlete.user:id,name,phone',
                'billableUser:id,name,phone',
                'payeeUser:id,name,phone',
                'transactions.verifier:id,name',
            ])
            ->get()
            ->sortBy(fn (Payment $payment): string => $this->queueSortKey($payment))
            ->values();

        if ($role === 'coach') {
            return Inertia::render('CoachPayrollPage', [
                'canSubmitPaymentProof' => false,
                'metrics' => $this->coachPayrollMetrics($payments),
                'rows' => $payments->map(fn (Payment $payment) => $this->paymentRows->row($payment))->values(),
            ]);
        }

        $invoiceTemplate = $this->invoiceTemplate($isAdmin);
        $tuitionMetrics = $this->monthlyTuitionMetrics($payments);
        $financeAttention = $isAdmin ? $this->financeAttention($payments) : null;

        return Inertia::render('PaymentsPage', [
            'isAdmin' => $isAdmin,
            'canSubmitPaymentProof' => in_array($role, ['athlete', 'parent'], true),
            'metrics' => $isAdmin
                ? $this->adminFinanceMetrics($payments, $financeAttention)
                : [
                    ['label' => 'Paid tuition', 'value' => (string) $tuitionMetrics['paid'], 'detail' => $tuitionMetrics['month_label'].' tuition bills fully paid', 'tone' => 'success'],
                    ['label' => 'Unpaid tuition', 'value' => (string) $tuitionMetrics['unpaid'], 'detail' => $tuitionMetrics['month_label'].' tuition bills with no approved payment', 'tone' => 'warning'],
                    ['label' => 'Partial tuition', 'value' => (string) $tuitionMetrics['partial'], 'detail' => $tuitionMetrics['month_label'].' tuition bills paid partly', 'tone' => 'info'],
                    ['label' => 'Previous unpaid', 'value' => (string) $tuitionMetrics['previous_unpaid'], 'detail' => 'Unpaid tuition bills before '.$tuitionMetrics['month_label'], 'tone' => 'danger'],
                ],
            'financeAttention' => $financeAttention,
            'rows' => $payments->map(fn (Payment $payment) => $this->paymentRows->row($payment))->values(),
            'athletes' => $isAdmin ? $this->athleteOptions() : [],
            'users' => $isAdmin ? $this->userOptions() : [],
            'coaches' => $isAdmin ? $this->coachOptions() : [],
            'invoiceTemplate' => $isAdmin ? $invoiceTemplate : null,
            'paymentInstructions' => $invoiceTemplate?->payment_notes
                ?: 'Please pay using the method agreed with the admin, then upload a clear receipt or transfer screenshot here.',
        ]);
    }

    private function invoiceTemplate(bool $createWhenMissing): ?InvoiceTemplate
    {
        if (! Schema::hasTable('invoice_templates')) {
            return null;
        }

        $template = InvoiceTemplate::query()->first();

        if ($template || ! $createWhenMissing) {
            return $template;
        }

        return InvoiceTemplate::query()->create([
            'name' => 'default',
            'company_name' => 'RF IS',
            'payment_notes' => 'Please pay using the method agreed with the admin, then upload a clear receipt or transfer screenshot here.',
        ]);
    }

    private function athleteOptions(): Collection
    {
        return Athlete::query()
            ->with('user:id,name')
            ->get()
            ->map(fn (Athlete $athlete) => [
                'value' => $athlete->athlete_id,
                'label' => $athlete->user?->name ?? 'Unknown athlete',
            ])
            ->sortBy('label')
            ->values();
    }

    private function userOptions(): Collection
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'value' => $user->id,
                'label' => trim($user->name.' - '.$user->email),
            ])
            ->values();
    }

    private function coachOptions(): Collection
    {
        return User::query()
            ->whereHas('roleAssignments', fn ($query) => $query->where('role', 'coach'))
            ->orWhere('role', 'coach')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'value' => $user->id,
                'label' => trim($user->name.' - '.$user->email),
            ])
            ->values();
    }

    private function adminFinanceMetrics(Collection $payments, array $attention): array
    {
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();
        $transactions = $payments->flatMap(
            fn (Payment $payment): Collection => $payment->transactions,
        );
        $receivedThisMonth = (float) $transactions
            ->filter(fn (PaymentTransaction $transaction): bool => $transaction->transaction_type === PaymentTransaction::TYPE_PAYMENT
                && $transaction->transaction_date?->betweenIncluded($monthStart, $monthEnd))
            ->sum('amount');

        $outstandingInvoices = (float) $payments
            ->filter(fn (Payment $payment): bool => ($payment->bill_kind ?? 'INVOICE') === 'INVOICE'
                && $payment->status === PaymentStatus::PENDING)
            ->sum(fn (Payment $payment): float => (float) ($payment->remaining_amount ?? 0));
        $overdueAmount = (float) $payments
            ->filter(fn (Payment $payment): bool => $payment->isOverdue())
            ->sum(fn (Payment $payment): float => (float) ($payment->remaining_amount ?? 0));
        $payrollOutstanding = (float) $payments
            ->filter(fn (Payment $payment): bool => $payment->bill_kind === 'PAYROLL'
                && $payment->status === PaymentStatus::PENDING)
            ->sum(fn (Payment $payment): float => (float) ($payment->remaining_amount ?? 0));

        return [
            ['label' => 'Diterima bulan ini', 'value' => $this->rupiah($receivedThisMonth), 'detail' => 'Total transaksi pembayaran yang disetujui bulan ini', 'tone' => 'success'],
            ['label' => 'Piutang anggota', 'value' => $this->rupiah($outstandingInvoices), 'detail' => 'Sisa seluruh tagihan anggota yang masih aktif', 'tone' => 'warning'],
            ['label' => 'Sudah jatuh tempo', 'value' => $this->rupiah($overdueAmount), 'detail' => $attention['overdue_count'].' tagihan perlu ditindaklanjuti', 'tone' => 'danger'],
            ['label' => 'Bukti menunggu review', 'value' => (string) $attention['proof_review_count'], 'detail' => 'Prioritas pertama dalam antrean keuangan', 'tone' => 'info'],
            ['label' => 'Payroll belum dibayar', 'value' => $this->rupiah($payrollOutstanding), 'detail' => 'Sisa kewajiban pembayaran kepada pelatih', 'tone' => 'warning'],
        ];
    }

    private function financeAttention(Collection $payments): array
    {
        return [
            'proof_review_count' => $payments->where('proof_status', PaymentStatus::PROOF_SUBMITTED)->count(),
            'overdue_count' => $payments->filter(fn (Payment $payment): bool => $payment->isOverdue())->count(),
            'partial_count' => $payments->filter(fn (Payment $payment): bool => (float) ($payment->paid_amount ?? 0) > 0
                && (float) ($payment->remaining_amount ?? 0) > 0)->count(),
            'ledger_mismatch_count' => $payments->filter(function (Payment $payment): bool {
                $paymentsTotal = (float) $payment->transactions
                    ->where('transaction_type', PaymentTransaction::TYPE_PAYMENT)
                    ->sum('amount');
                $refundsTotal = (float) $payment->transactions
                    ->where('transaction_type', 'REFUND')
                    ->sum('amount');

                return abs(max($paymentsTotal - $refundsTotal, 0) - (float) ($payment->paid_amount ?? 0)) >= 0.01;
            })->count(),
        ];
    }

    private function queueSortKey(Payment $payment): string
    {
        $priority = match (true) {
            $payment->proof_status === PaymentStatus::PROOF_SUBMITTED => 0,
            $payment->isOverdue() => 1,
            (float) ($payment->remaining_amount ?? 0) > 0 => 2,
            default => 3,
        };
        $dueDate = optional($payment->due_date)->format('Ymd') ?? '99999999';

        return sprintf('%d-%s-%010d', $priority, $dueDate, $payment->payment_id);
    }

    private function coachPayrollMetrics(Collection $payments): array
    {
        $paidAmount = (float) $payments->sum(fn (Payment $payment) => (float) ($payment->paid_amount ?? 0));
        $remainingAmount = (float) $payments->sum(fn (Payment $payment) => (float) ($payment->remaining_amount ?? 0));
        $latestDate = $payments->first()?->payment_date?->format('d M Y') ?? '-';

        return [
            ['label' => 'Payroll records', 'value' => (string) $payments->count(), 'detail' => 'Payout records assigned to this coach account', 'tone' => 'info'],
            ['label' => 'Paid amount', 'value' => $this->rupiah($paidAmount), 'detail' => 'Total amount recorded as paid', 'tone' => 'success'],
            ['label' => 'Remaining payout', 'value' => $this->rupiah($remainingAmount), 'detail' => 'Outstanding amount across payroll records', 'tone' => 'warning'],
            ['label' => 'Latest record', 'value' => $latestDate, 'detail' => 'Most recent payroll issue date', 'tone' => 'neutral'],
        ];
    }

    private function monthlyTuitionMetrics(Collection $payments): array
    {
        $now = now(config('app.timezone', 'Asia/Jakarta'));
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $monthLabel = $now->format('F Y');

        $tuitionPayments = $payments->filter(fn (Payment $payment): bool => strtoupper((string) ($payment->bill_kind ?? 'INVOICE')) === 'INVOICE'
            && strtoupper((string) $payment->payment_type) === 'TUITION'
        );

        $currentMonthTuition = $tuitionPayments->filter(fn (Payment $payment): bool => $payment->payment_date
            && $payment->payment_date->betweenIncluded($monthStart, $monthEnd)
        );

        return [
            'month_label' => $monthLabel,
            'paid' => $currentMonthTuition
                ->filter(fn (Payment $payment): bool => (float) ($payment->remaining_amount ?? 0) <= 0.0)
                ->count(),
            'unpaid' => $currentMonthTuition
                ->filter(fn (Payment $payment): bool => (float) ($payment->paid_amount ?? 0) <= 0.0 && (float) ($payment->remaining_amount ?? 0) > 0.0)
                ->count(),
            'partial' => $currentMonthTuition
                ->filter(fn (Payment $payment): bool => (float) ($payment->paid_amount ?? 0) > 0.0 && (float) ($payment->remaining_amount ?? 0) > 0.0)
                ->count(),
            'previous_unpaid' => $tuitionPayments
                ->filter(fn (Payment $payment): bool => $payment->payment_date
                    && $payment->payment_date->lt($monthStart)
                    && (float) ($payment->remaining_amount ?? 0) > 0.0
                )
                ->count(),
        ];
    }
}
