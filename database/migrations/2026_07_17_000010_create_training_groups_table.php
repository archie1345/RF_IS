<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('training_groups')) {
            Schema::create('training_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('training_groups') && DB::table('training_groups')->count() === 0) {
            DB::table('training_groups')->insert([
                ['name' => 'Umum', 'description' => 'Kategori umum untuk kelas reguler.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Junior', 'description' => 'Kategori atlet junior.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Prestasi', 'description' => 'Kategori atlet prestasi dan kompetisi.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Private', 'description' => 'Kategori kelas privat.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        Schema::table('class_groups', function (Blueprint $table): void {
            if (! Schema::hasColumn('class_groups', 'training_group_id')) {
                $table->foreignId('training_group_id')->nullable()->after('branch_id')->constrained('training_groups')->nullOnDelete();
            }
        });

        Schema::table('athletes', function (Blueprint $table): void {
            if (! Schema::hasColumn('athletes', 'training_group_id')) {
                $table->foreignId('training_group_id')->nullable()->after('group_id')->constrained('training_groups')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('athletes', function (Blueprint $table): void {
            if (Schema::hasColumn('athletes', 'training_group_id')) {
                $table->dropConstrainedForeignId('training_group_id');
            }
        });

        Schema::table('class_groups', function (Blueprint $table): void {
            if (Schema::hasColumn('class_groups', 'training_group_id')) {
                $table->dropConstrainedForeignId('training_group_id');
            }
        });

        Schema::dropIfExists('training_groups');
    }
};
