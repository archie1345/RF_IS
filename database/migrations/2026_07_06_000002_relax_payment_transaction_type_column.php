<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_transactions') || ! Schema::hasColumn('payment_transactions', 'transaction_type')) {
            return;
        }

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->string('transaction_type', 40)->default('PAYMENT')->change();
        });
    }

    public function down(): void
    {
        // Keep the flexible string column. Payment audit rows may contain proof and status history values.
    }
};
