<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_files', function (Blueprint $table): void {
            $table->string('disk', 32)->default('public')->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('user_files', function (Blueprint $table): void {
            $table->dropColumn('disk');
        });
    }
};
