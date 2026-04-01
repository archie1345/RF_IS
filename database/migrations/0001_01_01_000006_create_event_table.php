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
        Schema::create('events', function (Blueprint $table) {
            $table->id('event_id');
            $table->string('e_name', 100);
            $table->date('date')->index();
            $table->string('location', 255)->nullable();
            $table->enum('level', ['LOCAL', 'REGIONAL', 'NATIONAL', 'INTERNATIONAL'])->default('LOCAL')->index();
            $table->decimal('entry_fee', 10, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->string('organizer', 255)->nullable();
            $table->string('contact_info', 255)->nullable();
            $table->string('sponsor', 255)->nullable();
            $table->enum('status', ['SCHEDULED', 'ONGOING', 'COMPLETED', 'CANCELED'])->default('SCHEDULED')->index();
            $table->string('poster_url', 255)->nullable();
            $table->timestamps();
            $table->softdeletes();
        });

        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id('evrid');
            $table->foreignId('athlete_id')->constrained('athletes', 'athlete_id')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events', 'event_id')->cascadeOnDelete();
            $table->enum('category', ['KYORUGI','POOMSAE','FREESTYLE','UNKNOWN'])->default('UNKNOWN')->index();
            $table->timestamp('registered_at')->useCurrent();
            $table->enum('status', ['REGISTERED', 'CANCELED','PENDING','CONFIRMED'])->default('PENDING')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_results', function (Blueprint $table) {
            $table->id('evrid');
            $table->foreignId('athlete_id')->constrained('athletes', 'athlete_id')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events', 'event_id')->cascadeOnDelete();
            $table->enum('result', ['GOLD', 'SILVER', 'BRONZE', 'PARTICIPATED'])->default('PARTICIPATED')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_results');
        Schema::dropIfExists('events');
    }
};
