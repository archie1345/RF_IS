<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->enum('result_medal', ['GOLD', 'SILVER', 'BRONZE', 'NONE'])->nullable()->after('status');
            $table->string('result_class_name', 120)->nullable()->after('result_medal');
            $table->string('result_division', 120)->nullable()->after('result_class_name');
            $table->string('result_category', 120)->nullable()->after('result_division');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations', function (Blueprint $table): void {
            $table->dropColumn(['result_medal', 'result_class_name', 'result_division', 'result_category']);
        });
    }
};

