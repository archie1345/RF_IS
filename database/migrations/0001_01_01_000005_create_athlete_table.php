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
        Schema::create('athletes', function (Blueprint $table) {
            $table->id('athlete_id');
            $table->foreignId('id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('class_groups', 'group_id')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('parents', 'parent_id')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches', 'branch_id')->cascadeOnDelete();
            $table->decimal('height_cm', 5, 2);
            $table->decimal('weight_kg', 5, 2);
            $table->string('nik_hash', 64)->index();
            $table->string('bpjs_hash', 64)->index();
            $table->text('alamat')->nullable();
            $table->enum('geup', ['GEUP_1', 'GEUP_2', 'GEUP_3', 'GEUP_4', 'GEUP_5', 'GEUP_6', 'GEUP_7', 'GEUP_8', 'GEUP_9', 'GEUP_10', 'DAN'])->default('GEUP_10')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('athlete_attendance', function (Blueprint $table) {
            $table->id('atid');
            $table->foreignId('athlete_id')->constrained('athletes', 'athlete_id')->cascadeOnDelete();
            $table->date('date')->index();
            $table->enum('status', ['PRESENT', 'ABSENT', 'EXCUSED'])->default('ABSENT')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('athlete_attendance');
        Schema::dropIfExists('athletes');
    }
};
