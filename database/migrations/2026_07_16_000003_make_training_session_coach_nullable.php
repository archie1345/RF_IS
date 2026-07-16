<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_sessions') || ! Schema::hasColumn('training_sessions', 'coach_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver !== 'sqlite') {
            $this->dropForeignIfExists('training_sessions', ['coach_id']);
        }

        Schema::table('training_sessions', function (Blueprint $table): void {
            $table->ulid('coach_id')->nullable()->change();
        });

        if ($driver !== 'sqlite' && Schema::hasTable('coaches')) {
            Schema::table('training_sessions', function (Blueprint $table): void {
                $table->foreign('coach_id')->references('coach_id')->on('coaches')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('training_sessions') || ! Schema::hasColumn('training_sessions', 'coach_id')) {
            return;
        }

        if (DB::getDriverName() !== 'sqlite') {
            $this->dropForeignIfExists('training_sessions', ['coach_id']);

            Schema::table('training_sessions', function (Blueprint $table): void {
                $table->foreign('coach_id')->references('coach_id')->on('coaches')->nullOnDelete();
            });
        }
    }

    private function dropForeignIfExists(string $table, array $columns): void
    {
        try {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($columns));
        } catch (Throwable) {
        }
    }
};
