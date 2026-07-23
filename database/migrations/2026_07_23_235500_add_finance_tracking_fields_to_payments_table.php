<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'invoice_number')) {
                $table->string('invoice_number', 32)->nullable()->after('payment_id');
            }

            if (! Schema::hasColumn('payments', 'due_date')) {
                $table->date('due_date')->nullable()->after('payment_date');
            }

            if (! Schema::hasColumn('payments', 'collection_method')) {
                $table->string('collection_method', 20)->nullable()->after('due_date');
            }
        });

        DB::table('payments')
            ->orderBy('payment_id')
            ->chunkById(200, function ($payments): void {
                foreach ($payments as $payment) {
                    $issuedOn = Carbon::parse($payment->payment_date ?? $payment->created_at ?? now());
                    $firstNotePart = strtoupper(trim(explode('|', (string) ($payment->notes ?? ''))[0] ?? ''));
                    $collectionMethod = in_array($firstNotePart, ['CASH', 'CARD', 'TRANSFER', 'OTHER'], true)
                        ? $firstNotePart
                        : 'TRANSFER';

                    DB::table('payments')
                        ->where('payment_id', $payment->payment_id)
                        ->update([
                            'invoice_number' => $payment->invoice_number
                                ?? 'INV-'.$issuedOn->format('Ym').'-'.str_pad((string) $payment->payment_id, 6, '0', STR_PAD_LEFT),
                            'due_date' => $payment->due_date ?? $issuedOn->toDateString(),
                            'collection_method' => $payment->collection_method ?? $collectionMethod,
                        ]);
                }
            }, 'payment_id');

        Schema::table('payments', function (Blueprint $table): void {
            $table->unique('invoice_number', 'payments_invoice_number_unique');
            $table->index(['bill_kind', 'status', 'due_date'], 'payments_tracking_queue_index');
            $table->index(['proof_status', 'due_date'], 'payments_proof_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_proof_due_index');
            $table->dropIndex('payments_tracking_queue_index');
            $table->dropUnique('payments_invoice_number_unique');
            $table->dropColumn(['invoice_number', 'due_date', 'collection_method']);
        });
    }
};
