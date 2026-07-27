<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_templates') || Schema::hasColumn('invoice_templates', 'logo_path')) {
            return;
        }

        Schema::table('invoice_templates', function (Blueprint $table): void {
            $table->string('logo_path', 255)->nullable()->after('logo_url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_templates') || ! Schema::hasColumn('invoice_templates', 'logo_path')) {
            return;
        }

        Schema::table('invoice_templates', function (Blueprint $table): void {
            $table->dropColumn('logo_path');
        });
    }
};
