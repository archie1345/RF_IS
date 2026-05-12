<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            if (! Schema::hasColumn('athletes', 'nik_ciphertext')) {
                $table->text('nik_ciphertext')->nullable()->after('nik_hash');
            }

            if (! Schema::hasColumn('athletes', 'bpjs_ciphertext')) {
                $table->text('bpjs_ciphertext')->nullable()->after('bpjs_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            if (Schema::hasColumn('athletes', 'nik_ciphertext')) {
                $table->dropColumn('nik_ciphertext');
            }

            if (Schema::hasColumn('athletes', 'bpjs_ciphertext')) {
                $table->dropColumn('bpjs_ciphertext');
            }
        });
    }
};
