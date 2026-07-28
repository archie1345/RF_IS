<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('admin can mark an account active or not active', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $member = User::factory()->create([
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.accounts.status.update', $member), [
            'status' => User::ACCOUNT_STATUS_SUSPENDED,
        ])
        ->assertRedirect();

    expect($member->refresh()->isSuspended())->toBeTrue();

    auth()->logout();
    $this->post(route('login'), [
        'email' => $member->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->actingAs($admin)
        ->put(route('admin.accounts.status.update', $member), [
            'status' => User::ACCOUNT_STATUS_ACTIVE,
        ])
        ->assertRedirect();

    expect($member->refresh()->isActiveAccount())->toBeTrue();
});

test('non admins cannot change account active state', function () {
    $athlete = User::factory()->create([
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $target = User::factory()->create([
        'role' => 'coach',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($athlete)
        ->put(route('admin.accounts.status.update', $target), [
            'status' => User::ACCOUNT_STATUS_SUSPENDED,
        ])
        ->assertForbidden();

    expect($target->refresh()->isActiveAccount())->toBeTrue();
});

test('admin cannot mark their own account as not active', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->put(route('admin.accounts.status.update', $admin), [
            'status' => User::ACCOUNT_STATUS_SUSPENDED,
        ])
        ->assertSessionHasErrors('account');

    expect($admin->refresh()->isActiveAccount())->toBeTrue();
});
