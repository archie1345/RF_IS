<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guardian_relationship', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_id')->constrained('users', 'id')->onDelete('cascade');
            $table->foreignUlid('athlete_id')->constrained('athletes', 'athlete_id')->onDelete('cascade');
            $table->enum('relation_type', ['parent', 'legal_guardian', 'authorized']);
            $table->dateTime('authorized_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_relationship');
    }
};
