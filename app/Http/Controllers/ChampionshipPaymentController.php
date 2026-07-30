<?php

namespace App\Http\Controllers;

use App\Actions\Payments\RecordManualPayment;
use App\Models\Payment;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChampionshipPaymentController extends Controller
{
    public function __construct(private readonly RecordManualPayment $recordManualPayment) {}

    public function __invoke(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Athletes and parents must upload payment proof from the finance page.');
        abort_unless($payment->payment_type === 'CHAMPIONSHIP', 404);

        $validated = $request->validate([
            'paid_amount' => ['required', 'numeric', 'gt:0'],
            'transaction_date' => ['nullable', 'date', 'before_or_equal:today'],
            'payment_method' => ['nullable', Rule::in(['CASH', 'CARD', 'TRANSFER', 'OTHER'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $payment = $this->recordManualPayment->handle($payment, $request->user(), [
            'amount' => $validated['paid_amount'],
            'transaction_date' => $validated['transaction_date'] ?? now()->toDateString(),
            'payment_method' => $validated['payment_method'] ?? $payment->collection_method ?? 'TRANSFER',
            'notes' => $validated['notes'] ?? 'Championship installment recorded by admin.',
        ]);

        ActivityLogger::log(
            $request,
            'championship.payment.recorded',
            'payment',
            'Recorded championship installment through finance ledger',
            $payment,
            [
                'amount' => $validated['paid_amount'],
                'remaining_amount' => $payment->remaining_amount,
            ],
        );

        return redirect()->route('championships.index')->with('status', 'Pembayaran kejuaraan berhasil dicatat.');
    }
}
