<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table) {
            $table->id('coach_id');
            $table->foreignId('id')->constrained('users')->onDelete('cascade');
            $table->string('specialization', 255)->nullable();
            $table->text('bio')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id('training_session_id');
            $table->foreignId('coach_id')->constrained('coaches', 'coach_id')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches', 'branch_id')->cascadeOnDelete();
            $table->string('location', 255)->nullable();
            $table->date('session_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
        Schema::dropIfExists('coaches');
    }
};
