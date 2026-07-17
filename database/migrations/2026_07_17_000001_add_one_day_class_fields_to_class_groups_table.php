<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_groups', 'schedule_mode')) {
                $table->string('schedule_mode', 20)->default('weekly')->after('class_type');
            }

            if (! Schema::hasColumn('class_groups', 'single_session_date')) {
                $table->date('single_session_date')->nullable()->after('schedule_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('class_groups', function (Blueprint $table): void {
            foreach (['single_session_date', 'schedule_mode'] as $column) {
                if (Schema::hasColumn('class_groups', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
