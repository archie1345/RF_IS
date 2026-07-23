<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Console\Command;

class AuditFinanceLedger extends Command
{
    protected $signature = 'finance:audit {--repair : Repair stored paid and remaining balances from the immutable transaction ledger}';

    protected $description = 'Check invoice metadata and reconcile stored payment balances against transaction history.';

    public function handle(): int
    {
        $issues = [];
        $repaired = 0;

        Payment::query()
            ->with('transactions')
            ->orderBy('payment_id')
            ->chunkById(200, function ($payments) use (&$issues, &$repaired): void {
                foreach ($payments as $payment) {
                    $total = (float) ($payment->total_amount ?? $payment->amount ?? 0);
                    $ledgerPaid = (float) $payment->transactions
                        ->where('transaction_type', PaymentTransaction::TYPE_PAYMENT)
                        ->sum('amount');
                    $ledgerRefunded = (float) $payment->transactions
                        ->where('transaction_type', 'REFUND')
                        ->sum('amount');
                    $expectedPaid = max(min($ledgerPaid - $ledgerRefunded, $total), 0);
                    $expectedRemaining = max($total - $expectedPaid, 0);
                    $storedPaid = (float) ($payment->paid_amount ?? 0);
                    $storedRemaining = (float) ($payment->remaining_amount ?? 0);
                    $metadataMissing = blank($payment->invoice_number)
                        || $payment->due_date === null
                        || blank($payment->collection_method);
                    $balanceMismatch = abs($storedPaid - $expectedPaid) >= 0.01
                        || abs($storedRemaining - $expectedRemaining) >= 0.01;

                    if (! $metadataMissing && ! $balanceMismatch) {
                        continue;
                    }

                    $issues[] = [
                        $payment->payment_id,
                        $payment->invoice_number ?: '-',
                        number_format($storedPaid, 2, '.', ''),
                        number_format($expectedPaid, 2, '.', ''),
                        number_format($storedRemaining, 2, '.', ''),
                        number_format($expectedRemaining, 2, '.', ''),
                        $metadataMissing ? 'Missing metadata' : 'Balance mismatch',
                    ];

                    if ($this->option('repair') && ! $metadataMissing && $balanceMismatch) {
                        $payment->update([
                            'paid_amount' => $expectedPaid,
                            'remaining_amount' => $expectedRemaining,
                            'status' => $expectedRemaining <= 0 ? 'COMPLETED' : 'PENDING',
                        ]);
                        $repaired++;
                    }
                }
            }, 'payment_id');

        if ($issues === []) {
            $this->info('Finance ledger is consistent. Every stored balance matches its payment and refund transactions.');

            return self::SUCCESS;
        }

        $this->warn(count($issues).' finance record(s) need attention.');
        $this->table(
            ['ID', 'Invoice', 'Stored paid', 'Ledger paid', 'Stored remaining', 'Expected remaining', 'Issue'],
            $issues,
        );

        if ($this->option('repair')) {
            $this->info("Repaired {$repaired} balance record(s). Missing invoice metadata must be corrected through migrations or the admin form.");

            return self::SUCCESS;
        }

        $this->line('Review the listed rows. Run finance:audit --repair only after verifying that transaction history is authoritative.');

        return self::FAILURE;
    }
}
