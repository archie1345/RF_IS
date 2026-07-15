<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_registrations')) {
            Schema::table('event_registrations', function (Blueprint $table): void {
                if (! Schema::hasColumn('event_registrations', 'classification')) {
                    $table->string('classification')->nullable()->after('category');
                }
                if (! Schema::hasColumn('event_registrations', 'class_name')) {
                    $table->string('class_name')->nullable()->after('classification');
                }
                if (! Schema::hasColumn('event_registrations', 'division')) {
                    $table->string('division')->nullable()->after('class_name');
                }
                if (! Schema::hasColumn('event_registrations', 'team_contingent')) {
                    $table->string('team_contingent')->nullable()->default('Rhino Fighter')->after('division');
                }
                if (! Schema::hasColumn('event_registrations', 'result_medal')) {
                    $table->string('result_medal')->nullable()->after('status');
                }
                if (! Schema::hasColumn('event_registrations', 'result_class_name')) {
                    $table->string('result_class_name')->nullable()->after('result_medal');
                }
                if (! Schema::hasColumn('event_registrations', 'result_division')) {
                    $table->string('result_division')->nullable()->after('result_class_name');
                }
                if (! Schema::hasColumn('event_registrations', 'result_category')) {
                    $table->string('result_category')->nullable()->after('result_division');
                }
            });
        }

        if (Schema::hasTable('athletes')) {
            Schema::table('athletes', function (Blueprint $table): void {
                if (! Schema::hasColumn('athletes', 'school_origin')) {
                    $table->string('school_origin')->nullable()->after('alamat');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('event_registrations')) {
            Schema::table('event_registrations', function (Blueprint $table): void {
                foreach (['result_category', 'result_division', 'result_class_name', 'result_medal', 'team_contingent', 'division', 'class_name', 'classification'] as $column) {
                    if (Schema::hasColumn('event_registrations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
