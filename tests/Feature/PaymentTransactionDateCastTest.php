<?php

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('casts transaction dates and renders admin finance metrics without a string date crash', function () {
    Carbon::setTestNow('2026-07-24 10:00:00');

    $admin = User::factory()->create(['role' => 'admin']);
    $member = User::factory()->create(['role' => 'athlete']);
    $payment = Payment::query()->create([
        'billable_user_id' => $member->id,
        'payer_user_id' => $member->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'TUITION',
        'amount' => 300000,
        'total_amount' => 300000,
        'paid_amount' => 100000,
        'remaining_amount' => 200000,
        'payment_date' => '2026-07-05',
        'status' => 'PENDING',
        'proof_status' => 'APPROVED',
    ]);

    PaymentTransaction::query()->create([
        'payment_id' => $payment->payment_id,
        'verified_by' => $admin->id,
        'amount' => 100000,
        'transaction_date' => '2026-07-06 09:30:00',
        'payment_method' => 'TRANSFER',
        'transaction_type' => PaymentTransaction::TYPE_PAYMENT,
    ]);

    $transaction = PaymentTransaction::query()->firstOrFail();

    expect($transaction->transaction_date)->toBeInstanceOf(Carbon::class)
        ->and($transaction->transaction_date->betweenIncluded(now()->startOfMonth(), now()->endOfMonth()))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('payments.index'))
        ->assertOk();

    Carbon::setTestNow();
});
