<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('class_group_coaches')) {
            Schema::create('class_group_coaches', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('group_id');
                $table->ulid('coach_id');
                $table->timestamps();

                $table->foreign('group_id')->references('group_id')->on('class_groups')->cascadeOnDelete();
                $table->foreign('coach_id')->references('coach_id')->on('coaches')->cascadeOnDelete();
                $table->unique(['group_id', 'coach_id'], 'class_group_coaches_unique');
            });
        }

        if (Schema::hasColumn('class_groups', 'coach_id')) {
            DB::table('class_groups')
                ->whereNotNull('coach_id')
                ->orderBy('group_id')
                ->select(['group_id', 'coach_id'])
                ->chunkById(100, function ($groups): void {
                    foreach ($groups as $group) {
                        DB::table('class_group_coaches')->updateOrInsert(
                            [
                                'group_id' => $group->group_id,
                                'coach_id' => $group->coach_id,
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
        Schema::dropIfExists('class_group_coaches');
    }
};
