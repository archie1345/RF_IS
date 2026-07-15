<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_training_schedules')) {
            Schema::create('weekly_training_schedules', function (Blueprint $table): void {
                $table->id('weekly_training_schedule_id');
                $table->string('title', 150);
                $table->unsignedBigInteger('branch_id');
                $table->unsignedBigInteger('group_id')->nullable();
                $table->string('coach_id')->nullable();
                $table->unsignedTinyInteger('day_of_week');
                $table->time('start_time');
                $table->time('end_time');
                $table->string('location')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['is_active', 'day_of_week']);
                $table->index(['branch_id', 'group_id']);
                $table->unique(
                    ['branch_id', 'group_id', 'day_of_week', 'start_time', 'end_time'],
                    'weekly_training_unique_window'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_training_schedules');
    }
};
