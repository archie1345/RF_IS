<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Support\Domain\PaymentStatus;
use Illuminate\Support\Facades\DB;

class UpdatePaymentStatus
{
    public function handle(Payment $payment, string $status): Payment
    {
        return DB::transaction(function () use ($payment, $status): Payment {
            $total = (float) ($payment->total_amount ?? $payment->amount ?? 0);
            $paid = (float) ($payment->paid_amount ?? 0);

            if ($status === PaymentStatus::COMPLETED) {
                $paid = $total;
            }

            if ($status === PaymentStatus::PENDING && $paid >= $total) {
                $paid = max($total - 1, 0);
            }

            if ($status === PaymentStatus::FAILED || $status === PaymentStatus::REFUNDED) {
                $paid = 0.0;
            }

            $payment->update([
                'status' => $status,
                'paid_amount' => $paid,
                'remaining_amount' => max($total - $paid, 0),
            ]);

            return $payment->refresh();
        });
    }
}
