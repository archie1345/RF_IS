<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            if (! Schema::hasColumn('athletes', 'school_origin')) {
                $table->string('school_origin')->nullable()->after('alamat');
            }
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('event_registrations', 'classification')) {
                $table->string('classification')->nullable()->after('category');
            }
            if (! Schema::hasColumn('event_registrations', 'class_name')) {
                $table->string('class_name')->nullable()->after('classification');
            }
            if (! Schema::hasColumn('event_registrations', 'team_contingent')) {
                $table->string('team_contingent')->nullable()->default('rhino fighter')->after('division');
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            foreach (['team_contingent', 'class_name', 'classification'] as $column) {
                if (Schema::hasColumn('event_registrations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('athletes', function (Blueprint $table) {
            if (Schema::hasColumn('athletes', 'school_origin')) {
                $table->dropColumn('school_origin');
            }
        });
    }
};
