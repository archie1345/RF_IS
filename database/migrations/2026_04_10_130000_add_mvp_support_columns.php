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
        Schema::table('coach_sessions', function (Blueprint $table) {
            $table->string('title', 150)->default('Training Session')->after('branch_id');
            $table->enum('status', ['DRAFT', 'CONFIRMED', 'NEEDS_ASSISTANT', 'CANCELED'])->default('DRAFT')->after('end_time');
        });

        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->foreignId('coach_session_id')->after('athlete_id')->constrained('coach_sessions', 'csid')->nullOnDelete();
            $table->dateTime('checked_in_at')->nullable()->after('status');
            $table->text('notes')->nullable()->after('checked_in_at');
            $table->string('follow_up_owner', 120)->nullable()->after('notes');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('max_slots')->default(24)->after('entry_fee');
        });

        Schema::table('event_registrations', function (Blueprint $table) {
            $table->string('division', 120)->nullable()->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table) {
            $table->dropColumn('division');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('max_slots');
        });

        Schema::table('athlete_attendance', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coach_session_id');
            $table->dropColumn(['checked_in_at', 'notes', 'follow_up_owner']);
        });

        Schema::table('coach_sessions', function (Blueprint $table) {
            $table->dropColumn(['title', 'status']);
        });
    }
};
