<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable()->index();
            $table->timestamp('invalidated_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'accepted_at', 'invalidated_at'], 'user_invitations_active_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
