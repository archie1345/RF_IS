<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_groups', 'dedicated_athlete_id')) {
                $table->ulid('dedicated_athlete_id')->nullable()->after('coach_id');
                $table->foreign('dedicated_athlete_id')->references('athlete_id')->on('athletes')->nullOnDelete();
            }
        });

        if (Schema::hasTable('training_groups')) {
            DB::table('training_groups')
                ->whereRaw('LOWER(name) = ?', ['private'])
                ->where('classes_count', null);

            DB::table('training_groups')
                ->whereRaw('LOWER(name) = ?', ['private'])
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('class_groups')
                        ->whereColumn('class_groups.training_group_id', 'training_groups.id');
                })
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('athletes')
                        ->whereColumn('athletes.training_group_id', 'training_groups.id');
                })
                ->update(['is_active' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('class_groups', function (Blueprint $table): void {
            if (Schema::hasColumn('class_groups', 'dedicated_athlete_id')) {
                $table->dropForeign(['dedicated_athlete_id']);
                $table->dropColumn('dedicated_athlete_id');
            }
        });
    }
};
