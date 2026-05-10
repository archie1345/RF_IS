<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('billable_user_id')->nullable()->after('athlete_id')->constrained('users')->nullOnDelete();
            $table->foreignId('payee_user_id')->nullable()->after('billable_user_id')->constrained('users')->nullOnDelete();
            $table->string('bill_kind', 40)->default('INVOICE')->after('payee_user_id')->index();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('athlete_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('athlete_id')->nullable(false)->change();
            $table->dropConstrainedForeignId('billable_user_id');
            $table->dropConstrainedForeignId('payee_user_id');
            $table->dropColumn('bill_kind');
        });
    }
};
