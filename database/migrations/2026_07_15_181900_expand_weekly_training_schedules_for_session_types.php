<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropIndexIfExists('weekly_training_schedules', 'weekly_training_unique_window');

        if (! Schema::hasColumn('weekly_training_schedules', 'session_type')) {
            Schema::table('weekly_training_schedules', function (Blueprint $table): void {
                $table->string('session_type', 40)->default('reguler')->after('coach_id');
            });
        }

        if (! $this->indexExists('weekly_training_schedules', 'weekly_training_schedules_session_type_index')) {
            Schema::table('weekly_training_schedules', function (Blueprint $table): void {
                $table->index('session_type');
            });
        }

        if (Schema::hasColumn('weekly_training_schedules', 'dedicated_athlete_id') && ! $this->isCompatibleAthleteIdColumn()) {
            $this->dropForeignIfExists('weekly_training_schedules', 'weekly_training_schedules_dedicated_athlete_id_foreign');
            Schema::table('weekly_training_schedules', function (Blueprint $table): void {
                $table->dropColumn('dedicated_athlete_id');
            });
        }

        if (! Schema::hasColumn('weekly_training_schedules', 'dedicated_athlete_id')) {
            Schema::table('weekly_training_schedules', function (Blueprint $table): void {
                $table->string('dedicated_athlete_id', 26)->nullable()->after('group_id');
            });
        }

        if (! $this->foreignKeyExists('weekly_training_schedules', 'weekly_training_schedules_dedicated_athlete_id_foreign')) {
            Schema::table('weekly_training_schedules', function (Blueprint $table): void {
                $table->foreign('dedicated_athlete_id', 'weekly_training_schedules_dedicated_athlete_id_foreign')
                    ->references('athlete_id')
                    ->on('athletes')
                    ->nullOnDelete();
            });
        }

        if (! $this->indexExists('weekly_training_schedules', 'weekly_training_slot_type_index')) {
            Schema::table('weekly_training_schedules', function (Blueprint $table): void {
                $table->index(
                    ['branch_id', 'group_id', 'dedicated_athlete_id', 'session_type', 'day_of_week', 'start_time'],
                    'weekly_training_slot_type_index'
                );
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('weekly_training_schedules', 'weekly_training_slot_type_index');
        $this->dropForeignIfExists('weekly_training_schedules', 'weekly_training_schedules_dedicated_athlete_id_foreign');

        if (Schema::hasColumn('weekly_training_schedules', 'dedicated_athlete_id')) {
            Schema::table('weekly_training_schedules', function (Blueprint $table): void {
                $table->dropColumn('dedicated_athlete_id');
            });
        }

        if (Schema::hasColumn('weekly_training_schedules', 'session_type')) {
            $this->dropIndexIfExists('weekly_training_schedules', 'weekly_training_schedules_session_type_index');
            Schema::table('weekly_training_schedules', function (Blueprint $table): void {
                $table->dropColumn('session_type');
            });
        }

        // The legacy unique window is intentionally not recreated here.
        // New-format data can validly contain multiple session types in the same time window.
    }

    private function isCompatibleAthleteIdColumn(): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return true;
        }

        $database = DB::getDatabaseName();
        $column = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', 'weekly_training_schedules')
            ->where('COLUMN_NAME', 'dedicated_athlete_id')
            ->first(['DATA_TYPE', 'CHARACTER_MAXIMUM_LENGTH']);

        if (! $column) {
            return false;
        }

        return in_array(strtolower((string) $column->DATA_TYPE), ['char', 'varchar'], true)
            && (int) $column->CHARACTER_MAXIMUM_LENGTH === 26;
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => ($row->name ?? null) === $index);
        }

        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($index): void {
            $table->dropIndex($index);
        });
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        if (! $this->foreignKeyExists($table, $constraint)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($constraint): void {
            $table->dropForeign($constraint);
        });
    }
};
