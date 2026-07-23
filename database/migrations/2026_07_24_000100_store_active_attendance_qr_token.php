<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('training_sessions', 'attendance_qr_token')) {
                $table->text('attendance_qr_token')->nullable()->after('attendance_token_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('training_sessions', 'attendance_qr_token')) {
                $table->dropColumn('attendance_qr_token');
            }
        });
    }
};
