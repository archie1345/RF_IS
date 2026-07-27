<?php

use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin creates a paid payroll slip with basis and bonus', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $coach = User::factory()->create(['role' => 'coach', 'name' => 'Payroll Coach']);

    $this->actingAs($admin)
        ->post(route('admin.payroll.store'), [
            'coach_user_id' => $coach->id,
            'payroll_period' => now()->format('Y-m'),
            'basis_type' => 'SESSION',
            'units' => 12,
            'rate' => 100000,
            'base_amount' => 0,
            'bonus_amount' => 250000,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'TRANSFER',
            'notes' => 'Twelve sessions plus championship bonus',
        ])
        ->assertRedirect();

    $payment = Payment::query()->where('bill_kind', 'PAYROLL')->firstOrFail();

    expect($payment->payee_user_id)->toBe($coach->id)
        ->and($payment->invoice_number)->toStartWith('PAY-')
        ->and($payment->payroll_basis_type)->toBe('SESSION')
        ->and((float) $payment->payroll_units)->toBe(12.0)
        ->and((float) $payment->payroll_rate)->toBe(100000.0)
        ->and((float) $payment->payroll_base_amount)->toBe(1200000.0)
        ->and((float) $payment->payroll_bonus_amount)->toBe(250000.0)
        ->and((float) $payment->total_amount)->toBe(1450000.0)
        ->and((float) $payment->paid_amount)->toBe(1450000.0)
        ->and((float) $payment->remaining_amount)->toBe(0.0)
        ->and($payment->status)->toBe('COMPLETED');

    $this->assertDatabaseHas('payment_transactions', [
        'payment_id' => $payment->payment_id,
        'verified_by' => $admin->id,
        'transaction_type' => PaymentTransaction::TYPE_PAYMENT,
        'amount' => 1450000,
        'payment_method' => 'TRANSFER',
    ]);

    $this->actingAs($coach)
        ->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CoachPayrollPage')
            ->where('rows.0.payment_id', $payment->payment_id)
            ->where('rows.0.payroll_basis', 'Per sesi')
            ->where('rows.0.payroll_bonus', 'Rp250.000')
            ->where('rows.0.receipt_url', route('payments.export', $payment)));

    $this->actingAs($coach)
        ->get(route('payments.export', $payment))
        ->assertOk();
});

test('admin payroll page and dashboard remind admin until current month payroll exists', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.payroll.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/AdminPayrollPage')
            ->where('reminder.needed', true)
            ->where('reminder.count', 0));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles', ['admin'])
            ->where('metrics.0.value', 'Belum dibuat'));
});

test('non admin cannot create payroll', function () {
    $coach = User::factory()->create(['role' => 'coach']);

    $this->actingAs($coach)
        ->post(route('admin.payroll.store'), [
            'coach_user_id' => $coach->id,
            'payroll_period' => now()->format('Y-m'),
            'basis_type' => 'FIXED',
            'base_amount' => 1000000,
            'bonus_amount' => 0,
            'paid_at' => now()->toDateString(),
            'payment_method' => 'TRANSFER',
        ])
        ->assertForbidden();
});
