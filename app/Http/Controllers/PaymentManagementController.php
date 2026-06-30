<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Actions\Payments\CreatePayment;
use App\Actions\Payments\ReviewPaymentProof;
use App\Actions\Payments\SubmitPaymentProof;
use App\Actions\Payments\UpdatePayment;
use App\Actions\Payments\UpdatePaymentStatus;
use App\Http\Requests\Payments\ReviewPaymentProofRequest;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Requests\Payments\SubmitPaymentProofRequest;
use App\Http\Requests\Payments\UpdatePaymentRequest;
use App\Http\Requests\Payments\UpdatePaymentStatusRequest;
use App\Models\Athlete;
use App\Models\InvoiceTemplate;
use App\Models\Payment;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Services\PaymentVisibilityService;
use App\Presenters\PaymentRowPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PaymentManagementController extends Controller
{
    use FormatsMvpData;

    public function __construct(
        private readonly PaymentVisibilityService $paymentVisibility,
        private readonly PaymentRowPresenter $paymentRows,
        private readonly CreatePayment $createPayment,
        private readonly UpdatePayment $updatePayment,
        private readonly SubmitPaymentProof $submitPaymentProof,
        private readonly ReviewPaymentProof $reviewPaymentProof,
        private readonly UpdatePaymentStatus $updatePaymentStatus,
    ) {
    }

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

        return Inertia::render('PaymentsPage', [
            'isAdmin' => (bool) $user?->isAdmin(),
            'metrics' => [
                ['label' => 'Approved payments', 'value' => $this->rupiah((float) $payments->sum('paid_amount')), 'detail' => 'Receipts approved or marked paid', 'tone' => 'success'],
                ['label' => 'Outstanding balance', 'value' => $this->rupiah((float) $payments->sum('remaining_amount')), 'detail' => 'Still open across all active invoices', 'tone' => 'warning'],
                ['label' => 'Open payment items', 'value' => (string) $payments->where('remaining_amount', '>', 0)->count(), 'detail' => 'Invoices still waiting for completion', 'tone' => 'info'],
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

        if ((float) ($payment->remaining_amount ?? 0) <= 0.0) {
            return back()->withErrors(['proof_file' => 'This bill is already marked as paid.']);
        }

        $payment = $this->submitPaymentProof->handle($payment, $user, $request->file('proof_file'), $validated['notes'] ?? null);

        return redirect()->route('payments.index');
    }

    public function reviewProof(ReviewPaymentProofRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('reviewProof', $payment);
        $validated = $request->validated();

        $payment = $this->reviewPaymentProof->handle($payment, $request->user(), $validated);

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
            ['athlete_id' => $payment->athlete_id, 'remaining_amount' => $payment->remaining_amount],
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
            ['athlete_id' => $payment->athlete_id, 'remaining_amount' => $payment->remaining_amount],
        );

        return redirect()->route('payments.index');
    }

    public function destroy(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('delete', $payment);

        ActivityLogger::log(
            $request,
            'payment.deleted',
            'payment',
            'Deleted payment record',
            $payment,
            ['athlete_id' => $payment->athlete_id],
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
            'invoice_number' => 'INV-'.$payment->payment_id,
            'invoice_date' => optional($payment->payment_date)->format('d M Y') ?? now()->format('d M Y'),
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

            return $pdf->download('invoice_'.$payment->payment_id.'.pdf');
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
                'Content-Disposition' => 'attachment; filename="invoice_'.$payment->payment_id.'.html"',
            ],
        );
    }

    public function updateStatus(UpdatePaymentStatusRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('updateStatus', $payment);
        $validated = $request->validated();
        $payment = $this->updatePaymentStatus->handle($payment, $validated['status']);

        ActivityLogger::log($request, 'payment.status.updated', 'payment', 'Updated payment status manually', $payment, [
            'new_status' => $payment->status,
        ]);

        return redirect()->route('payments.index');
    }

}
