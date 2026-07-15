<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table) {
            $table->string('attendance_token_hash', 64)->nullable()->unique()->after('status');
            $table->timestamp('attendance_opens_at')->nullable()->after('attendance_token_hash');
            $table->timestamp('attendance_closes_at')->nullable()->after('attendance_opens_at');
            $table->timestamp('attendance_qr_generated_at')->nullable()->after('attendance_closes_at');
            $table->timestamp('attendance_qr_revoked_at')->nullable()->after('attendance_qr_generated_at');
        });

        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->unique(['athlete_id', 'training_session_id'], 'athlete_attendance_athlete_session_unique');
        });
    }

    public function down(): void
    {
        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->dropUnique('athlete_attendance_athlete_session_unique');
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->dropUnique(['attendance_token_hash']);
            $table->dropColumn([
                'attendance_token_hash',
                'attendance_opens_at',
                'attendance_closes_at',
                'attendance_qr_generated_at',
                'attendance_qr_revoked_at',
            ]);
        });
    }
};
