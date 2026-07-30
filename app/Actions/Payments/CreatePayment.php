<?php

namespace App\Actions\Payments;

use App\Actions\Payments\Concerns\NormalizesPaymentInput;
use App\Actions\Payments\Concerns\ValidatesPaymentRecipient;
use App\Models\Payment;
use App\Support\Domain\PaymentStatus;
use Illuminate\Support\Facades\DB;

class CreatePayment
{
    use NormalizesPaymentInput;
    use ValidatesPaymentRecipient;

    public function handle(array $validated): Payment
    {
        $validated = $this->normalizePaymentData($validated);
        $this->validatePaymentRecipient($validated);

        return DB::transaction(function () use ($validated): Payment {
            $totalAmount = (float) $validated['total_amount'];

            return Payment::query()->create([
                'athlete_id' => $validated['athlete_id'],
                'billable_user_id' => $validated['billable_user_id'] ?? null,
                'payee_user_id' => $validated['payee_user_id'] ?? null,
                'bill_kind' => $validated['bill_kind'],
                'payment_type' => $validated['payment_type'],
                'amount' => $totalAmount,
                'reference_id' => null,
                'total_amount' => $totalAmount,
                'paid_amount' => 0,
                'remaining_amount' => $totalAmount,
                'payment_date' => $validated['payment_date'],
                'due_date' => $validated['due_date'],
                'collection_method' => $validated['collection_method'],
                'status' => PaymentStatus::PENDING,
                'proof_status' => PaymentStatus::PROOF_NONE,
                'notes' => $this->notesFrom($validated),
            ]);
        });
    }
}
