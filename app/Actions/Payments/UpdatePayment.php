<?php

namespace App\Actions\Payments;

use App\Actions\Payments\Concerns\NormalizesPaymentInput;
use App\Actions\Payments\Concerns\ValidatesPaymentRecipient;
use App\Models\Payment;
use App\Support\Domain\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePayment
{
    use NormalizesPaymentInput;
    use ValidatesPaymentRecipient;

    public function handle(Payment $payment, array $validated): Payment
    {
        $validated = $this->normalizePaymentData($validated);
        $this->validatePaymentRecipient($validated);

        return DB::transaction(function () use ($payment, $validated): Payment {
            $lockedPayment = Payment::query()
                ->withCount('transactions')
                ->lockForUpdate()
                ->findOrFail($payment->payment_id);

            $totalAmount = (float) $validated['total_amount'];
            $paidAmount = (float) ($lockedPayment->paid_amount ?? 0);

            if ($totalAmount < $paidAmount) {
                throw ValidationException::withMessages([
                    'total_amount' => 'The bill total cannot be lower than the amount already recorded in the transaction ledger.',
                ]);
            }

            $hasFinancialOrProofHistory = $lockedPayment->transactions_count > 0
                || filled($lockedPayment->proof_path)
                || ($lockedPayment->proof_status ?? PaymentStatus::PROOF_NONE) !== PaymentStatus::PROOF_NONE;

            if ($hasFinancialOrProofHistory && $this->identityChanged($lockedPayment, $validated)) {
                throw ValidationException::withMessages([
                    'billable_user_id' => 'The recipient, bill kind, and category cannot be changed after payment or proof activity. Create a new bill instead.',
                ]);
            }

            $remainingAmount = max($totalAmount - $paidAmount, 0);
            $status = in_array($lockedPayment->status, [PaymentStatus::FAILED, PaymentStatus::REFUNDED], true)
                ? $lockedPayment->status
                : ($remainingAmount <= 0 ? PaymentStatus::COMPLETED : PaymentStatus::PENDING);

            $lockedPayment->update([
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
                'due_date' => $validated['due_date'],
                'collection_method' => $validated['collection_method'],
                'status' => $status,
                'notes' => $this->notesFrom($validated),
            ]);

            return $lockedPayment->refresh();
        });
    }

    private function identityChanged(Payment $payment, array $validated): bool
    {
        return (string) ($payment->athlete_id ?? '') !== (string) ($validated['athlete_id'] ?? '')
            || (string) ($payment->billable_user_id ?? '') !== (string) ($validated['billable_user_id'] ?? '')
            || (string) ($payment->payee_user_id ?? '') !== (string) ($validated['payee_user_id'] ?? '')
            || strtoupper((string) $payment->bill_kind) !== strtoupper((string) $validated['bill_kind'])
            || strtoupper((string) $payment->payment_type) !== strtoupper((string) $validated['payment_type']);
    }
}
