<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsPresentationData;
use App\Models\Athlete;
use App\Models\InvoiceTemplate;
use App\Models\Payment;
use App\Models\User;
use App\Presenters\PaymentRowPresenter;
use App\Services\PaymentVisibilityService;
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
            ->latest('payment_date')
            ->get();

        if ($role === 'coach') {
            return Inertia::render('CoachPayrollPage', [
                'canSubmitPaymentProof' => false,
                'metrics' => $this->coachPayrollMetrics($payments),
                'rows' => $payments->map(fn (Payment $payment) => $this->paymentRows->row($payment))->values(),
            ]);
        }

        $invoiceTemplate = $this->invoiceTemplate($isAdmin);
        $tuitionMetrics = $this->monthlyTuitionMetrics($payments);

        return Inertia::render('PaymentsPage', [
            'isAdmin' => $isAdmin,
            'canSubmitPaymentProof' => in_array($role, ['athlete', 'parent'], true),
            'metrics' => [
                ['label' => 'Paid tuition', 'value' => (string) $tuitionMetrics['paid'], 'detail' => $tuitionMetrics['month_label'].' tuition bills fully paid', 'tone' => 'success'],
                ['label' => 'Unpaid tuition', 'value' => (string) $tuitionMetrics['unpaid'], 'detail' => $tuitionMetrics['month_label'].' tuition bills with no approved payment', 'tone' => 'warning'],
                ['label' => 'Partial tuition', 'value' => (string) $tuitionMetrics['partial'], 'detail' => $tuitionMetrics['month_label'].' tuition bills paid partly', 'tone' => 'info'],
                ['label' => 'Previous unpaid', 'value' => (string) $tuitionMetrics['previous_unpaid'], 'detail' => 'Unpaid tuition bills before '.$tuitionMetrics['month_label'], 'tone' => 'danger'],
            ],
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
