<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Models\Athlete;
use App\Models\Payment;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentManagementController extends Controller
{
    use FormatsMvpData;

    public function index(): Response
    {
        $payments = Payment::query()
            ->with(['athlete.user:id,name'])
            ->latest('payment_date')
            ->get();

        return Inertia::render('Payments/Index', [
            'metrics' => [
                ['label' => 'Collected this month', 'value' => $this->rupiah((float) $payments->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])->sum('paid_amount')), 'detail' => 'Verified receipts for the current month', 'tone' => 'success'],
                ['label' => 'Outstanding balance', 'value' => $this->rupiah((float) $payments->sum('remaining_amount')), 'detail' => 'Still open across all active invoices', 'tone' => 'warning'],
                ['label' => 'Open payment items', 'value' => (string) $payments->where('remaining_amount', '>', 0)->count(), 'detail' => 'Invoices still waiting for completion', 'tone' => 'info'],
            ],
            'rows' => $payments->map(fn (Payment $payment) => [
                'id' => 'PAY-'.$payment->payment_id,
                'athlete' => $payment->athlete?->user?->name ?? 'Unknown athlete',
                'type' => Str::headline(strtolower((string) $payment->payment_type)),
                'amount' => $this->rupiah((float) ($payment->total_amount ?? $payment->amount)),
                'balance' => $this->rupiah((float) ($payment->remaining_amount ?? 0)),
                'status' => $this->paymentBadge($payment),
            ])->values(),
            'athletes' => Athlete::query()
                ->with('user:id,name')
                ->get()
                ->map(fn (Athlete $athlete) => ['value' => $athlete->athlete_id, 'label' => $athlete->user?->name ?? 'Unknown athlete'])
                ->sortBy('label')
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'athlete_id' => ['required', 'exists:athletes,athlete_id'],
            'payment_type' => ['required', Rule::in(['TUITION', 'UNIFORM', 'LICENSE', 'CHAMPIONSHIP', 'OTHER', 'UNKNOWN'])],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'collection_method' => ['nullable', Rule::in(['CASH', 'TRANSFER', 'OTHER'])],
        ]);

        $remainingAmount = max((float) $validated['total_amount'] - (float) $validated['paid_amount'], 0);

        $notes = trim(collect([
            $validated['collection_method'] ?? null,
            $validated['notes'] ?? null,
        ])->filter()->implode(' | '));

        $payment = Payment::create([
            'athlete_id' => $validated['athlete_id'],
            'payment_type' => $validated['payment_type'],
            'amount' => $validated['total_amount'],
            'reference_id' => null,
            'total_amount' => $validated['total_amount'],
            'paid_amount' => $validated['paid_amount'],
            'remaining_amount' => $remainingAmount,
            'payment_date' => $validated['payment_date'],
            'status' => $remainingAmount === 0.0 ? 'COMPLETED' : 'PENDING',
            'notes' => $notes !== '' ? $notes : null,
        ]);

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
}
