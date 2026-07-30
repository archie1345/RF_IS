<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table): void {
            $table->boolean('qris_enabled')->default(true)->after('payment_notes');
            $table->string('qris_label', 150)->default('Pembayaran QRIS')->after('qris_enabled');
            $table->text('qris_instructions')->nullable()->after('qris_label');
            $table->string('qris_image_path', 255)->nullable()->after('qris_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table): void {
            $table->dropColumn([
                'qris_enabled',
                'qris_label',
                'qris_instructions',
                'qris_image_path',
            ]);
        });
    }
};
