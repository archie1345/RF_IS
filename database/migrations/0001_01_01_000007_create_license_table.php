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
        Schema::create('licenses', function (Blueprint $table) {
            $table->id('lid');
            $table->foreignId('id')->constrained('users')->onDelete('cascade');
            $table->string('license_number', 50)->unique();
            $table->enum('license_type', ['BELT', 'COACH', 'REFEREE', 'UNKNOWN'])->default('UNKNOWN')->index();
            $table->enum('level', ['GEUP_1', 'GEUP_2', 'GEUP_3', 'GEUP_4', 'GEUP_5', 'GEUP_6', 'GEUP_7', 'GEUP_8', 'GEUP_9', 'GEUP_10', 'DAN'])->default('GEUP_10')->index();
            $table->date('issued_date');
            $table->date('expiry_date')->nullable();
            $table->string('issued_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
