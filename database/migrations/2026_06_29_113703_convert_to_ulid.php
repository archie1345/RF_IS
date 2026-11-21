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

        $this->convertPrimaryKeyToUlid('parents', 'parent_id');
        $this->convertPrimaryKeyToUlid('coaches', 'coach_id');
        $this->convertPrimaryKeyToUlid('athletes', 'athlete_id');

        Schema::table('athletes', function (Blueprint $table) {
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

        $this->convertPrimaryKeyToAutoIncrementBigInt('parents', 'parent_id');
        $this->convertPrimaryKeyToAutoIncrementBigInt('coaches', 'coach_id');
        $this->convertPrimaryKeyToAutoIncrementBigInt('athletes', 'athlete_id');

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

    private function convertPrimaryKeyToUlid(string $table, string $column): void
    {
        if ($this->isTiDb()) {
            // TiDB rejects MODIFY COLUMN on primary key columns (error 8200), so rebuild the table.
            $this->rebuildTableWithPrimaryKeyColumn($table, $column, 'char(26) NOT NULL');

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->char($column, 26)->change();
        });
    }

    private function convertPrimaryKeyToAutoIncrementBigInt(string $table, string $column): void
    {
        if ($this->isTiDb()) {
            $this->rebuildTableWithPrimaryKeyColumn(
                $table,
                $column,
                'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT'
            );

            return;
        }

        $quotedTable = $this->quoteIdentifier($table);
        $quotedColumn = $this->quoteIdentifier($column);

        DB::statement(
            "ALTER TABLE {$quotedTable} MODIFY {$quotedColumn} BIGINT UNSIGNED NOT NULL AUTO_INCREMENT"
        );
    }

    private function isTiDb(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        $row = DB::selectOne('SELECT VERSION() AS version');

        return $row !== null && stripos((string) ($row->version ?? ''), 'TiDB') !== false;
    }

    private function rebuildTableWithPrimaryKeyColumn(
        string $table,
        string $column,
        string $columnDefinition
    ): void {
        $quotedTable = $this->quoteIdentifier($table);
        $createRow = (array) DB::selectOne("SHOW CREATE TABLE {$quotedTable}");
        $createSql = $createRow['Create Table'] ?? $createRow['create table'] ?? null;

        if (! is_string($createSql) || $createSql === '') {
            throw new RuntimeException("Unable to read CREATE TABLE statement for [{$table}].");
        }

        $tmpTable = "{$table}__ulid_tmp";
        $quotedTmpTable = $this->quoteIdentifier($tmpTable);
        Schema::dropIfExists($tmpTable);

        $tmpCreateSql = preg_replace(
            '/^CREATE TABLE\s+`'.preg_quote($table, '/').'`/i',
            "CREATE TABLE {$quotedTmpTable}",
            $createSql,
            1
        );

        if (is_string($tmpCreateSql)) {
            $tmpCreateSql = preg_replace(
                '/`'.preg_quote($column, '/').'`\s+[^,\n]+/i',
                $this->quoteIdentifier($column).' '.$columnDefinition,
                $tmpCreateSql,
                1
            );
        }

        if (is_string($tmpCreateSql)) {
            $tmpCreateSql = preg_replace('/\s+AUTO_INCREMENT=\d+/i', '', $tmpCreateSql);
        }

        if (is_string($tmpCreateSql)) {
            $tmpCreateSql = preg_replace(
                '/\s*\/\*T!\[clustered_index\]\s+(?:NON)?CLUSTERED\s*\*\//i',
                '',
                $tmpCreateSql
            );
        }

        if (! is_string($tmpCreateSql) || $tmpCreateSql === $createSql) {
            throw new RuntimeException("Unable to rewrite CREATE TABLE statement for [{$table}].");
        }

        // Drop outgoing FKs on the original table first so constraint names don't collide on the temp table.
        $this->dropForeignKeysForTable($table);

        DB::statement($tmpCreateSql);
        DB::statement("INSERT INTO {$quotedTmpTable} SELECT * FROM {$quotedTable}");
        DB::statement("DROP TABLE {$quotedTable}");
        DB::statement("RENAME TABLE {$quotedTmpTable} TO {$quotedTable}");
    }

    private function dropForeignKeysForTable(string $table): void
    {
        if (! Schema::hasTable($table) || DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        $constraints = DB::select(
            <<<'SQL'
                SELECT DISTINCT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            SQL,
            [$table]
        );

        $this->dropForeignKeyConstraints($table, $constraints);
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

        $this->dropForeignKeyConstraints($table, $constraints);
    }

    /**
     * @param  array<int, object>  $constraints
     */
    private function dropForeignKeyConstraints(string $table, array $constraints): void
    {
        $tableName = $this->quoteIdentifier($table);

        foreach ($constraints as $constraint) {
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
