<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_group_private_athletes')) {
            Schema::create('class_group_private_athletes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->ulid('athlete_id');
                $table->timestamps();

                $table->foreign('group_id')->references('group_id')->on('class_groups')->cascadeOnDelete();
                $table->foreign('athlete_id')->references('athlete_id')->on('athletes')->cascadeOnDelete();
                $table->unique(['group_id', 'athlete_id'], 'class_group_private_athletes_unique');
            });
        }

        if (Schema::hasColumn('class_groups', 'dedicated_athlete_id')) {
            DB::table('class_groups')
                ->whereNotNull('dedicated_athlete_id')
                ->orderBy('group_id')
                ->select(['group_id', 'dedicated_athlete_id'])
                ->chunkById(100, function ($groups): void {
                    foreach ($groups as $group) {
                        DB::table('class_group_private_athletes')->updateOrInsert(
                            [
                                'group_id' => $group->group_id,
                                'athlete_id' => $group->dedicated_athlete_id,
                            ],
                            [
                                'created_at' => now(),
                                'updated_at' => now(),
                            ],
                        );
                    }
                }, 'group_id');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('class_group_private_athletes');
    }
};
