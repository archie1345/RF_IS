<?php

namespace Database\Seeders;

use App\Models\InvoiceTemplate;
use Illuminate\Database\Seeder;

class PaymentConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        InvoiceTemplate::query()->updateOrCreate(
            ['name' => 'default'],
            [
                'company_name' => 'RF IS',
                'header_text' => 'Invoice Pembayaran',
                'payment_notes' => 'Bayar menggunakan metode yang disepakati, lalu unggah bukti yang jelas untuk direview admin.',
                'qris_enabled' => true,
                'qris_label' => 'Pembayaran QRIS',
                'qris_instructions' => 'Pindai QRIS resmi, bayar sesuai sisa tagihan, lalu unggah bukti pembayaran untuk direview admin.',
            ],
        );
    }
}
