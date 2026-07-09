<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropLegacyTable('coach_sessions');

        $this->dropColumnsIfPresent('users', ['last_login_at', 'is_active']);

        if (Schema::hasColumn('parents', 'relation')) {
            $this->dropColumnsIfPresent('parents', ['p_name', 'p_phone']);
        }

        if (Schema::hasColumn('athletes', 'id')) {
            $this->dropColumnsIfPresent('athletes', ['name', 'bday', 'gender', 'phone']);
        }

        if (Schema::hasColumn('athletes', 'group_id')) {
            $this->dropColumnsIfPresent('athletes', ['gid']);
        }

        if (Schema::hasColumn('athletes', 'parent_id')) {
            $this->dropColumnsIfPresent('athletes', ['pid']);
        }

        if (Schema::hasColumn('athletes', 'branch_id')) {
            $this->dropColumnsIfPresent('athletes', ['brid']);
        }

        if (Schema::hasTable('coaches')) {
            $this->dropColumnsIfPresent('coaches', ['c_name', 'c_phone', 'license_type', 'license_number', 'license_expiry']);
        }

        if (Schema::hasColumn('payments', 'athlete_id')) {
            $this->dropColumnsIfPresent('payments', ['aid']);
        }

        if (Schema::hasColumn('payments', 'status')) {
            $this->dropColumnsIfPresent('payments', ['payment_status']);
        }

        if (Schema::hasColumn('payment_transactions', 'payment_id')) {
            $this->dropColumnsIfPresent('payment_transactions', ['payid']);
        }

        if (Schema::hasColumn('activity_logs', 'actor_user_id')) {
            $this->dropColumnsIfPresent('activity_logs', ['id']);
        }

        $this->dropColumnsIfPresent('class_groups', ['capacity']);
    }

    public function down(): void
    {
        // Intentionally not reversible. This migration removes legacy/duplicate artifacts only.
        // Restore from database backup if any removed legacy data is needed.
    }

    /**
     * @param array<int, string> $columns
     */
    private function dropColumnsIfPresent(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $existingColumns = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn($table, $column)));

        if ($existingColumns === []) {
            return;
        }

        foreach ($existingColumns as $column) {
            $this->dropForeignKeysForColumn($table, $column);
        }

        Schema::table($table, function (Blueprint $table) use ($existingColumns): void {
            $table->dropColumn($existingColumns);
        });
    }

    private function dropLegacyTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::dropIfExists($table);
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        $database = DB::getDatabaseName();
        $constraints = DB::select(
            'select constraint_name from information_schema.key_column_usage where table_schema = ? and table_name = ? and column_name = ? and referenced_table_name is not null',
            [$database, $table, $column]
        );

        foreach ($constraints as $constraint) {
            $constraintName = $constraint->constraint_name ?? null;
            if (! $constraintName) {
                continue;
            }

            DB::statement('alter table `'.$table.'` drop foreign key `'.$constraintName.'`');
        }
    }
};
