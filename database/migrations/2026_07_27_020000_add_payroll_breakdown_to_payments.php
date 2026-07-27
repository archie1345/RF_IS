<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->date('payroll_period')->nullable()->after('billing_run_key')->index();
            $table->string('payroll_basis_type', 40)->nullable()->after('payroll_period');
            $table->decimal('payroll_units', 10, 2)->nullable()->after('payroll_basis_type');
            $table->decimal('payroll_rate', 14, 2)->nullable()->after('payroll_units');
            $table->decimal('payroll_base_amount', 14, 2)->nullable()->after('payroll_rate');
            $table->decimal('payroll_bonus_amount', 14, 2)->default(0)->after('payroll_base_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['payroll_period']);
            $table->dropColumn([
                'payroll_period',
                'payroll_basis_type',
                'payroll_units',
                'payroll_rate',
                'payroll_base_amount',
                'payroll_bonus_amount',
            ]);
        });
    }
};
