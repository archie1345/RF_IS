<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('proof_disk', 32)->default('public')->after('proof_path');
        });

        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->string('proof_disk', 32)->default('public')->after('proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropColumn('proof_disk');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('proof_disk');
        });
    }
};
