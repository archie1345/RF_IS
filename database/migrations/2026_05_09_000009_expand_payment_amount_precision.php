<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('amount', 14, 2)->change();
            $table->decimal('total_amount', 14, 2)->nullable()->change();
            $table->decimal('paid_amount', 14, 2)->nullable()->change();
            $table->decimal('remaining_amount', 14, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('amount', 10, 2)->change();
            $table->decimal('total_amount', 10, 2)->nullable()->change();
            $table->decimal('paid_amount', 10, 2)->nullable()->change();
            $table->decimal('remaining_amount', 10, 2)->nullable()->change();
        });
    }
};
