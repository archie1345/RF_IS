<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->deduplicateByUserId('coaches', 'coach_id');
        $this->deduplicateByUserId('parents', 'parent_id');
        $this->deduplicateByUserId('athletes', 'athlete_id');

        Schema::table('coaches', function (Blueprint $table): void {
            $table->unique('id', 'coaches_user_id_unique');
            $table->index(['id', 'status'], 'coaches_user_status_idx');
        });

        Schema::table('parents', function (Blueprint $table): void {
            $table->unique('id', 'parents_user_id_unique');
            $table->index(['id', 'relation'], 'parents_user_relation_idx');
        });

        Schema::table('athletes', function (Blueprint $table): void {
            $table->unique('id', 'athletes_user_id_unique');
            $table->index(['parent_id', 'athlete_id'], 'athletes_parent_lookup_idx');
            $table->index(['branch_id', 'group_id'], 'athletes_branch_group_idx');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['athlete_id', 'status', 'payment_date'], 'payments_athlete_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_athlete_status_date_idx');
        });

        Schema::table('athletes', function (Blueprint $table): void {
            $table->dropIndex('athletes_parent_lookup_idx');
            $table->dropIndex('athletes_branch_group_idx');
            $table->dropUnique('athletes_user_id_unique');
        });

        Schema::table('parents', function (Blueprint $table): void {
            $table->dropIndex('parents_user_relation_idx');
            $table->dropUnique('parents_user_id_unique');
        });

        Schema::table('coaches', function (Blueprint $table): void {
            $table->dropIndex('coaches_user_status_idx');
            $table->dropUnique('coaches_user_id_unique');
        });
    }

    private function deduplicateByUserId(string $table, string $primaryKey): void
    {
        $duplicateIds = DB::table($table)
            ->select('id')
            ->groupBy('id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('id');

        foreach ($duplicateIds as $userId) {
            $keepId = DB::table($table)
                ->where('id', $userId)
                ->min($primaryKey);

            DB::table($table)
                ->where('id', $userId)
                ->where($primaryKey, '!=', $keepId)
                ->delete();
        }
    }
};

