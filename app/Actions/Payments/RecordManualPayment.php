<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\Domain\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordManualPayment
{
    public function handle(Payment $payment, User $admin, array $validated): Payment
    {
        return DB::transaction(function () use ($payment, $admin, $validated): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->payment_id);
            $remaining = (float) ($lockedPayment->remaining_amount ?? 0);
            $amount = (float) $validated['amount'];

            if (in_array($lockedPayment->status, [PaymentStatus::FAILED, PaymentStatus::REFUNDED], true)) {
                throw ValidationException::withMessages([
                    'amount' => 'Failed or refunded bills cannot receive another payment. Reopen or replace the bill first.',
                ]);
            }

            if (($lockedPayment->proof_status ?? PaymentStatus::PROOF_NONE) === PaymentStatus::PROOF_SUBMITTED) {
                throw ValidationException::withMessages([
                    'amount' => 'A receipt is waiting for review. Approve or reject that receipt before recording a separate manual payment.',
                ]);
            }

            if ($remaining <= 0) {
                throw ValidationException::withMessages(['amount' => 'This bill is already fully paid.']);
            }

            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'The recorded amount cannot exceed the current balance.',
                ]);
            }

            PaymentTransaction::query()->create([
                'payment_id' => $lockedPayment->payment_id,
                'verified_by' => $admin->id,
                'amount' => $amount,
                'transaction_date' => $validated['transaction_date'],
                'payment_method' => $validated['payment_method'],
                'transaction_type' => PaymentTransaction::TYPE_PAYMENT,
                'notes' => collect([
                    'Manual payment recorded by admin.',
                    filled($validated['notes'] ?? null) ? trim((string) $validated['notes']) : null,
                ])->filter()->implode("\n"),
            ]);

            $newPaid = min((float) ($lockedPayment->paid_amount ?? 0) + $amount, (float) $lockedPayment->total_amount);
            $newRemaining = max((float) $lockedPayment->total_amount - $newPaid, 0);

            $lockedPayment->update([
                'payer_user_id' => $lockedPayment->payer_user_id
                    ?? $lockedPayment->billable_user_id
                    ?? $lockedPayment->athlete?->id,
                'paid_amount' => $newPaid,
                'remaining_amount' => $newRemaining,
                'status' => $newRemaining <= 0 ? PaymentStatus::COMPLETED : PaymentStatus::PENDING,
                'proof_status' => $lockedPayment->proof_status === PaymentStatus::PROOF_REJECTED
                    ? PaymentStatus::PROOF_NONE
                    : $lockedPayment->proof_status,
            ]);

            return $lockedPayment->refresh();
        });
    }
}
