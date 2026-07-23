<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\RecordManualPayment;
use App\Actions\Payments\ReviewPaymentProof;
use App\Actions\Payments\SubmitPaymentProof;
use App\Actions\Payments\UpdatePayment;
use App\Actions\Payments\UpdatePaymentStatus;
use App\Http\Controllers\Concerns\FormatsPresentationData;
use App\Http\Requests\Payments\RecordManualPaymentRequest;
use App\Http\Requests\Payments\ReviewPaymentProofRequest;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Requests\Payments\SubmitPaymentProofRequest;
use App\Http\Requests\Payments\UpdatePaymentRequest;
use App\Http\Requests\Payments\UpdatePaymentStatusRequest;
use App\Models\Athlete;
use App\Models\InvoiceTemplate;
use App\Models\Payment;
use App\Models\User;
use App\Presenters\PaymentRowPresenter;
use App\Services\PaymentVisibilityService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    use FormatsPresentationData;

    public function __construct(
        private readonly PaymentVisibilityService $paymentVisibility,
        private readonly PaymentRowPresenter $paymentRows,
        private readonly CreatePayment $createPayment,
        private readonly UpdatePayment $updatePayment,
        private readonly RecordManualPayment $recordManualPayment,
        private readonly SubmitPaymentProof $submitPaymentProof,
        private readonly ReviewPaymentProof $reviewPaymentProof,
        private readonly UpdatePaymentStatus $updatePaymentStatus,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $invoiceTemplate = Schema::hasTable('invoice_templates')
            ? InvoiceTemplate::query()->firstOrCreate(
                ['name' => 'default'],
                [
                    'company_name' => 'RF IS',
                    'payment_notes' => 'Please pay using the method agreed with the admin, then upload a clear receipt or transfer screenshot here.',
                ],
            )
            : null;

        $payments = $this->paymentVisibility->visiblePaymentsQuery($request)
            ->with([
                'athlete.user:id,name,phone',
                'billableUser:id,name,phone',
                'payeeUser:id,name,phone',
                'transactions.verifier:id,name',
            ])
            ->latest('payment_date')
            ->get();

        $tuitionMetrics = $this->monthlyTuitionMetrics($payments);

        return Inertia::render('PaymentsPage', [
            'isAdmin' => (bool) $user?->isAdmin(),
            'canSubmitPaymentProof' => (bool) ($user?->isAthlete() || $user?->isParent()),
            'metrics' => [
                ['label' => 'Paid tuition', 'value' => (string) $tuitionMetrics['paid'], 'detail' => $tuitionMetrics['month_label'].' tuition bills fully paid', 'tone' => 'success'],
                ['label' => 'Unpaid tuition', 'value' => (string) $tuitionMetrics['unpaid'], 'detail' => $tuitionMetrics['month_label'].' tuition bills with no approved payment', 'tone' => 'warning'],
                ['label' => 'Partial tuition', 'value' => (string) $tuitionMetrics['partial'], 'detail' => $tuitionMetrics['month_label'].' tuition bills paid partly', 'tone' => 'info'],
                ['label' => 'Previous unpaid', 'value' => (string) $tuitionMetrics['previous_unpaid'], 'detail' => 'Unpaid tuition bills before '.$tuitionMetrics['month_label'], 'tone' => 'danger'],
            ],
            'rows' => $payments->map(fn (Payment $payment) => $this->paymentRows->row($payment))->values(),
            'athletes' => Athlete::query()
                ->with('user:id,name')
                ->get()
                ->map(fn (Athlete $athlete) => ['value' => $athlete->athlete_id, 'label' => $athlete->user?->name ?? 'Unknown athlete'])
                ->sortBy('label')
                ->values(),
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => ['value' => $user->id, 'label' => trim($user->name.' - '.$user->email)])
                ->values(),
            'coaches' => User::query()
                ->whereHas('roleAssignments', fn ($query) => $query->where('role', 'coach'))
                ->orWhere('role', 'coach')
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => ['value' => $user->id, 'label' => trim($user->name.' - '.$user->email)])
                ->values(),
            'invoiceTemplate' => $invoiceTemplate,
            'paymentInstructions' => $invoiceTemplate?->payment_notes
                ?: 'Please pay using the method agreed with the admin, then upload a clear receipt or transfer screenshot here.',
        ]);
    }

    public function submitProof(SubmitPaymentProofRequest $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $payment->loadMissing(['athlete.user', 'billableUser', 'payeeUser']);
        $this->authorize('submitProof', $payment);

        $payment = $this->submitPaymentProof->handle(
            $payment,
            $user,
            $request->file('proof_file'),
            $validated['notes'] ?? null,
        );

        ActivityLogger::log(
            $request,
            'payment.proof.submitted',
            'payment',
            'Submitted payment proof for review',
            $payment,
            ['invoice_number' => $payment->invoice_number, 'remaining_amount' => $payment->remaining_amount],
        );

        return redirect()->route('payments.index');
    }

    public function reviewProof(ReviewPaymentProofRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('reviewProof', $payment);
        $validated = $request->validated();
        $payment = $this->reviewPaymentProof->handle($payment, $request->user(), $validated);

        ActivityLogger::log(
            $request,
            'payment.proof.reviewed',
            'payment',
            'Reviewed payment proof',
            $payment,
            [
                'decision' => $validated['decision'],
                'approved_amount' => $validated['approved_amount'] ?? null,
                'remaining_amount' => $payment->remaining_amount,
            ],
        );

        return redirect()->route('payments.index');
    }

    public function recordPayment(RecordManualPaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('recordPayment', $payment);
        $payment = $this->recordManualPayment->handle($payment, $request->user(), $request->validated());

        ActivityLogger::log(
            $request,
            'payment.transaction.recorded',
            'payment',
            'Recorded a manual payment transaction',
            $payment,
            [
                'amount' => $request->validated('amount'),
                'payment_method' => $request->validated('payment_method'),
                'remaining_amount' => $payment->remaining_amount,
            ],
        );

        return redirect()->route('payments.index');
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $this->authorize('create', Payment::class);
        $payment = $this->createPayment->handle($request->validated());

        ActivityLogger::log(
            $request,
            'payment.created',
            'payment',
            'Created payment record',
            $payment,
            [
                'invoice_number' => $payment->invoice_number,
                'athlete_id' => $payment->athlete_id,
                'total_amount' => $payment->total_amount,
                'due_date' => optional($payment->due_date)->toDateString(),
            ],
        );

        return redirect()->route('payments.index');
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('update', $payment);
        $payment = $this->updatePayment->handle($payment, $request->validated());

        ActivityLogger::log(
            $request,
            'payment.updated',
            'payment',
            'Updated payment record',
            $payment,
            [
                'invoice_number' => $payment->invoice_number,
                'total_amount' => $payment->total_amount,
                'remaining_amount' => $payment->remaining_amount,
                'due_date' => optional($payment->due_date)->toDateString(),
            ],
        );

        return redirect()->route('payments.index');
    }

    public function destroy(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);
        $payment->loadCount('transactions');

        if ($payment->transactions_count > 0 || filled($payment->proof_path) || (float) ($payment->paid_amount ?? 0) > 0) {
            return back()->withErrors([
                'payment' => 'This bill has financial history and cannot be deleted. Keep it for audit purposes or change its status instead.',
            ]);
        }

        ActivityLogger::log(
            $request,
            'payment.deleted',
            'payment',
            'Deleted unused payment record',
            $payment,
            ['invoice_number' => $payment->invoice_number, 'athlete_id' => $payment->athlete_id],
        );

        $payment->delete();

        return redirect()->route('payments.index');
    }

    public function exportInvoice(Request $request, Payment $payment)
    {
        $this->authorize('exportInvoice', $payment);
        $payment->loadMissing(['athlete.user:id,name,email', 'billableUser:id,name,email', 'payeeUser:id,name,email']);

        $template = Schema::hasTable('invoice_templates')
            ? InvoiceTemplate::query()->firstOrCreate(
                ['name' => 'default'],
                ['company_name' => 'RF IS'],
            )
            : (object) [
                'company_name' => 'RF IS',
                'company_address' => null,
                'company_phone' => null,
                'company_email' => null,
                'logo_url' => null,
                'header_text' => null,
                'footer_text' => null,
                'payment_notes' => null,
            ];

        $invoiceData = [
            'invoice_number' => $payment->invoice_number ?: 'INV-'.$payment->payment_id,
            'invoice_date' => optional($payment->payment_date)->format('d M Y') ?? now()->format('d M Y'),
            'due_date' => optional($payment->due_date)->format('d M Y') ?? '-',
            'collection_method' => $payment->collection_method ?? 'TRANSFER',
            'athlete_name' => $this->paymentRows->subject($payment),
            'athlete_email' => $payment->athlete?->user?->email ?? $payment->billableUser?->email ?? $payment->payeeUser?->email ?? '-',
            'payment_type' => Str::headline(strtolower((string) $payment->payment_type)),
            'status' => $payment->status,
            'total_amount' => (float) ($payment->total_amount ?? $payment->amount),
            'paid_amount' => (float) ($payment->paid_amount ?? 0),
            'remaining_amount' => (float) ($payment->remaining_amount ?? 0),
            'notes' => $payment->notes,
        ];

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
                'template' => $template,
                'invoice' => $invoiceData,
            ])->setPaper('a4');

            ActivityLogger::log($request, 'payment.invoice.exported', 'payment', 'Exported invoice PDF', $payment);

            return $pdf->download(strtolower($invoiceData['invoice_number']).'.pdf');
        }

        $html = view('pdf.invoice', [
            'template' => $template,
            'invoice' => $invoiceData,
        ])->render();

        ActivityLogger::log($request, 'payment.invoice.exported.html_fallback', 'payment', 'Exported invoice HTML fallback', $payment);

        return new HttpResponse(
            $html,
            200,
            [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.strtolower($invoiceData['invoice_number']).'.html"',
            ],
        );
    }

    public function updateStatus(UpdatePaymentStatusRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('updateStatus', $payment);
        $validated = $request->validated();
        $payment = $this->updatePaymentStatus->handle($payment, $request->user(), $validated['status']);

        ActivityLogger::log($request, 'payment.status.updated', 'payment', 'Updated payment status manually', $payment, [
            'new_status' => $payment->status,
            'remaining_amount' => $payment->remaining_amount,
        ]);

        return redirect()->route('payments.index');
    }

    private function monthlyTuitionMetrics($payments): array
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
