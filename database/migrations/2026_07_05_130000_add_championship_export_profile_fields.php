<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('athletes') && ! Schema::hasColumn('athletes', 'school_origin')) {
            Schema::table('athletes', function (Blueprint $table): void {
                $table->string('school_origin')->nullable();
            });
        }

        if (! Schema::hasTable('event_registrations')) {
            return;
        }

        Schema::table('event_registrations', function (Blueprint $table): void {
            if (! Schema::hasColumn('event_registrations', 'classification')) {
                $table->string('classification', 120)->nullable();
            }

            if (! Schema::hasColumn('event_registrations', 'division')) {
                $table->string('division', 120)->nullable();
            }

            if (! Schema::hasColumn('event_registrations', 'class_name')) {
                $table->string('class_name', 120)->nullable();
            }

            if (! Schema::hasColumn('event_registrations', 'team_contingent')) {
                $table->string('team_contingent', 120)->nullable()->default('rhino fighter');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('event_registrations')) {
            $columns = collect(['team_contingent', 'class_name', 'division', 'classification'])
                ->filter(fn (string $column): bool => Schema::hasColumn('event_registrations', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                Schema::table('event_registrations', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }

        if (Schema::hasTable('athletes') && Schema::hasColumn('athletes', 'school_origin')) {
            Schema::table('athletes', function (Blueprint $table): void {
                $table->dropColumn('school_origin');
            });
        }
    }
};
