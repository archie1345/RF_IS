<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('training_sessions', 'weekly_training_schedule_id')) {
                $table->unsignedBigInteger('weekly_training_schedule_id')->nullable()->after('training_session_id');
                $table->index('weekly_training_schedule_id', 'training_sessions_weekly_schedule_id_index');
                $table->unique(['weekly_training_schedule_id', 'session_date'], 'training_sessions_weekly_date_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('training_sessions', 'weekly_training_schedule_id')) {
                $table->dropUnique('training_sessions_weekly_date_unique');
                $table->dropIndex('training_sessions_weekly_schedule_id_index');
                $table->dropColumn('weekly_training_schedule_id');
            }
        });
    }
};
