<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches') || Schema::hasColumn('branches', 'google_maps_url')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table): void {
            $table->text('google_maps_url')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('branches') || ! Schema::hasColumn('branches', 'google_maps_url')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('google_maps_url');
        });
    }
};
