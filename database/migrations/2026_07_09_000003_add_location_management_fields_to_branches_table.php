<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            if (! Schema::hasColumn('branches', 'address')) {
                $table->text('address')->nullable()->after('location');
            }
            if (! Schema::hasColumn('branches', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (! Schema::hasColumn('branches', 'province')) {
                $table->string('province')->nullable()->after('city');
            }
            if (! Schema::hasColumn('branches', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('province');
            }
            if (! Schema::hasColumn('branches', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('branches', 'attendance_radius_meters')) {
                $table->unsignedInteger('attendance_radius_meters')->default(100)->after('longitude');
            }
            if (! Schema::hasColumn('branches', 'timezone')) {
                $table->string('timezone')->nullable()->after('attendance_radius_meters');
            }
            if (! Schema::hasColumn('branches', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('timezone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            foreach (['address', 'city', 'province', 'latitude', 'longitude', 'attendance_radius_meters', 'timezone', 'is_active'] as $column) {
                if (Schema::hasColumn('branches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
