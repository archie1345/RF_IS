<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_certifications', function (Blueprint $table): void {
            $table->foreignId('user_file_id')
                ->nullable()
                ->after('user_id')
                ->constrained('user_files')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_certifications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_file_id');
        });
    }
};
