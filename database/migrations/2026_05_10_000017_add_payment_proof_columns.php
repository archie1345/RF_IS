<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('payer_user_id')->nullable()->after('athlete_id')->constrained('users')->nullOnDelete();
            $table->string('proof_path', 255)->nullable()->after('notes');
            $table->enum('proof_status', ['NONE', 'SUBMITTED', 'APPROVED', 'REJECTED'])->default('NONE')->index()->after('proof_path');
            $table->text('proof_notes')->nullable()->after('proof_status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payer_user_id');
            $table->dropColumn(['proof_path', 'proof_status', 'proof_notes']);
        });
    }
};
