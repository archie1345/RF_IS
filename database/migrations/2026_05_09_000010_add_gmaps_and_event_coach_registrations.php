<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('gmaps_url', 255)->nullable()->after('location');
        });

        Schema::create('event_coach_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events', 'event_id')->cascadeOnDelete();
            $table->foreignId('coach_id')->constrained('coaches', 'coach_id')->cascadeOnDelete();
            $table->string('role', 120)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'coach_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_coach_registrations');

        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('gmaps_url');
        });
    }
};
