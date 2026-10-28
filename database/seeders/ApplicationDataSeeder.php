<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\GuardsDemoSeeding;
use Illuminate\Database\Seeder;

class ApplicationDataSeeder extends Seeder
{
    use GuardsDemoSeeding;

    public function run(): void
    {
        $this->guardDemoSeeding();

        $this->call([
            FreshApplicationSeeder::class,
            AllRoleDemoSeeder::class,
            PaymentConfigurationSeeder::class,
            FinanceLedgerDemoSeeder::class,
            MemberNumberSeeder::class,
        ]);
    }
}
