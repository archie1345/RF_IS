<?php

namespace App\Actions\Payments;

use App\Actions\Payments\Concerns\NormalizesPaymentInput;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePayment
{
    use NormalizesPaymentInput;

    public function handle(Payment $payment, array $validated): Payment
    {
        $validated = $this->normalizePaymentData($validated);

        if ($validated['bill_kind'] !== 'PAYROLL' && empty($validated['athlete_id']) && empty($validated['billable_user_id'])) {
            throw ValidationException::withMessages([
                'athlete_id' => 'Choose an athlete or another account for this bill.',
                'billable_user_id' => 'Choose an athlete or another account for this bill.',
            ]);
        }

        return DB::transaction(function () use ($payment, $validated): Payment {
            $notes = $this->notesFrom($validated);
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

            return $payment->refresh();
        });
    }
}
