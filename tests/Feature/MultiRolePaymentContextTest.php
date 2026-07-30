<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('coach athlete account sees tuition and payroll only in their matching active roles', function () {
    $branch = Branch::query()->create([
        'branch_name' => 'Payment Context Branch',
        'location' => 'Jakarta',
    ]);
    $group = Group::query()->create([
        'group_name' => 'Payment Context Group',
    ]);
    $user = User::factory()->create([
        'name' => 'Coach Athlete Payment User',
        'role' => 'coach',
    ]);
    $coach = Coach::query()->create([
        'id' => $user->id,
        'status' => 'active',
    ]);
    $athlete = Athlete::query()->create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 170,
        'weight_kg' => 65,
        'nik_hash' => hash('sha256', 'payment-context-nik'),
        'bpjs_hash' => hash('sha256', 'payment-context-bpjs'),
        'geup' => 'GEUP_1',
    ]);
    UserRoleAssignment::query()->create(['user_id' => $user->id, 'role' => 'coach']);
    UserRoleAssignment::query()->create(['user_id' => $user->id, 'role' => 'athlete']);

    $tuition = Payment::query()->create([
        'athlete_id' => $athlete->athlete_id,
        'billable_user_id' => $user->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'TUITION',
        'amount' => 250000,
        'total_amount' => 250000,
        'paid_amount' => 0,
        'remaining_amount' => 250000,
        'payment_date' => now()->toDateString(),
        'status' => 'PENDING',
    ]);
    $payroll = Payment::query()->create([
        'payee_user_id' => $user->id,
        'bill_kind' => 'PAYROLL',
        'payment_type' => 'OTHER',
        'amount' => 500000,
        'total_amount' => 500000,
        'paid_amount' => 200000,
        'remaining_amount' => 300000,
        'payment_date' => now()->toDateString(),
        'status' => 'PARTIAL',
    ]);

    $this->actingAs($user)
        ->put(route('role-context.update'), ['role' => 'athlete'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PaymentsPage')
            ->where('auth.user.activeRole', 'athlete')
            ->where('isAdmin', false)
            ->where('canSubmitPaymentProof', true)
            ->has('rows', 1)
            ->where('rows.0.payment_id', $tuition->payment_id)
            ->where('rows.0.bill_kind', 'INVOICE')
            ->has('athletes', 0)
            ->has('users', 0)
            ->has('coaches', 0)
            ->where('invoiceTemplate', null));

    $this->put(route('role-context.update'), ['role' => 'coach'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CoachPayrollPage')
            ->where('auth.user.activeRole', 'coach')
            ->has('rows', 1)
            ->where('rows.0.payment_id', $payroll->payment_id)
            ->where('rows.0.bill_kind', 'PAYROLL'));

    expect($coach->coach_id)->not->toBeNull();
});

test('admin payment page keeps management options while non-admin pages receive none', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $member = User::factory()->create([
        'name' => 'Payment Option Member',
        'role' => 'coach',
    ]);
    Coach::query()->create([
        'id' => $member->id,
        'status' => 'active',
    ]);
    UserRoleAssignment::query()->create(['user_id' => $member->id, 'role' => 'coach']);

    $this->actingAs($admin)
        ->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PaymentsPage')
            ->where('isAdmin', true)
            ->where('canSubmitPaymentProof', false)
            ->has('users', 2)
            ->has('coaches', 1));
});
