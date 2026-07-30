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
use Throwable;

class SubmitPaymentProof
{
    public function handle(Payment $payment, User $payer, UploadedFile $file, ?string $notes = null): Payment
    {
        $disk = Payment::PROOF_DISK_PRIVATE;
        $path = $file->store('payment-proofs/'.$payment->payment_id, $disk);

        try {
            return DB::transaction(function () use ($payment, $payer, $path, $disk, $notes): Payment {
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

                $lockedPayment->update([
                    'payer_user_id' => $payer->id,
                    'proof_path' => $path,
                    'proof_disk' => $disk,
                    'proof_status' => PaymentStatus::PROOF_SUBMITTED,
                    'proof_notes' => $notes,
                ]);

                PaymentTransaction::query()->create([
                    'payment_id' => $lockedPayment->payment_id,
                    'verified_by' => $payer->id,
                    'amount' => 0,
                    'transaction_date' => now(),
                    'payment_method' => $lockedPayment->collection_method ?? 'OTHER',
                    'transaction_type' => PaymentTransaction::TYPE_PROOF_SUBMITTED,
                    'notes' => 'Payment proof submitted for review.',
                    'proof_path' => $path,
                    'proof_disk' => $disk,
                    'proof_notes' => $notes,
                ]);

                return $lockedPayment->refresh();
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }
}
