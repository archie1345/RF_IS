<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Models\Athlete;
use App\Models\InvoiceTemplate;
use App\Models\Payment;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentManagementController extends Controller
{
    use FormatsMvpData;

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

        $payments = Payment::query()
            ->with(['athlete.user:id,name,phone', 'billableUser:id,name,phone', 'payeeUser:id,name,phone'])
            ->when(! $user?->isAdmin(), fn ($q) => $q->where(function ($query) use ($user): void {
                $childIds = $user?->isParent() ? $user->children()->pluck('athletes.athlete_id')->all() : [];
                $childUserIds = $user?->isParent() ? $user->children()->pluck('athletes.id')->all() : [];
                $athleteId = $user?->athleteProfile?->athlete_id;

                $query
                    ->where('billable_user_id', $user?->id)
                    ->orWhere('payee_user_id', $user?->id)
                    ->when($athleteId, fn ($inner) => $inner->orWhere('athlete_id', $athleteId))
                    ->when(count($childIds) > 0, fn ($inner) => $inner->orWhereIn('athlete_id', $childIds))
                    ->when(count($childUserIds) > 0, fn ($inner) => $inner->orWhereIn('billable_user_id', $childUserIds));
            }))
            ->latest('payment_date')
            ->get();

        return Inertia::render('PaymentsPage', [
            'isAdmin' => (bool) $user?->isAdmin(),
            'metrics' => [
                ['label' => 'Approved payments', 'value' => $this->rupiah((float) $payments->sum('paid_amount')), 'detail' => 'Receipts approved or marked paid', 'tone' => 'success'],
                ['label' => 'Outstanding balance', 'value' => $this->rupiah((float) $payments->sum('remaining_amount')), 'detail' => 'Still open across all active invoices', 'tone' => 'warning'],
                ['label' => 'Open payment items', 'value' => (string) $payments->where('remaining_amount', '>', 0)->count(), 'detail' => 'Invoices still waiting for completion', 'tone' => 'info'],
            ],
            'rows' => $payments->map(fn (Payment $payment) => [
                'id' => 'PAY-'.$payment->payment_id,
                'payment_id' => $payment->payment_id,
                'athlete_id' => $payment->athlete_id,
                'billable_user_id' => $payment->billable_user_id,
                'payee_user_id' => $payment->payee_user_id,
                'bill_kind' => $payment->bill_kind ?? 'INVOICE',
                'athlete' => $this->paymentSubject($payment),
                'athlete_phone' => $payment->athlete?->user?->phone ?? $payment->billableUser?->phone ?? $payment->payeeUser?->phone ?? '',
                'type' => Str::headline(strtolower((string) $payment->payment_type)),
                'payment_type_raw' => $payment->payment_type,
                'amount' => $this->rupiah((float) ($payment->total_amount ?? $payment->amount)),
                'total_amount_raw' => (string) ($payment->total_amount ?? $payment->amount ?? 0),
                'paid_amount_raw' => (string) ($payment->paid_amount ?? 0),
                'remaining_amount_raw' => (string) ($payment->remaining_amount ?? 0),
                'balance' => $this->rupiah((float) ($payment->remaining_amount ?? 0)),
                'payment_date_raw' => optional($payment->payment_date)->format('Y-m-d') ?? '',
                'issued' => optional($payment->payment_date)->format('d M Y') ?? '-',
                'notes_raw' => $payment->notes ?? '',
                'collection_method_raw' => $this->extractCollectionMethod($payment->notes),
                'proof_status' => $payment->proof_status ?? 'NONE',
                'proof_status_label' => $this->proofBadge((string) ($payment->proof_status ?? 'NONE')),
                'proof_url' => $payment->proof_path ? Storage::url($payment->proof_path) : null,
                'proof_notes' => $payment->proof_notes ?? '',
                'status' => $this->paymentBadge($payment),
            ])->values(),
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

    public function submitProof(Request $request, Payment $payment): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,heic,heif', 'max:10240'],
        ]);

        $user = $request->user();
        $payment->loadMissing(['athlete.user', 'billableUser', 'payeeUser']);
        abort_unless($this->userCanSubmitProof($user, $payment), 403);

        if ((float) ($payment->remaining_amount ?? 0) <= 0.0) {
            return back()->withErrors(['proof_file' => 'This bill is already marked as paid.']);
        }

        $path = $request->file('proof_file')->store('payment-proofs', 'public');
        $payment->update([
            'payer_user_id' => $user?->id,
            'proof_path' => $path,
            'proof_status' => 'SUBMITTED',
            'proof_notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('payments.index');
    }

    public function reviewProof(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['APPROVED', 'REJECTED'])],
            'notes' => ['nullable', 'string'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'], // Added to accept partial amounts
        ]);

        if (($payment->proof_status ?? 'NONE') !== 'SUBMITTED') {
            return back()->withErrors(['proof_review' => 'A user must upload payment proof before it can be approved or rejected.']);
        }

        if ($validated['decision'] === 'APPROVED') {
            // Use the amount the admin typed in, or default to the full remaining amount
            $amountToApprove = (float) ($validated['approved_amount'] ?? $payment->remaining_amount);
            
            // Calculate new totals
            $newPaid = min((float) ($payment->paid_amount ?? 0) + $amountToApprove, (float) ($payment->total_amount ?? $payment->amount ?? 0));
            $newRemaining = max((float) ($payment->total_amount ?? $payment->amount ?? 0) - $newPaid, 0);

            // Log this specific partial payment into the Transactions table!
            if ($amountToApprove > 0) {
                \App\Models\Transactions::create([
                    'payment_id' => $payment->payment_id,
                    'verified_by' => $request->user()->id,
                    'amount' => $amountToApprove,
                    'transaction_date' => now(),
                    'transaction_type' => 'CREDIT',
                    'payment_method' => $this->extractCollectionMethod($payment->notes),
                    'notes' => 'Proof approved: ' . ($validated['notes'] ?? ''),
                ]);
            }

            $payment->update([
                'paid_amount' => $newPaid,
                'remaining_amount' => $newRemaining,
                'status' => $newRemaining <= 0 ? 'COMPLETED' : 'PENDING',
                // If fully paid, lock it. If partial, set to 'NONE' so the user can upload the next receipt!
                'proof_status' => $newRemaining <= 0 ? 'APPROVED' : 'NONE',
                // Clear the proof image if partial, so they can upload a new one next time
                'proof_path' => $newRemaining <= 0 ? $payment->proof_path : null, 
                'proof_notes' => $validated['notes'] ?? null,
            ]);
        } else {
            // Rejected
            $payment->update([
                'proof_status' => 'REJECTED',
                'proof_notes' => $validated['notes'] ?? null,
            ]);
        }

        return redirect()->route('payments.index');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'athlete_id' => ['nullable', 'exists:athletes,athlete_id'],
            'payment_type' => ['required', Rule::in(['TUITION', 'UNIFORM', 'LICENSE', 'CHAMPIONSHIP', 'OTHER', 'UNKNOWN'])],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'collection_method' => ['nullable', Rule::in(['CASH', 'TRANSFER', 'OTHER'])],
            'bill_kind' => ['nullable', Rule::in(['INVOICE', 'PAYROLL'])],
            'billable_user_id' => ['nullable', 'exists:users,id'],
            'payee_user_id' => ['nullable', 'exists:users,id'],
        ]);
        $validated['bill_kind'] = $validated['bill_kind'] ?? 'INVOICE';
        $validated['athlete_id'] = $validated['athlete_id'] ?? null;
        $validated['paid_amount'] = $validated['paid_amount'] ?? 0;
        $validated['payment_date'] = $validated['payment_date'] ?? now()->toDateString();

        if ($validated['bill_kind'] === 'PAYROLL') {
            $request->validate(['payee_user_id' => ['required', 'exists:users,id']]);
            $validated['athlete_id'] = null;
            $validated['billable_user_id'] = null;
        } else {
            $validated['payee_user_id'] = null;
            $request->validate(['athlete_id' => ['nullable', 'exists:athletes,athlete_id']]);
            if (empty($validated['athlete_id']) && empty($validated['billable_user_id'])) {
                return back()->withErrors([
                    'athlete_id' => 'Choose an athlete or another account for this bill.',
                    'billable_user_id' => 'Choose an athlete or another account for this bill.',
                ]);
            }
        }

        $notes = trim(collect([
            $validated['collection_method'] ?? null,
            $validated['notes'] ?? null,
        ])->filter()->implode(' | '));

        $openInvoice = Payment::query()
            ->where('athlete_id', $validated['athlete_id'])
            ->where('billable_user_id', $validated['billable_user_id'] ?? null)
            ->where('payee_user_id', $validated['payee_user_id'] ?? null)
            ->where('bill_kind', $validated['bill_kind'])
            ->where('payment_type', $validated['payment_type'])
            ->where('status', 'PENDING')
            ->where('remaining_amount', '>', 0)
            ->orderBy('payment_date')
            ->first();

        if ($openInvoice) {
            $currentTotal = (float) ($openInvoice->total_amount ?? $openInvoice->amount ?? 0);
            $currentPaid = (float) ($openInvoice->paid_amount ?? 0);
            $inputTotal = (float) $validated['total_amount'];
            $additionalPaid = (float) $validated['paid_amount'];

            // Keep current invoice total unless a larger total is intentionally provided.
            $newTotal = max($currentTotal, $inputTotal);
            $newPaid = min($currentPaid + $additionalPaid, $newTotal);
            $remainingAmount = max($newTotal - $newPaid, 0);

            $openInvoice->update([
                'amount' => $newTotal,
                'total_amount' => $newTotal,
                'paid_amount' => $newPaid,
                'remaining_amount' => $remainingAmount,
                'payment_date' => $validated['payment_date'],
                'status' => $remainingAmount === 0.0 ? 'COMPLETED' : 'PENDING',
                'notes' => $this->appendNote($openInvoice->notes, $notes),
            ]);

            $payment = $openInvoice;
        } else {
            $totalAmount = (float) $validated['total_amount'];
            $paidAmount = min((float) $validated['paid_amount'], $totalAmount);
            $remainingAmount = max($totalAmount - $paidAmount, 0);

            $payment = Payment::create([
                'athlete_id' => $validated['athlete_id'],
                'billable_user_id' => $validated['billable_user_id'] ?? null,
                'payee_user_id' => $validated['payee_user_id'] ?? null,
                'bill_kind' => $validated['bill_kind'],
                'payment_type' => $validated['payment_type'],
                'amount' => $totalAmount,
                'reference_id' => null,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'payment_date' => $validated['payment_date'],
                'status' => $remainingAmount === 0.0 ? 'COMPLETED' : 'PENDING',
                'notes' => $notes !== '' ? $notes : null,
            ]);
        }

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

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'athlete_id' => ['nullable', 'exists:athletes,athlete_id'],
            'payment_type' => ['required', Rule::in(['TUITION', 'UNIFORM', 'LICENSE', 'CHAMPIONSHIP', 'OTHER', 'UNKNOWN'])],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'collection_method' => ['nullable', Rule::in(['CASH', 'TRANSFER', 'OTHER'])],
            'bill_kind' => ['nullable', Rule::in(['INVOICE', 'PAYROLL'])],
            'billable_user_id' => ['nullable', 'exists:users,id'],
            'payee_user_id' => ['nullable', 'exists:users,id'],
        ]);
        $validated['bill_kind'] = $validated['bill_kind'] ?? 'INVOICE';
        $validated['athlete_id'] = $validated['athlete_id'] ?? null;
        $validated['paid_amount'] = $validated['paid_amount'] ?? 0;
        $validated['payment_date'] = $validated['payment_date'] ?? now()->toDateString();

        if ($validated['bill_kind'] === 'PAYROLL') {
            $request->validate(['payee_user_id' => ['required', 'exists:users,id']]);
            $validated['athlete_id'] = null;
            $validated['billable_user_id'] = null;
        } else {
            $validated['payee_user_id'] = null;
        }

        if ($validated['bill_kind'] !== 'PAYROLL' && empty($validated['athlete_id']) && empty($validated['billable_user_id'])) {
            return back()->withErrors([
                'athlete_id' => 'Choose an athlete or another account for this bill.',
                'billable_user_id' => 'Choose an athlete or another account for this bill.',
            ]);
        }

        $notes = trim(collect([
            $validated['collection_method'] ?? null,
            $validated['notes'] ?? null,
        ])->filter()->implode(' | '));

        $totalAmount = (float) $validated['total_amount'];
        $paidAmount = min((float) $validated['paid_amount'], $totalAmount);
        $remainingAmount = max($totalAmount - $paidAmount, 0);

        $payment->update([
            'athlete_id' => $validated['athlete_id'],
            'billable_user_id' => $validated['billable_user_id'] ?? null,
            'payee_user_id' => $validated['payee_user_id'] ?? null,
            'bill_kind' => $validated['bill_kind'],
            'payment_type' => $validated['payment_type'],
            'amount' => $totalAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'payment_date' => $validated['payment_date'],
            'status' => $remainingAmount === 0.0 ? 'COMPLETED' : 'PENDING',
            'notes' => $notes !== '' ? $notes : null,
        ]);

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
        abort_unless($request->user()?->isAdmin(), 403);

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

    private function appendNote(?string $existing, string $incoming): ?string
    {
        $existing = trim((string) $existing);
        $incoming = trim($incoming);

        if ($incoming === '') {
            return $existing !== '' ? $existing : null;
        }

        if ($existing === '') {
            return $incoming;
        }

        return $existing.' | '.$incoming;
    }

    public function exportInvoice(Request $request, Payment $payment)
    {
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
            'athlete_name' => $this->paymentSubject($payment),
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

    public function updateStatus(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['PENDING', 'COMPLETED', 'FAILED', 'REFUNDED'])],
        ]);

        $status = $validated['status'];
        $total = (float) ($payment->total_amount ?? $payment->amount ?? 0);
        $paid = (float) ($payment->paid_amount ?? 0);

        if ($status === 'COMPLETED') {
            $paid = $total;
        }

        if ($status === 'PENDING' && $paid >= $total) {
            $paid = max($total - 1, 0);
        }

        if ($status === 'FAILED' || $status === 'REFUNDED') {
            $paid = 0.0;
        }

        $payment->update([
            'status' => $status,
            'paid_amount' => $paid,
            'remaining_amount' => max($total - $paid, 0),
        ]);

        ActivityLogger::log($request, 'payment.status.updated', 'payment', 'Updated payment status manually', $payment, [
            'new_status' => $status,
        ]);

        return redirect()->route('payments.index');
    }

    private function paymentBadge(Payment $payment): array
    {
        if ($payment->status === 'FAILED') {
            return $this->badge('Failed', 'danger');
        }

        if ($payment->status === 'REFUNDED') {
            return $this->badge('Refunded', 'info');
        }

        if ((float) ($payment->remaining_amount ?? 0) === 0.0) {
            return $this->badge('Paid', 'success');
        }

        if ((float) ($payment->paid_amount ?? 0) > 0) {
            return $this->badge('Partial', 'warning');
        }

        return $this->badge('Unpaid', 'danger');
    }

    private function proofBadge(string $proofStatus): array
    {
        return match ($proofStatus) {
            'SUBMITTED' => $this->badge('Waiting review', 'warning'),
            'APPROVED' => $this->badge('Approved', 'success'),
            'REJECTED' => $this->badge('Rejected', 'danger'),
            default => $this->badge('No proof yet', 'neutral'),
        };
    }

    private function userCanSubmitProof(?User $user, Payment $payment): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $directUserIds = collect([
            $payment->billable_user_id,
            $payment->payee_user_id,
            $payment->payer_user_id,
            $payment->athlete?->id,
        ])->filter()->map(fn ($id) => (int) $id)->unique();

        if ($directUserIds->contains((int) $user->id)) {
            return true;
        }

        if ($user->isAthlete()) {
            return (int) $payment->athlete_id === (int) $user->athleteProfile?->athlete_id;
        }

        if ($user->isParent()) {
            $childAthletes = $user->children()
                ->with('user:id')
                ->get(['athletes.athlete_id', 'athletes.id', 'athletes.parent_id'])
                ->map(fn (Athlete $athlete) => [
                    'athlete_id' => (int) $athlete->athlete_id,
                    'user_id' => (int) $athlete->id,
                ]);

            return $childAthletes->contains('athlete_id', (int) $payment->athlete_id)
                || $childAthletes->contains('user_id', (int) $payment->billable_user_id);
        }

        return false;
    }

    private function extractCollectionMethod(?string $notes): string
    {
        $first = trim(explode('|', (string) $notes)[0] ?? '');
        return in_array($first, ['CASH', 'TRANSFER', 'OTHER'], true) ? $first : 'CASH';
    }

    private function paymentSubject(Payment $payment): string
    {
        if (($payment->bill_kind ?? 'INVOICE') === 'PAYROLL') {
            return 'Payroll: '.($payment->payeeUser?->name ?? 'Unknown coach');
        }

        return $payment->athlete?->user?->name
            ?? $payment->billableUser?->name
            ?? 'Unknown user';
    }
}

