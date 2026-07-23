<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Presenters\PaymentRowPresenter;
use App\Support\Domain\PaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewPaymentProof
{
    public function __construct(private readonly PaymentRowPresenter $paymentRows) {}

    public function handle(Payment $payment, User $reviewer, array $validated): Payment
    {
        return DB::transaction(function () use ($payment, $reviewer, $validated): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->payment_id);

            if (($lockedPayment->proof_status ?? PaymentStatus::PROOF_NONE) !== PaymentStatus::PROOF_SUBMITTED) {
                throw ValidationException::withMessages([
                    'proof_review' => 'A user must upload payment proof before it can be approved or rejected.',
                ]);
            }

            if ($validated['decision'] === PaymentStatus::PROOF_REJECTED) {
                return $this->reject($lockedPayment, $reviewer, $validated);
            }

            return $this->approve($lockedPayment, $reviewer, $validated);
        });
    }

    private function reject(Payment $payment, User $reviewer, array $validated): Payment
    {
        PaymentTransaction::query()->create([
            'payment_id' => $payment->payment_id,
            'verified_by' => $reviewer->id,
            'amount' => 0,
            'transaction_date' => now(),
            'transaction_type' => PaymentTransaction::TYPE_PROOF_REJECTED,
            'payment_method' => $this->paymentRows->extractCollectionMethod($payment),
            'notes' => collect([
                'Payment proof rejected.',
                filled($validated['notes'] ?? null) ? trim((string) $validated['notes']) : null,
                filled($payment->proof_notes) ? 'Submitted note: '.$payment->proof_notes : null,
            ])->filter()->implode("\n"),
            'proof_path' => $payment->proof_path,
            'proof_notes' => $payment->proof_notes,
        ]);

        $payment->update([
            'proof_status' => PaymentStatus::PROOF_REJECTED,
            'proof_notes' => $validated['notes'] ?? null,
        ]);

        return $payment->refresh();
    }

    private function approve(Payment $payment, User $reviewer, array $validated): Payment
    {
        $total = (float) ($payment->total_amount ?? $payment->amount ?? 0);
        $currentPaid = (float) ($payment->paid_amount ?? 0);
        $currentRemaining = max((float) ($payment->remaining_amount ?? ($total - $currentPaid)), 0);
        $amountToApprove = filled($validated['approved_amount'] ?? null)
            ? (float) $validated['approved_amount']
            : $currentRemaining;

        if ($amountToApprove <= 0) {
            throw ValidationException::withMessages(['approved_amount' => 'Enter an amount greater than zero.']);
        }

        if ($amountToApprove > $currentRemaining) {
            throw ValidationException::withMessages(['approved_amount' => 'The approved amount cannot exceed the remaining balance.']);
        }

        $newPaid = min($currentPaid + $amountToApprove, $total);
        $newRemaining = max($total - $newPaid, 0);
        $reviewedProofPath = $payment->proof_path;
        $submittedProofNotes = $payment->proof_notes;
        $previousPaymentStatus = $payment->status ?? PaymentStatus::PENDING;
        $previousProofStatus = $payment->proof_status ?? PaymentStatus::PROOF_NONE;

        PaymentTransaction::query()->create([
            'payment_id' => $payment->payment_id,
            'verified_by' => $reviewer->id,
            'amount' => $amountToApprove,
            'transaction_date' => now(),
            'transaction_type' => PaymentTransaction::TYPE_PAYMENT,
            'payment_method' => $this->paymentRows->extractCollectionMethod($payment),
            'notes' => $this->proofReviewTransactionNotes(
                $validated['notes'] ?? null,
                $submittedProofNotes,
                $previousPaymentStatus,
                $previousProofStatus,
                $newRemaining,
            ),
            'proof_path' => $reviewedProofPath,
            'proof_notes' => $submittedProofNotes,
        ]);

        $payment->update([
            'paid_amount' => $newPaid,
            'remaining_amount' => $newRemaining,
            'status' => $newRemaining <= 0 ? PaymentStatus::COMPLETED : PaymentStatus::PENDING,
            'proof_status' => $newRemaining <= 0 ? PaymentStatus::PROOF_APPROVED : PaymentStatus::PROOF_NONE,
            'proof_path' => $newRemaining <= 0 ? $reviewedProofPath : null,
            'proof_notes' => $newRemaining <= 0 ? ($validated['notes'] ?? null) : null,
        ]);

        return $payment->refresh();
    }

    private function proofReviewTransactionNotes(?string $adminNotes, ?string $submittedNotes, string $previousPaymentStatus, string $previousProofStatus, float $newRemaining): string
    {
        return collect([
            'Proof approved.',
            'Previous payment status: '.$previousPaymentStatus.'.',
            'Previous proof status: '.$previousProofStatus.'.',
            'New payment state: '.($newRemaining <= 0 ? PaymentStatus::COMPLETED : PaymentStatus::PENDING).'.',
            filled($adminNotes) ? 'Admin note: '.$adminNotes : null,
            filled($submittedNotes) ? 'Submitted note: '.$submittedNotes : null,
        ])->filter()->implode("\n");
    }
}
