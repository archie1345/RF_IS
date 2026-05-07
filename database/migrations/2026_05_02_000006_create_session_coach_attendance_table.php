<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_coach_attendance', function (Blueprint $table) {
            $table->id('scaid');
            $table->foreignId('coach_session_id')->constrained('coach_sessions', 'csid')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('coaches', 'coach_id')->cascadeOnDelete();
            $table->enum('status', ['TEACH', 'NOT_TEACH'])->default('TEACH');
            $table->dateTime('checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['coach_session_id', 'coach_id'], 'session_coach_attendance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_coach_attendance');
    }
};

