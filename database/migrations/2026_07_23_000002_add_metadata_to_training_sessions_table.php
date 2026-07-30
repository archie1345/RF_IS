<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_sessions')) {
            return;
        }

        if (! Schema::hasColumn('training_sessions', 'metadata')) {
            Schema::table('training_sessions', function (Blueprint $table): void {
                $table->json('metadata')->nullable()->after('status');
            });
        }

        if (! Schema::hasTable('class_groups') || ! Schema::hasColumn('class_groups', 'schedule_mode')) {
            return;
        }

        $oneDayGroupIds = DB::table('class_groups')
            ->where('schedule_mode', 'one_day')
            ->pluck('group_id');

        if ($oneDayGroupIds->isEmpty()) {
            return;
        }

        $sessions = DB::table('training_sessions')
            ->whereIn('group_id', $oneDayGroupIds);

        if (Schema::hasColumn('training_sessions', 'weekly_training_schedule_id')) {
            $sessions->whereNull('weekly_training_schedule_id');
        }

        $sessions->update([
            'metadata' => json_encode(['class_schedule_mode' => 'one_day'], JSON_THROW_ON_ERROR),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('training_sessions') && Schema::hasColumn('training_sessions', 'metadata')) {
            Schema::table('training_sessions', function (Blueprint $table): void {
                $table->dropColumn('metadata');
            });
        }
    }
};
