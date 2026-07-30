<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments') || ! Schema::hasTable('payment_transactions')) {
            return;
        }

        DB::table('payments')
            ->whereNull('deleted_at')
            ->where('paid_amount', '>', 0)
            ->orderBy('payment_id')
            ->chunkById(200, function ($payments): void {
                foreach ($payments as $payment) {
                    $approved = (float) DB::table('payment_transactions')
                        ->where('payment_id', $payment->payment_id)
                        ->whereNull('deleted_at')
                        ->where('transaction_type', 'PAYMENT')
                        ->sum('amount');
                    $refunded = (float) DB::table('payment_transactions')
                        ->where('payment_id', $payment->payment_id)
                        ->whereNull('deleted_at')
                        ->where('transaction_type', 'REFUND')
                        ->sum('amount');
                    $ledgerBalance = max($approved - $refunded, 0);
                    $missing = (float) $payment->paid_amount - $ledgerBalance;

                    if ($missing <= 0.009) {
                        continue;
                    }

                    DB::table('payment_transactions')->insert([
                        'payment_id' => $payment->payment_id,
                        'verified_by' => null,
                        'amount' => $missing,
                        'transaction_date' => $payment->payment_date ?? now()->toDateString(),
                        'payment_method' => $payment->collection_method ?? 'OTHER',
                        'transaction_type' => 'PAYMENT',
                        'notes' => 'Legacy opening balance created during finance ledger migration. This preserves the approved paid amount that existed before transaction-level tracking was enforced.',
                        'created_at' => now(),
                        'updated_at' => now(),
                        'deleted_at' => null,
                    ]);
                }
            }, 'payment_id');
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_transactions')) {
            return;
        }

        DB::table('payment_transactions')
            ->where('notes', 'like', 'Legacy opening balance created during finance ledger migration.%')
            ->delete();
    }
};
