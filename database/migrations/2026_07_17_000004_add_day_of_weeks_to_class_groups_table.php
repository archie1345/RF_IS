<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_groups')) {
            return;
        }

        Schema::table('class_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_groups', 'day_of_weeks')) {
                $table->json('day_of_weeks')->nullable()->after('day_of_week');
            }
        });

        DB::table('class_groups')
            ->whereNotNull('day_of_week')
            ->orderBy('group_id')
            ->select(['group_id', 'day_of_week'])
            ->chunkById(100, function ($groups): void {
                foreach ($groups as $group) {
                    DB::table('class_groups')
                        ->where('group_id', $group->group_id)
                        ->update(['day_of_weeks' => json_encode([(int) $group->day_of_week])]);
                }
            }, 'group_id');
    }

    public function down(): void
    {
        if (! Schema::hasTable('class_groups') || ! Schema::hasColumn('class_groups', 'day_of_weeks')) {
            return;
        }

        Schema::table('class_groups', function (Blueprint $table): void {
            $table->dropColumn('day_of_weeks');
        });
    }
};
