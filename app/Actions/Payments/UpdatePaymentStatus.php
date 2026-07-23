<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\Domain\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePaymentStatus
{
    public function handle(Payment $payment, User $actor, string $status): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $status): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->payment_id);
            $total = (float) ($lockedPayment->total_amount ?? $lockedPayment->amount ?? 0);
            $paid = (float) ($lockedPayment->paid_amount ?? 0);
            $remaining = (float) ($lockedPayment->remaining_amount ?? max($total - $paid, 0));
            $previousStatus = $lockedPayment->status ?? PaymentStatus::PENDING;

            if ($status === PaymentStatus::COMPLETED && $remaining > 0) {
                throw ValidationException::withMessages([
                    'status' => 'A bill can only be completed after its balance reaches zero through recorded transactions.',
                ]);
            }

            if ($status === PaymentStatus::PENDING && $remaining <= 0) {
                throw ValidationException::withMessages([
                    'status' => 'A fully paid bill cannot be reopened without recording a refund first.',
                ]);
            }

            if ($status === PaymentStatus::FAILED && $paid > 0) {
                throw ValidationException::withMessages([
                    'status' => 'A bill with recorded payments cannot be marked failed. Refund it or correct the transactions first.',
                ]);
            }

            if ($status === PaymentStatus::REFUNDED) {
                if ($paid <= 0) {
                    throw ValidationException::withMessages([
                        'status' => 'Only a bill with recorded payments can be refunded.',
                    ]);
                }

                PaymentTransaction::query()->create([
                    'payment_id' => $lockedPayment->payment_id,
                    'verified_by' => $actor->id,
                    'amount' => $paid,
                    'transaction_date' => now(),
                    'transaction_type' => 'REFUND',
                    'payment_method' => $lockedPayment->collection_method ?? 'OTHER',
                    'notes' => 'Full refund recorded by admin. Previous paid amount: '.$paid.'.',
                    'proof_path' => $lockedPayment->proof_path,
                    'proof_notes' => $lockedPayment->proof_notes,
                ]);

                $paid = 0;
                $remaining = $total;
            }

            PaymentTransaction::query()->create([
                'payment_id' => $lockedPayment->payment_id,
                'verified_by' => $actor->id,
                'amount' => 0,
                'transaction_date' => now(),
                'transaction_type' => PaymentTransaction::TYPE_STATUS_CHANGE,
                'payment_method' => 'MANUAL_STATUS',
                'notes' => "Payment status changed from {$previousStatus} to {$status}. Paid: {$paid}. Remaining: {$remaining}.",
                'proof_path' => $lockedPayment->proof_path,
                'proof_notes' => $lockedPayment->proof_notes,
            ]);

            $lockedPayment->update([
                'status' => $status,
                'paid_amount' => $paid,
                'remaining_amount' => $remaining,
                'proof_status' => $status === PaymentStatus::REFUNDED
                    ? PaymentStatus::PROOF_NONE
                    : $lockedPayment->proof_status,
            ]);

            return $lockedPayment->refresh();
        });
    }
}
