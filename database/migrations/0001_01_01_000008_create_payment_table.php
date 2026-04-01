<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id('payment_id');
            $table->foreignId('athlete_id')->constrained('athletes', 'athlete_id')->cascadeOnDelete();
            $table->enum('payment_type', ['TUITION', 'UNIFORM', 'LICENSE','CHAMPIONSHIP','OTHER','UNKNOWN'])->default('UNKNOWN')->index();
            $table->decimal('amount', 10, 2);
            $table->bigInteger('reference_id')->nullable()->unique();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('paid_amount', 10, 2)->nullable();
            $table->decimal('remaining_amount', 10, 2)->nullable();
            $table->date('payment_date');
            $table->enum('status', ['PENDING', 'COMPLETED', 'FAILED', 'REFUNDED'])->default('PENDING')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id('ptid');
            $table->foreignId('payid')->constrained('payments', 'payment_id')->cascadeOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users', 'id')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('transaction_date');
            $table->enum('payment_method', ['CASH', 'CARD', 'TRANSFER', 'OTHER'])->default('OTHER')->index();
            $table->enum('transaction_type', ['PAYMENT', 'REFUND'])->default('PAYMENT')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payments');
    }
};
