<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->enum('charge_kind', ['MONTHLY', 'ONE_TIME'])->index();
            $table->enum('payment_type', ['TUITION', 'UNIFORM', 'LICENSE', 'CHAMPIONSHIP', 'OTHER'])
                ->default('TUITION')
                ->index();
            $table->decimal('amount', 12, 2);
            $table->foreignId('branch_id')->nullable()->constrained('branches', 'branch_id')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('class_groups', 'group_id')->nullOnDelete();
            $table->unsignedSmallInteger('due_days')->default(14);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['charge_kind', 'branch_id', 'group_id'], 'billing_rules_scope_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('billing_rule_id')
                ->nullable()
                ->after('reference_id')
                ->constrained('billing_rules')
                ->nullOnDelete();
            $table->string('billing_run_key', 191)->nullable()->after('billing_rule_id')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['billing_rule_id']);
            $table->dropUnique(['billing_run_key']);
            $table->dropColumn(['billing_rule_id', 'billing_run_key']);
        });

        Schema::dropIfExists('billing_rules');
    }
};
