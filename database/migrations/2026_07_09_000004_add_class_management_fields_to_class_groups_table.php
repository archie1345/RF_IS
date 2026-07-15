<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_groups', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('group_id');
                $table->index('branch_id', 'class_groups_branch_id_index');
            }
            if (! Schema::hasColumn('class_groups', 'coach_id')) {
                $table->string('coach_id')->nullable()->after('branch_id');
                $table->index('coach_id', 'class_groups_coach_id_index');
            }
            if (! Schema::hasColumn('class_groups', 'class_type')) {
                $table->string('class_type')->default('Beginner')->after('group_name');
            }
            if (! Schema::hasColumn('class_groups', 'day_of_week')) {
                $table->unsignedTinyInteger('day_of_week')->default(1)->after('class_type');
            }
            if (! Schema::hasColumn('class_groups', 'start_time')) {
                $table->time('start_time')->nullable()->after('day_of_week');
            }
            if (! Schema::hasColumn('class_groups', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }
            if (! Schema::hasColumn('class_groups', 'capacity')) {
                $table->unsignedInteger('capacity')->default(20)->after('end_time');
            }
            if (! Schema::hasColumn('class_groups', 'min_belt')) {
                $table->string('min_belt')->nullable()->after('capacity');
            }
            if (! Schema::hasColumn('class_groups', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('min_belt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_groups', function (Blueprint $table): void {
            if (Schema::hasColumn('class_groups', 'branch_id')) {
                $table->dropIndex('class_groups_branch_id_index');
            }
            if (Schema::hasColumn('class_groups', 'coach_id')) {
                $table->dropIndex('class_groups_coach_id_index');
            }
            foreach (['branch_id', 'coach_id', 'class_type', 'day_of_week', 'start_time', 'end_time', 'capacity', 'min_belt', 'is_active'] as $column) {
                if (Schema::hasColumn('class_groups', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
