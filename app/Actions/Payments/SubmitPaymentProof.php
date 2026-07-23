<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\Domain\PaymentStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SubmitPaymentProof
{
    public function handle(Payment $payment, User $payer, UploadedFile $file, ?string $notes = null): Payment
    {
        $path = $file->store('payment-proofs', 'public');

        try {
            return DB::transaction(function () use ($payment, $payer, $path, $notes): Payment {
                $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->payment_id);

                if ((float) ($lockedPayment->remaining_amount ?? 0) <= 0) {
                    throw ValidationException::withMessages(['proof_file' => 'This bill is already fully paid.']);
                }

                if (in_array($lockedPayment->status, [PaymentStatus::FAILED, PaymentStatus::REFUNDED], true)) {
                    throw ValidationException::withMessages(['proof_file' => 'This bill is not open for payment.']);
                }

                if (($lockedPayment->proof_status ?? PaymentStatus::PROOF_NONE) === PaymentStatus::PROOF_SUBMITTED) {
                    throw ValidationException::withMessages(['proof_file' => 'Another receipt is already waiting for admin review.']);
                }

                PaymentTransaction::query()->create([
                    'payment_id' => $lockedPayment->payment_id,
                    'verified_by' => $payer->id,
                    'amount' => 0,
                    'transaction_date' => now(),
                    'payment_method' => $lockedPayment->collection_method ?? 'TRANSFER',
                    'transaction_type' => PaymentTransaction::TYPE_PROOF_SUBMITTED,
                    'notes' => collect([
                        'Payment proof submitted for review.',
                        filled($notes) ? trim((string) $notes) : null,
                    ])->filter()->implode("\n"),
                    'proof_path' => $path,
                    'proof_notes' => $notes,
                ]);

                $lockedPayment->update([
                    'payer_user_id' => $payer->id,
                    'proof_path' => $path,
                    'proof_status' => PaymentStatus::PROOF_SUBMITTED,
                    'proof_notes' => $notes,
                ]);

                return $lockedPayment->refresh();
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }
    }
}
