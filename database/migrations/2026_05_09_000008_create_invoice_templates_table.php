<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('company_name', 150)->default('RF IS');
            $table->string('company_address', 255)->nullable();
            $table->string('company_phone', 60)->nullable();
            $table->string('company_email', 120)->nullable();
            $table->string('logo_url', 255)->nullable();
            $table->string('header_text', 255)->nullable();
            $table->text('footer_text')->nullable();
            $table->text('payment_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
