<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->enum('transaction_type', [
                'PAYMENT',
                'REFUND',
                'STATUS_CHANGE',
                'PROOF_SUBMITTED',
                'PROOF_REJECTED',
            ])->default('PAYMENT')->change();
        });
    }

    public function down(): void
    {
        DB::table('payment_transactions')
            ->whereNotIn('transaction_type', ['PAYMENT', 'REFUND'])
            ->update(['transaction_type' => 'PAYMENT']);

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->enum('transaction_type', ['PAYMENT', 'REFUND'])
                ->default('PAYMENT')
                ->change();
        });
    }
};
