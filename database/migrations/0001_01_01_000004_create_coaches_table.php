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
            $table->string('c_name', 100);
            $table->string('c_phone', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coach_sessions', function (Blueprint $table) {
            $table->id('csid');
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
        Schema::dropIfExists('coach_sessions');
        Schema::dropIfExists('coaches');
    }
};
