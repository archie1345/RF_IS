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
        if (($payment->proof_status ?? PaymentStatus::PROOF_NONE) !== PaymentStatus::PROOF_SUBMITTED) {
            throw ValidationException::withMessages([
                'proof_review' => 'A user must upload payment proof before it can be approved or rejected.',
            ]);
        }

        if ($validated['decision'] === PaymentStatus::PROOF_APPROVED) {
            return $this->approve($payment, $reviewer, $validated);
        }

        return DB::transaction(function () use ($payment, $validated): Payment {
            $payment->update([
                'proof_status' => PaymentStatus::PROOF_REJECTED,
                'proof_notes' => $validated['notes'] ?? null,
                'proof_path' => $payment->proof_path,
            ]);

            return $payment->refresh();
        });
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

        return DB::transaction(function () use ($payment, $reviewer, $validated, $amountToApprove, $newPaid, $newRemaining, $reviewedProofPath, $submittedProofNotes, $previousPaymentStatus, $previousProofStatus): Payment {
            PaymentTransaction::query()->create([
                'payment_id' => $payment->payment_id,
                'verified_by' => $reviewer->id,
                'amount' => $amountToApprove,
                'transaction_date' => now(),
                'transaction_type' => PaymentTransaction::TYPE_PAYMENT,
                'payment_method' => $this->paymentRows->extractCollectionMethod($payment->notes),
                'notes' => $this->proofReviewTransactionNotes($validated['notes'] ?? null, $submittedProofNotes, $previousPaymentStatus, $previousProofStatus, $newRemaining),
                'proof_path' => $reviewedProofPath,
                'proof_notes' => $submittedProofNotes,
            ]);

            $payment->update([
                'paid_amount' => $newPaid,
                'remaining_amount' => $newRemaining,
                'status' => $newRemaining <= 0 ? PaymentStatus::COMPLETED : PaymentStatus::PENDING,
                'proof_status' => $newRemaining <= 0 ? PaymentStatus::PROOF_APPROVED : PaymentStatus::PROOF_NONE,
                'proof_path' => $newRemaining <= 0 ? $reviewedProofPath : null,
                'proof_notes' => $validated['notes'] ?? null,
            ]);

            return $payment->refresh();
        });
    }

    private function proofReviewTransactionNotes(?string $adminNotes, ?string $submittedNotes, string $previousPaymentStatus, string $previousProofStatus, float $newRemaining): string
    {
        return collect([
            'Proof approved.',
            'Previous payment status: '.$previousPaymentStatus.'.',
            'Previous proof status: '.$previousProofStatus.'.',
            'New payment state: '.($newRemaining <= 0 ? PaymentStatus::COMPLETED : PaymentStatus::PENDING).'.',
            filled($adminNotes) ? 'Proof approved: '.$adminNotes : null,
            filled($submittedNotes) ? 'Submitted note: '.$submittedNotes : null,
        ])->filter()->implode("\n");
    }
}
