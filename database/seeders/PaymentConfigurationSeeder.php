<?php

namespace Database\Seeders;

use App\Models\BillingRule;
use App\Models\BillingSetting;
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

        BillingSetting::query()->updateOrCreate(
            ['name' => 'monthly_tuition'],
            [
                'invoice_day' => 1,
                'invoice_time' => '01:10:00',
                'default_amount' => 150000,
                'is_active' => true,
            ],
        );

        BillingRule::query()
            ->where('charge_kind', BillingRule::KIND_MONTHLY)
            ->whereNull('branch_id')
            ->whereNull('group_id')
            ->update(['is_active' => false]);

        BillingRule::query()->updateOrCreate(
            ['name' => 'Seragam latihan'],
            [
                'charge_kind' => BillingRule::KIND_ONE_TIME,
                'payment_type' => 'UNIFORM',
                'amount' => 350000,
                'branch_id' => null,
                'group_id' => null,
                'due_days' => 14,
                'effective_from' => null,
                'effective_until' => null,
                'is_active' => true,
                'notes' => 'Template tagihan satu kali. Admin tetap harus menekan Terbitkan tagihan sebelum invoice dibuat.',
            ],
        );
    }
}
