<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->string('proof_path', 255)->nullable()->after('notes');
            $table->text('proof_notes')->nullable()->after('proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table): void {
            $table->dropColumn(['proof_path', 'proof_notes']);
        });
    }
};
