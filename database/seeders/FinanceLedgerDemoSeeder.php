<?php

namespace Database\Seeders;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinanceLedgerDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $adminId = User::query()->where('email', 'admin@rfis.test')->value('id');

            Payment::query()
                ->with('transactions')
                ->orderBy('payment_id')
                ->get()
                ->each(function (Payment $payment) use ($adminId): void {
                    $paidBalance = (float) ($payment->paid_amount ?? 0);
                    $ledgerPayments = (float) $payment->transactions
                        ->where('transaction_type', PaymentTransaction::TYPE_PAYMENT)
                        ->sum('amount');
                    $ledgerRefunds = (float) $payment->transactions
                        ->where('transaction_type', 'REFUND')
                        ->sum('amount');
                    $ledgerBalance = max($ledgerPayments - $ledgerRefunds, 0);
                    $missingAmount = $paidBalance - $ledgerBalance;

                    if ($missingAmount > 0.009) {
                        PaymentTransaction::query()->create([
                            'payment_id' => $payment->payment_id,
                            'verified_by' => $adminId,
                            'amount' => $missingAmount,
                            'transaction_date' => $payment->payment_date ?? now(),
                            'payment_method' => $payment->collection_method ?? 'TRANSFER',
                            'transaction_type' => PaymentTransaction::TYPE_PAYMENT,
                            'notes' => 'Seeded opening payment transaction to align the demo ledger with the stored balance.',
                        ]);
                    }

                    $payment->forceFill([
                        'due_date' => $payment->due_date
                            ?? (($payment->bill_kind ?? 'INVOICE') === 'PAYROLL'
                                ? $payment->payment_date
                                : $payment->payment_date?->copy()->addDays(14)),
                        'collection_method' => $payment->collection_method ?? 'TRANSFER',
                    ])->save();
                });
        });

        $this->command?->info('Finance demo balances and transaction ledger aligned.');
    }
}
