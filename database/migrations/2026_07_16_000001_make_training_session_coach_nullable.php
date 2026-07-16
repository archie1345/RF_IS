<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('training_sessions', 'coach_id')) {
            return;
        }

        $this->dropForeignIfExists('training_sessions', 'training_sessions_coach_id_foreign');

        Schema::table('training_sessions', function (Blueprint $table): void {
            $table->char('coach_id', 26)->nullable()->change();
        });

        Schema::table('training_sessions', function (Blueprint $table): void {
            $table->foreign('coach_id')
                ->references('coach_id')
                ->on('coaches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('training_sessions', 'coach_id')) {
            return;
        }

        $this->dropForeignIfExists('training_sessions', 'training_sessions_coach_id_foreign');
    }

    private function dropForeignIfExists(string $table, string $foreign): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreign)
            ->exists();

        if ($exists) {
            Schema::table($table, fn (Blueprint $table) => $table->dropForeign($foreign));
        }
    }
};
