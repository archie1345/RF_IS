<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Support\Domain\PaymentStatus;
use Illuminate\Support\Facades\DB;

class UpdatePaymentStatus
{
    public function handle(Payment $payment, string $status): Payment
    {
        return DB::transaction(function () use ($payment, $status): Payment {
            $total = (float) ($payment->total_amount ?? $payment->amount ?? 0);
            $paid = (float) ($payment->paid_amount ?? 0);
            $previousStatus = $payment->status ?? PaymentStatus::PENDING;
            $previousPaid = (float) ($payment->paid_amount ?? 0);
            $previousRemaining = (float) ($payment->remaining_amount ?? max($total - $paid, 0));

            if ($status === PaymentStatus::COMPLETED) {
                $paid = $total;
            }

            if ($status === PaymentStatus::PENDING && $paid >= $total) {
                $paid = max($total - 1, 0);
            }

            if ($status === PaymentStatus::FAILED || $status === PaymentStatus::REFUNDED) {
                $paid = 0.0;
            }

            $remaining = max($total - $paid, 0);

            PaymentTransaction::query()->create([
                'payment_id' => $payment->payment_id,
                'verified_by' => auth()->id(),
                'amount' => 0,
                'transaction_date' => now(),
                'transaction_type' => PaymentTransaction::TYPE_STATUS_CHANGE,
                'payment_method' => 'MANUAL_STATUS',
                'notes' => "Payment status changed from {$previousStatus} to {$status}. Previous paid: {$previousPaid}. Previous remaining: {$previousRemaining}. New paid: {$paid}. New remaining: {$remaining}.",
                'proof_path' => $payment->proof_path,
                'proof_notes' => $payment->proof_notes,
            ]);

            $payment->update([
                'status' => $status,
                'paid_amount' => $paid,
                'remaining_amount' => $remaining,
            ]);

            return $payment->refresh();
        });
    }
}
