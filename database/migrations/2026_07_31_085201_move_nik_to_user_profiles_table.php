<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add NIK & BPJS columns to user_profiles for ALL roles
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('nik_hash', 64)->nullable()->index();
            $table->text('nik_ciphertext')->nullable();
            $table->string('bpjs_hash', 64)->nullable()->index();
            $table->text('bpjs_ciphertext')->nullable();
            $table->text('address')->nullable();
        });

        // 2. Backfill existing Athlete data using Foreign Key (athletes.id -> users.id)
        if (Schema::hasTable('athletes')) {
            $athletes = DB::table('athletes')->get();
            foreach ($athletes as $athlete) {
                DB::table('user_profiles')->updateOrInsert(
                    ['user_id' => $athlete->id],
                    [
                        'nik_hash' => $athlete->nik_hash ?? null,
                        'nik_ciphertext' => $athlete->nik_ciphertext ?? null,
                        'bpjs_hash' => $athlete->bpjs_hash ?? null,
                        'bpjs_ciphertext' => $athlete->bpjs_ciphertext ?? null,
                        'address' => $athlete->alamat ?? null,
                        'updated_at' => now(),
                    ]
                );
            }

            // 3. Drop migrated columns from athletes
            Schema::table('athletes', function (Blueprint $table) {
                $columns = array_filter(['nik_hash', 'nik_ciphertext', 'bpjs_hash', 'bpjs_ciphertext', 'alamat'], fn ($col) => Schema::hasColumn('athletes', $col));
                if (! empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['nik_hash', 'nik_ciphertext', 'bpjs_hash', 'bpjs_ciphertext', 'address']);
        });
    }
};