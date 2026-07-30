<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('training_sessions', 'session_type')) {
                $table->string('session_type', 40)->nullable()->after('group_id')->index();
            }

            if (! Schema::hasColumn('training_sessions', 'dedicated_athlete_id')) {
                $table->ulid('dedicated_athlete_id')->nullable()->after('session_type');
                $table->foreign('dedicated_athlete_id')
                    ->references('athlete_id')
                    ->on('athletes')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('training_sessions', 'dedicated_athlete_id')) {
                $table->dropForeign(['dedicated_athlete_id']);
                $table->dropColumn('dedicated_athlete_id');
            }

            if (Schema::hasColumn('training_sessions', 'session_type')) {
                $table->dropColumn('session_type');
            }
        });
    }
};
