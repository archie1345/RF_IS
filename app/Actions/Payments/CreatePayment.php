<?php

namespace App\Actions\Payments;

use App\Actions\Payments\Concerns\NormalizesPaymentInput;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePayment
{
    use NormalizesPaymentInput;

    public function handle(array $validated): Payment
    {
        $validated = $this->normalizePaymentData($validated);

        if ($validated['bill_kind'] !== 'PAYROLL' && empty($validated['athlete_id']) && empty($validated['billable_user_id'])) {
            throw ValidationException::withMessages([
                'athlete_id' => 'Choose an athlete or another account for this bill.',
                'billable_user_id' => 'Choose an athlete or another account for this bill.',
            ]);
        }

        return DB::transaction(function () use ($validated): Payment {
            $notes = $this->notesFrom($validated);
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

                return $openInvoice->refresh();
            }

            $totalAmount = (float) $validated['total_amount'];
            $paidAmount = min((float) $validated['paid_amount'], $totalAmount);
            $remainingAmount = max($totalAmount - $paidAmount, 0);

            return Payment::query()->create([
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
        });
    }
}
