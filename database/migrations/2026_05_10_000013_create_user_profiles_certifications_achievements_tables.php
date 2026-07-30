<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('profile_picture_path', 255)->nullable();
            $table->text('bio')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('user_certifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('cert_type', ['BELT', 'REFEREE', 'TRAINER'])->index();
            $table->string('title', 120);
            $table->string('issuer', 120)->nullable();
            $table->date('certified_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'cert_type']);
        });

        Schema::create('user_achievements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events', 'event_id')->nullOnDelete();
            $table->foreignId('event_registration_id')->nullable()->constrained('event_registrations', 'evrid')->nullOnDelete();
            $table->string('championship_name', 120);
            $table->enum('medal', ['GOLD', 'SILVER', 'BRONZE', 'NONE'])->default('NONE')->index();
            $table->string('location', 160)->nullable();
            $table->date('event_date')->nullable();
            $table->string('class_name', 120)->nullable();
            $table->string('division', 120)->nullable();
            $table->string('category', 120)->nullable();
            $table->boolean('is_auto_recorded')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'medal']);
            $table->unique('event_registration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('user_certifications');
        Schema::dropIfExists('user_profiles');
    }
};
