<?php

namespace App\Presenters;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Presenters\Concerns\FormatsPresenterData;
use App\Support\Domain\PaymentStatus;
use Illuminate\Support\Str;

class PaymentRowPresenter
{
    use FormatsPresenterData;

    public function row(Payment $payment): array
    {
        $phone = $payment->athlete?->user?->phone ?? $payment->billableUser?->phone ?? $payment->payeeUser?->phone ?? '';
        $paidAmount = (float) ($payment->paid_amount ?? 0);
        $remainingAmount = (float) ($payment->remaining_amount ?? 0);
        $ledgerPaidAmount = $this->ledgerPaidAmount($payment);
        $ledgerConsistent = abs($ledgerPaidAmount - $paidAmount) < 0.01;
        $collectionMethod = $this->extractCollectionMethod($payment);

        return [
            'id' => 'PAY-'.$payment->payment_id,
            'payment_id' => $payment->payment_id,
            'invoice_number' => $payment->invoice_number ?: 'INV-'.$payment->payment_id,
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
            'paid' => $this->rupiah($paidAmount),
            'balance' => $this->rupiah($remainingAmount),
            'total_amount_raw' => (string) ($payment->total_amount ?? $payment->amount ?? 0),
            'paid_amount_raw' => (string) $paidAmount,
            'remaining_amount_raw' => (string) $remainingAmount,
            'ledger_paid_amount_raw' => (string) $ledgerPaidAmount,
            'ledger_consistent' => $ledgerConsistent,
            'ledger_status' => $ledgerConsistent
                ? $this->badge('Ledger sesuai', 'success')
                : $this->badge('Perlu rekonsiliasi', 'danger'),
            'transaction_count' => $payment->transactions->count(),
            'payment_date_raw' => optional($payment->payment_date)->format('Y-m-d') ?? '',
            'due_date_raw' => optional($payment->due_date)->format('Y-m-d') ?? '',
            'issued' => optional($payment->payment_date)->format('d M Y') ?? '-',
            'due' => optional($payment->due_date)->format('d M Y') ?? '-',
            'is_overdue' => $payment->isOverdue(),
            'notes_raw' => $this->cleanNotes($payment->notes),
            'collection_method_raw' => $collectionMethod,
            'status_value' => $payment->status,
            'proof_status' => $payment->proof_status ?? PaymentStatus::PROOF_NONE,
            'proof_status_label' => $this->proofBadge((string) ($payment->proof_status ?? PaymentStatus::PROOF_NONE)),
            'proof_url' => $payment->proof_path ? route('payments.proof.download', $payment) : null,
            'proof_notes' => $payment->proof_notes ?? '',
            'transaction_history' => $this->transactionHistory($payment),
            'status' => $this->paymentBadge($payment),
            'next_action' => $this->nextAction($payment),
            'can_record_payment' => $remainingAmount > 0
                && ! in_array($payment->status, [PaymentStatus::FAILED, PaymentStatus::REFUNDED], true)
                && $payment->proof_status !== PaymentStatus::PROOF_SUBMITTED,
            'can_delete' => $payment->transactions->isEmpty()
                && blank($payment->proof_path)
                && $paidAmount <= 0,
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
                'amount' => (float) $transaction->amount > 0
                    ? $this->rupiah((float) $transaction->amount)
                    : '-',
                'amount_raw' => (string) $transaction->amount,
                'date' => optional($transaction->transaction_date)->format('d M Y') ?? '-',
                'method' => $transaction->payment_method,
                'type' => $this->transactionTypeLabel((string) $transaction->transaction_type),
                'verified_by' => $transaction->verifier?->name ?? 'System',
                'notes' => $transaction->notes ?? '',
                'proof_notes' => $transaction->proof_notes ?? '',
                'proof_url' => $transaction->proof_path
                    ? route('payments.transactions.proof.download', $transaction)
                    : null,
            ])
            ->values()
            ->all();
    }

    public function extractCollectionMethod(Payment|string|null $source): string
    {
        if ($source instanceof Payment && filled($source->collection_method)) {
            return strtoupper((string) $source->collection_method);
        }

        $notes = $source instanceof Payment ? $source->notes : $source;
        $first = strtoupper(trim(explode('|', (string) $notes)[0] ?? ''));

        return in_array($first, ['CASH', 'CARD', 'TRANSFER', 'OTHER'], true) ? $first : 'TRANSFER';
    }

    private function ledgerPaidAmount(Payment $payment): float
    {
        $payments = (float) $payment->transactions
            ->where('transaction_type', PaymentTransaction::TYPE_PAYMENT)
            ->sum('amount');
        $refunds = (float) $payment->transactions
            ->where('transaction_type', PaymentTransaction::TYPE_REFUND)
            ->sum('amount');

        return max($payments - $refunds, 0);
    }

    private function nextAction(Payment $payment): array
    {
        if ($payment->proof_status === PaymentStatus::PROOF_SUBMITTED) {
            return $this->badge('Tinjau bukti', 'warning');
        }

        if ($payment->status === PaymentStatus::REFUNDED) {
            return $this->badge('Sudah direfund', 'info');
        }

        if ($payment->status === PaymentStatus::FAILED) {
            return $this->badge('Periksa tagihan gagal', 'danger');
        }

        if ((float) ($payment->remaining_amount ?? 0) <= 0) {
            return $this->badge('Selesai', 'success');
        }

        if ($payment->isOverdue()) {
            return $this->badge('Tindak lanjut jatuh tempo', 'danger');
        }

        if ((float) ($payment->paid_amount ?? 0) > 0) {
            return $this->badge('Menunggu cicilan berikutnya', 'warning');
        }

        return ($payment->bill_kind ?? 'INVOICE') === 'PAYROLL'
            ? $this->badge('Catat pembayaran pelatih', 'info')
            : $this->badge('Menunggu pembayaran', 'neutral');
    }

    private function cleanNotes(?string $notes): string
    {
        $parts = array_map('trim', explode('|', (string) $notes));
        if (isset($parts[0]) && in_array(strtoupper($parts[0]), ['CASH', 'CARD', 'TRANSFER', 'OTHER'], true)) {
            array_shift($parts);
        }

        return trim(implode(' | ', array_filter($parts)));
    }

    private function transactionTypeLabel(string $type): string
    {
        return match ($type) {
            PaymentTransaction::TYPE_PAYMENT => 'Pembayaran disetujui',
            PaymentTransaction::TYPE_PROOF_SUBMITTED => 'Bukti dikirim',
            PaymentTransaction::TYPE_PROOF_REJECTED => 'Bukti ditolak',
            PaymentTransaction::TYPE_STATUS_CHANGE => 'Perubahan status',
            PaymentTransaction::TYPE_REFUND => 'Refund',
            default => Str::headline(strtolower($type)),
        };
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
            'Halo %s, tagihan %s (%s) sebesar %s masih memiliki sisa %s dan jatuh tempo %s. Silakan lakukan pembayaran lalu upload bukti di sistem RF IS. Terima kasih.',
            $this->subject($payment),
            $payment->invoice_number ?: 'INV-'.$payment->payment_id,
            Str::headline(strtolower((string) $payment->payment_type)),
            $this->rupiah((float) ($payment->total_amount ?? $payment->amount ?? 0)),
            $this->rupiah((float) ($payment->remaining_amount ?? 0)),
            optional($payment->due_date)->format('d M Y') ?? '-',
        );

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }
}
