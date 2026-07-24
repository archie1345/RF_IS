<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ApplicationDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            FreshApplicationSeeder::class,
            AllRoleDemoSeeder::class,
            PaymentConfigurationSeeder::class,
            FinanceLedgerDemoSeeder::class,
            MemberNumberSeeder::class,
        ]);
    }
}
