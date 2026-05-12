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
        Schema::table('athletes', function (Blueprint $table) {
            $table->dropColumn(['nik_encrypted', 'bpjs_encrypted']);
            $table->string('nik_hash')->nullable()->change();
            $table->string('bpjs_hash')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('athletes', function (Blueprint $table) {
            $table->string('nik_encrypted')->nullable();
            $table->string('bpjs_encrypted')->nullable();
            $table->string('nik_hash')->nullable(false)->change();
            $table->string('bpjs_hash')->nullable(false)->change();
        });
    }
};
