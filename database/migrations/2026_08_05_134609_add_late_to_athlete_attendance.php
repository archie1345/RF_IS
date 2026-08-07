<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->string('status', 20)->default('ABSENT')->change();
        });
    }

    public function down(): void
    {
        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->enum('status', ['PRESENT', 'ABSENT', 'EXCUSED'])->default('ABSENT')->change();
        });
    }
};