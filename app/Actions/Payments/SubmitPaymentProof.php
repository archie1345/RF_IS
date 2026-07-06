<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SubmitPaymentProof
{
    public function handle(Payment $payment, User $payer, UploadedFile $file, ?string $notes = null): Payment
    {
        $path = $file->store('payment-proofs', 'public');

        try {
            return DB::transaction(function () use ($payment, $payer, $path, $notes): Payment {
                $previousProofStatus = $payment->proof_status ?? 'NONE';
                $previousPaymentStatus = $payment->status ?? 'PENDING';

                PaymentTransaction::query()->create([
                    'payment_id' => $payment->payment_id,
                    'verified_by' => $payer->id,
                    'amount' => 0,
                    'transaction_date' => now(),
                    'transaction_type' => PaymentTransaction::TYPE_PROOF_SUBMITTED,
                    'payment_method' => 'PROOF_UPLOAD',
                    'notes' => "Proof submitted. Previous payment status: {$previousPaymentStatus}. Previous proof status: {$previousProofStatus}.",
                    'proof_path' => $path,
                    'proof_notes' => $notes,
                ]);

                $payment->update([
                    'payer_user_id' => $payer->id,
                    'proof_path' => $path,
                    'proof_status' => 'SUBMITTED',
                    'proof_notes' => $notes,
                ]);

                return $payment->refresh();
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }
    }
}
