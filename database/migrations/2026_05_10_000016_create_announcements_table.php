<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->text('message');
            $table->enum('target_role', ['ALL', 'ADMIN', 'COACH', 'PARENT', 'ATHLETE'])->default('ALL')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('publish_at')->nullable()->index();
            $table->timestamp('expire_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};

