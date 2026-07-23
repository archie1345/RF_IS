<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            ])->default('PAYMENT')->index()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->enum('transaction_type', ['PAYMENT', 'REFUND'])
                ->default('PAYMENT')
                ->index()
                ->change();
        });
    }
};
