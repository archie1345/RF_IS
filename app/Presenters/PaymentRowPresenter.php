<?php

namespace App\Presenters;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Presenters\Concerns\FormatsPresenterData;
use App\Support\Domain\PaymentStatus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PaymentRowPresenter
{
    use FormatsPresenterData;

    public function row(Payment $payment): array
    {
        $phone = $payment->athlete?->user?->phone ?? $payment->billableUser?->phone ?? $payment->payeeUser?->phone ?? '';

        return [
            'id' => 'PAY-'.$payment->payment_id,
            'payment_id' => $payment->payment_id,
            'athlete_id' => $payment->athlete_id,
            'athlete_user_id' => $payment->athlete?->id,
            'billable_user_id' => $payment->billable_user_id,
            'payee_user_id' => $payment->payee_user_id,
            'bill_kind' => $payment->bill_kind ?? 'INVOICE',
            'athlete' => $this->subject($payment),
            'athlete_phone' => $phone,
            'whatsapp_url' => $this->whatsAppUrl($payment, $phone),
            'type' => Str::headline(strtolower((string) $payment->payment_type)),
            'payment_type_raw' => $payment->payment_type,
            'amount' => $this->rupiah((float) ($payment->total_amount ?? $payment->amount)),
            'total_amount_raw' => (string) ($payment->total_amount ?? $payment->amount ?? 0),
            'paid_amount_raw' => (string) ($payment->paid_amount ?? 0),
            'remaining_amount_raw' => (string) ($payment->remaining_amount ?? 0),
            'balance' => $this->rupiah((float) ($payment->remaining_amount ?? 0)),
            'payment_date_raw' => optional($payment->payment_date)->format('Y-m-d') ?? '',
            'issued' => optional($payment->payment_date)->format('d M Y') ?? '-',
            'notes_raw' => $payment->notes ?? '',
            'collection_method_raw' => $this->extractCollectionMethod($payment->notes),
            'status_value' => $payment->status,
            'proof_status' => $payment->proof_status ?? PaymentStatus::PROOF_NONE,
            'proof_status_label' => $this->proofBadge((string) ($payment->proof_status ?? PaymentStatus::PROOF_NONE)),
            'proof_url' => $payment->proof_path ? Storage::url($payment->proof_path) : null,
            'proof_notes' => $payment->proof_notes ?? '',
            'transaction_history' => $this->transactionHistory($payment),
            'status' => $this->paymentBadge($payment),
        ];
    }

    public function subject(Payment $payment): string
    {
        if (($payment->bill_kind ?? 'INVOICE') === 'PAYROLL') {
            return 'Payroll: '.($payment->payeeUser?->name ?? 'Unknown coach');
        }

        return $payment->athlete?->user?->name
            ?? $payment->billableUser?->name
            ?? 'Unknown user';
    }

    public function paymentBadge(Payment $payment): array
    {
        $paid = (float) ($payment->paid_amount ?? 0);
        $remaining = (float) ($payment->remaining_amount ?? 0);
        $status = (string) $payment->status;

        return $this->badge(
            PaymentStatus::paymentLabel($status, $paid, $remaining),
            PaymentStatus::paymentTone($status, $paid, $remaining),
        );
    }

    public function proofBadge(string $proofStatus): array
    {
        return $this->badge(PaymentStatus::proofLabel($proofStatus), PaymentStatus::proofTone($proofStatus));
    }

    public function transactionHistory(Payment $payment): array
    {
        return $payment->transactions
            ->map(fn (PaymentTransaction $transaction) => [
                'id' => $transaction->ptid,
                'amount' => $this->rupiah((float) $transaction->amount),
                'amount_raw' => (string) $transaction->amount,
                'date' => optional($transaction->transaction_date)->format('d M Y') ?? '-',
                'method' => $transaction->payment_method,
                'type' => Str::headline(strtolower((string) $transaction->transaction_type)),
                'verified_by' => $transaction->verifier?->name ?? 'System',
                'notes' => $transaction->notes ?? '',
                'proof_notes' => $transaction->proof_notes ?? '',
                'proof_url' => $transaction->proof_path ? Storage::url($transaction->proof_path) : null,
            ])
            ->values()
            ->all();
    }

    public function extractCollectionMethod(?string $notes): string
    {
        $first = trim(explode('|', (string) $notes)[0] ?? '');

        return in_array($first, ['CASH', 'TRANSFER', 'OTHER'], true) ? $first : 'TRANSFER';
    }

    private function whatsAppUrl(Payment $payment, string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        $message = sprintf(
            "Halo %s, tagihan %s Anda sebesar %s masih memiliki sisa %s. Silakan lakukan pembayaran lalu upload bukti di sistem RF IS. Terima kasih.",
            $this->subject($payment),
            Str::headline(strtolower((string) $payment->payment_type)),
            $this->rupiah((float) ($payment->total_amount ?? $payment->amount ?? 0)),
            $this->rupiah((float) ($payment->remaining_amount ?? 0)),
        );

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }
}
