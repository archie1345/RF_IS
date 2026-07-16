<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_training_schedules', function (Blueprint $table): void {
            try {
                $table->dropUnique('weekly_training_unique_window');
            } catch (Throwable) {
            }

            if (! Schema::hasColumn('weekly_training_schedules', 'session_type')) {
                $table->string('session_type', 40)->default('reguler')->after('coach_id')->index();
            }
            if (! Schema::hasColumn('weekly_training_schedules', 'dedicated_athlete_id')) {
                $table->foreignId('dedicated_athlete_id')->nullable()->after('group_id')->constrained('athletes', 'athlete_id')->nullOnDelete();
            }

            $table->index(
                ['branch_id', 'group_id', 'dedicated_athlete_id', 'session_type', 'day_of_week', 'start_time'],
                'weekly_training_slot_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('weekly_training_schedules', function (Blueprint $table): void {
            try {
                $table->dropIndex('weekly_training_slot_type_index');
            } catch (Throwable) {
            }

            if (Schema::hasColumn('weekly_training_schedules', 'dedicated_athlete_id')) {
                $table->dropConstrainedForeignId('dedicated_athlete_id');
            }
            if (Schema::hasColumn('weekly_training_schedules', 'session_type')) {
                $table->dropColumn('session_type');
            }

            // The legacy unique window is intentionally not recreated here.
            // New-format data can validly contain multiple session types in the same time window.
        });
    }
};
