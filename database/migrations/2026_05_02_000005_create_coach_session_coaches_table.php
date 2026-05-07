<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_session_coaches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_session_id')->constrained('coach_sessions', 'csid')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('coaches', 'coach_id')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['coach_session_id', 'coach_id'], 'coach_session_coach_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_session_coaches');
    }
};

