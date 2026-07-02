<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_attendance', function (Blueprint $table) {
            $table->id('coach_attendance_id');
            $table->foreignId('training_session_id')->constrained('training_sessions', 'training_session_id')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('coaches', 'coach_id')->cascadeOnDelete();
            $table->enum('status', ['TEACH', 'NOT_TEACH'])->default('TEACH');
            $table->dateTime('checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['training_session_id', 'coach_id'], 'coach_attendance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_attendance');
    }
};
