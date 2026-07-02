<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coach_sessions')
            && Schema::hasTable('training_sessions')
            && Schema::hasColumn('training_sessions', 'training_session_id')
            && Schema::hasColumn('athlete_attendance', 'training_session_id')) {
            return;
        }

        $this->dropForeignKeysForColumns('athlete_attendance', ['coach_session_id']);
        $this->dropForeignKeysForColumns('coach_session_coaches', ['coach_session_id']);
        $this->dropForeignKeysForColumns('session_coach_attendance', ['coach_session_id']);

        $this->dropIndexIfExists('athlete_attendance', 'athlete_attendance_athlete_session_unique');
        $this->dropIndexIfExists('athlete_attendance', 'athlete_attendance_session_date_status_idx');
        $this->dropIndexIfExists('coach_session_coaches', 'coach_session_coach_unique');
        $this->dropIndexIfExists('coach_session_coaches', 'coach_session_coaches_coach_session_idx');
        $this->dropIndexIfExists('session_coach_attendance', 'session_coach_attendance_unique');
        $this->dropIndexIfExists('session_coach_attendance', 'session_coach_attendance_session_status_idx');

        $this->renameTableIfNeeded('coach_sessions', 'training_sessions');
        $this->renameTableIfNeeded('coach_session_coaches', 'training_session_coaches');
        $this->renameTableIfNeeded('session_coach_attendance', 'coach_attendance');

        $this->renameColumnIfNeeded('training_sessions', 'csid', 'training_session_id');
        $this->renameColumnIfNeeded('athlete_attendance', 'atid', 'athlete_attendance_id');
        $this->renameColumnIfNeeded('athlete_attendance', 'coach_session_id', 'training_session_id');
        $this->renameColumnIfNeeded('training_session_coaches', 'id', 'training_session_coach_id');
        $this->renameColumnIfNeeded('training_session_coaches', 'coach_session_id', 'training_session_id');
        $this->renameColumnIfNeeded('coach_attendance', 'scaid', 'coach_attendance_id');
        $this->renameColumnIfNeeded('coach_attendance', 'coach_session_id', 'training_session_id');

        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->foreign('training_session_id')->references('training_session_id')->on('training_sessions')->nullOnDelete();
            $table->unique(['athlete_id', 'training_session_id'], 'athlete_attendance_athlete_session_unique');
            $table->index(['training_session_id', 'date', 'status'], 'athlete_attendance_session_date_status_idx');
        });

        Schema::table('training_session_coaches', function (Blueprint $table) {
            $table->foreign('training_session_id')->references('training_session_id')->on('training_sessions')->cascadeOnDelete();
            $table->unique(['training_session_id', 'coach_id'], 'training_session_coach_unique');
            $table->index(['coach_id', 'training_session_id'], 'training_session_coaches_training_session_idx');
        });

        Schema::table('coach_attendance', function (Blueprint $table) {
            $table->foreign('training_session_id')->references('training_session_id')->on('training_sessions')->cascadeOnDelete();
            $table->unique(['training_session_id', 'coach_id'], 'coach_attendance_unique');
            $table->index(['training_session_id', 'status'], 'coach_attendance_session_status_idx');
        });
    }

    public function down(): void
    {
        $this->dropForeignKeysForColumns('athlete_attendance', ['training_session_id']);
        $this->dropForeignKeysForColumns('training_session_coaches', ['training_session_id']);
        $this->dropForeignKeysForColumns('coach_attendance', ['training_session_id']);

        foreach ([
            ['athlete_attendance', 'athlete_attendance_athlete_session_unique'],
            ['athlete_attendance', 'athlete_attendance_session_date_status_idx'],
            ['training_session_coaches', 'training_session_coach_unique'],
            ['training_session_coaches', 'training_session_coaches_training_session_idx'],
            ['coach_attendance', 'coach_attendance_unique'],
            ['coach_attendance', 'coach_attendance_session_status_idx'],
        ] as [$table, $index]) {
            $this->dropIndexIfExists($table, $index);
        }

        $this->renameColumnIfNeeded('training_sessions', 'training_session_id', 'csid');
        $this->renameColumnIfNeeded('athlete_attendance', 'athlete_attendance_id', 'atid');
        $this->renameColumnIfNeeded('athlete_attendance', 'training_session_id', 'coach_session_id');
        $this->renameColumnIfNeeded('training_session_coaches', 'training_session_coach_id', 'id');
        $this->renameColumnIfNeeded('training_session_coaches', 'training_session_id', 'coach_session_id');
        $this->renameColumnIfNeeded('coach_attendance', 'coach_attendance_id', 'scaid');
        $this->renameColumnIfNeeded('coach_attendance', 'training_session_id', 'coach_session_id');

        $this->renameTableIfNeeded('coach_attendance', 'session_coach_attendance');
        $this->renameTableIfNeeded('training_session_coaches', 'coach_session_coaches');
        $this->renameTableIfNeeded('training_sessions', 'coach_sessions');

        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->foreign('coach_session_id')->references('csid')->on('coach_sessions')->nullOnDelete();
            $table->unique(['athlete_id', 'coach_session_id'], 'athlete_attendance_athlete_session_unique');
            $table->index(['coach_session_id', 'date', 'status'], 'athlete_attendance_session_date_status_idx');
        });

        Schema::table('coach_session_coaches', function (Blueprint $table) {
            $table->foreign('coach_session_id')->references('csid')->on('coach_sessions')->cascadeOnDelete();
            $table->unique(['coach_session_id', 'coach_id'], 'coach_session_coach_unique');
            $table->index(['coach_id', 'coach_session_id'], 'coach_session_coaches_coach_session_idx');
        });

        Schema::table('session_coach_attendance', function (Blueprint $table) {
            $table->foreign('coach_session_id')->references('csid')->on('coach_sessions')->cascadeOnDelete();
            $table->unique(['coach_session_id', 'coach_id'], 'session_coach_attendance_unique');
            $table->index(['coach_session_id', 'status'], 'session_coach_attendance_session_status_idx');
        });
    }

    private function renameTableIfNeeded(string $from, string $to): void
    {
        if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
            Schema::rename($from, $to);
        }
    }

    private function renameColumnIfNeeded(string $table, string $from, string $to): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn($from, $to));
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }
        try {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
        } catch (Throwable) {
        }
    }

    private function dropForeignKeysForColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }
            try {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign([$column]));
            } catch (Throwable) {
            }
        }
    }
};
