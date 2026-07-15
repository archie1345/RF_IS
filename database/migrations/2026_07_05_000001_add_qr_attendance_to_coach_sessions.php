<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('coach_sessions', function (Blueprint $table) {
            $table->string('attendance_scan_token', 96)->nullable()->after('status');
            $table->string('attendance_token_hash', 96)->nullable()->after('attendance_scan_token');
            $table->dateTime('attendance_opens_at')->nullable()->after('attendance_token_hash');
            $table->dateTime('attendance_closes_at')->nullable()->after('attendance_opens_at');
            $table->dateTime('attendance_qr_generated_at')->nullable()->after('attendance_closes_at');
            $table->dateTime('attendance_qr_revoked_at')->nullable()->after('attendance_qr_generated_at');
            $table->index('attendance_token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coach_sessions', function (Blueprint $table) {
            $table->dropIndex(['attendance_token_hash']);
            $table->dropColumn([
                'attendance_scan_token',
                'attendance_token_hash',
                'attendance_opens_at',
                'attendance_closes_at',
                'attendance_qr_generated_at',
                'attendance_qr_revoked_at',
            ]);
        });
    }
};
