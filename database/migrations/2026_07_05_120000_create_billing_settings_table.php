<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_settings')) {
            return;
        }

        Schema::create('billing_settings', function (Blueprint $table): void {
            $table->id('billing_setting_id');
            $table->string('name')->unique();
            $table->unsignedTinyInteger('invoice_day')->default(1);
            $table->time('invoice_time')->default('01:10:00');
            $table->decimal('default_amount', 12, 2)->default(150000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
