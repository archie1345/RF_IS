<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignKeysForColumn('athletes', 'parent_id');

        $this->dropForeignKeysForColumn('athlete_attendance', 'athlete_id');
        $this->dropForeignKeysForColumn('payments', 'athlete_id');
        $this->dropForeignKeysForColumn('event_registrations', 'athlete_id');
        $this->dropForeignKeysForColumn('event_results', 'athlete_id');

        $this->dropForeignKeysForColumn('training_sessions', 'coach_id');
        $this->dropForeignKeysForColumn('training_session_coaches', 'coach_id');
        $this->dropForeignKeysForColumn('coach_attendance', 'coach_id');
        $this->dropForeignKeysForColumn('event_coach_registrations', 'coach_id');

        Schema::table('parents', function (Blueprint $table) {
            $table->char('parent_id', 26)->change();
        });

        Schema::table('coaches', function (Blueprint $table) {
            $table->char('coach_id', 26)->change();
        });

        Schema::table('athletes', function (Blueprint $table) {
            $table->char('athlete_id', 26)->change();
            $table->char('parent_id', 26)->nullable()->change();
        });

        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->char('athlete_id', 26)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->char('athlete_id', 26)->nullable()->change();
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->char('athlete_id', 26)->change();
        });

        Schema::table('event_results', function (Blueprint $table) {
            $table->char('athlete_id', 26)->change();
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->char('coach_id', 26)->change();
        });

        Schema::table('training_session_coaches', function (Blueprint $table) {
            $table->char('coach_id', 26)->change();
        });

        Schema::table('coach_attendance', function (Blueprint $table) {
            $table->char('coach_id', 26)->change();
        });

        Schema::table('event_coach_registrations', function (Blueprint $table) {
            $table->char('coach_id', 26)->change();
        });

        Schema::table('athletes', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('parent_id')
                ->on('parents')
                ->nullOnDelete();
        });

        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->foreign('athlete_id')
                ->references('athlete_id')
                ->on('athletes')
                ->cascadeOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('athlete_id')
                ->references('athlete_id')
                ->on('athletes')
                ->nullOnDelete();
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreign('athlete_id')
                ->references('athlete_id')
                ->on('athletes')
                ->cascadeOnDelete();
        });

        Schema::table('event_results', function (Blueprint $table) {
            $table->foreign('athlete_id')
                ->references('athlete_id')
                ->on('athletes')
                ->cascadeOnDelete();
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->foreign('coach_id')
                ->references('coach_id')
                ->on('coaches')
                ->cascadeOnDelete();
        });

        Schema::table('training_session_coaches', function (Blueprint $table) {
            $table->foreign('coach_id')
                ->references('coach_id')
                ->on('coaches')
                ->cascadeOnDelete();
        });

        Schema::table('coach_attendance', function (Blueprint $table) {
            $table->foreign('coach_id')
                ->references('coach_id')
                ->on('coaches')
                ->cascadeOnDelete();
        });

        Schema::table('event_coach_registrations', function (Blueprint $table) {
            $table->foreign('coach_id')
                ->references('coach_id')
                ->on('coaches')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $tables = [
            'parents',
            'coaches',
            'athletes',
            'athlete_attendance',
            'payments',
            'event_registrations',
            'event_results',
            'training_sessions',
            'training_session_coaches',
            'coach_attendance',
            'event_coach_registrations',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException(
                    "Cannot roll back ULID conversion because table [{$table}] contains data."
                );
            }
        }

        $this->dropForeignKeysForColumn('athletes', 'parent_id');

        $this->dropForeignKeysForColumn('athlete_attendance', 'athlete_id');
        $this->dropForeignKeysForColumn('payments', 'athlete_id');
        $this->dropForeignKeysForColumn('event_registrations', 'athlete_id');
        $this->dropForeignKeysForColumn('event_results', 'athlete_id');

        $this->dropForeignKeysForColumn('training_sessions', 'coach_id');
        $this->dropForeignKeysForColumn('training_session_coaches', 'coach_id');
        $this->dropForeignKeysForColumn('coach_attendance', 'coach_id');
        $this->dropForeignKeysForColumn('event_coach_registrations', 'coach_id');

        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->unsignedBigInteger('athlete_id')->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('athlete_id')->nullable()->change();
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('athlete_id')->change();
        });

        Schema::table('event_results', function (Blueprint $table) {
            $table->unsignedBigInteger('athlete_id')->change();
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('coach_id')->change();
        });

        Schema::table('training_session_coaches', function (Blueprint $table) {
            $table->unsignedBigInteger('coach_id')->change();
        });

        Schema::table('coach_attendance', function (Blueprint $table) {
            $table->unsignedBigInteger('coach_id')->change();
        });

        Schema::table('event_coach_registrations', function (Blueprint $table) {
            $table->unsignedBigInteger('coach_id')->change();
        });

        Schema::table('athletes', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->change();
        });

        DB::statement(
            'ALTER TABLE `parents`
             MODIFY `parent_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'
        );

        DB::statement(
            'ALTER TABLE `coaches`
             MODIFY `coach_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'
        );

        DB::statement(
            'ALTER TABLE `athletes`
             MODIFY `athlete_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'
        );

        Schema::table('athletes', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('parent_id')
                ->on('parents')
                ->nullOnDelete();
        });

        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->foreign('athlete_id')
                ->references('athlete_id')
                ->on('athletes')
                ->cascadeOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('athlete_id')
                ->references('athlete_id')
                ->on('athletes')
                ->nullOnDelete();
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->foreign('athlete_id')
                ->references('athlete_id')
                ->on('athletes')
                ->cascadeOnDelete();
        });

        Schema::table('event_results', function (Blueprint $table) {
            $table->foreign('athlete_id')
                ->references('athlete_id')
                ->on('athletes')
                ->cascadeOnDelete();
        });

        Schema::table('training_sessions', function (Blueprint $table) {
            $table->foreign('coach_id')
                ->references('coach_id')
                ->on('coaches')
                ->cascadeOnDelete();
        });

        Schema::table('training_session_coaches', function (Blueprint $table) {
            $table->foreign('coach_id')
                ->references('coach_id')
                ->on('coaches')
                ->cascadeOnDelete();
        });

        Schema::table('coach_attendance', function (Blueprint $table) {
            $table->foreign('coach_id')
                ->references('coach_id')
                ->on('coaches')
                ->cascadeOnDelete();
        });

        Schema::table('event_coach_registrations', function (Blueprint $table) {
            $table->foreign('coach_id')
                ->references('coach_id')
                ->on('coaches')
                ->cascadeOnDelete();
        });
    }

    private function dropForeignKeysForColumn(
        string $table,
        string $column
    ): void {
        if (
            ! Schema::hasTable($table) ||
            ! Schema::hasColumn($table, $column)
        ) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        $constraints = DB::select(
            <<<'SQL'
                SELECT DISTINCT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            SQL,
            [$table, $column]
        );

        foreach ($constraints as $constraint) {
            $tableName = $this->quoteIdentifier($table);
            $constraintName = $this->quoteIdentifier(
                $constraint->CONSTRAINT_NAME
            );

            DB::statement(
                "ALTER TABLE {$tableName} DROP FOREIGN KEY {$constraintName}"
            );
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
};
