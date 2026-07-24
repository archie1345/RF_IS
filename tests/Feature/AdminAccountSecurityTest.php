<?php

use App\Models\User;
use App\Models\UserRoleAssignment;

function assignAdminRole(User $user): void
{
    UserRoleAssignment::query()->firstOrCreate([
        'user_id' => $user->id,
        'role' => 'admin',
    ]);
}

test('coach cannot promote themself to admin through account management', function () {
    $coach = User::factory()->create(['role' => 'coach']);

    $this->actingAs($coach)
        ->put(route('admin.accounts.update', $coach), [
            'name' => $coach->name,
            'email' => $coach->email,
            'roles' => ['admin', 'coach'],
            'status' => 'active',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertForbidden();

    expect($coach->fresh()->hasRole('admin'))->toBeFalse();
});

test('admin cannot remove their own admin role or suspend their own account', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    assignAdminRole($admin);

    $this->actingAs($admin)
        ->put(route('admin.accounts.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => ['athlete'],
            'status' => User::ACCOUNT_STATUS_ACTIVE,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertSessionHasErrors('account');

    $this->actingAs($admin)
        ->put(route('admin.accounts.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'roles' => ['admin'],
            'status' => User::ACCOUNT_STATUS_SUSPENDED,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertSessionHasErrors('account');

    expect($admin->fresh()->hasRole('admin'))->toBeTrue()
        ->and($admin->fresh()->account_status)->toBe(User::ACCOUNT_STATUS_ACTIVE);
});

test('last active admin cannot be suspended or deleted', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    assignAdminRole($admin);
    $otherAdmin = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
    ]);
    assignAdminRole($otherAdmin);

    $this->actingAs($admin)
        ->put(route('admin.accounts.update', $otherAdmin), [
            'name' => $otherAdmin->name,
            'email' => $otherAdmin->email,
            'roles' => ['athlete'],
            'status' => User::ACCOUNT_STATUS_SUSPENDED,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->delete(route('admin.accounts.destroy', $admin))
        ->assertSessionHasErrors('account');

    expect($admin->fresh())->not->toBeNull();
});

test('an admin account can be disabled when another active admin remains', function () {
    $firstAdmin = User::factory()->create(['role' => 'admin']);
    $secondAdmin = User::factory()->create(['role' => 'admin']);
    assignAdminRole($firstAdmin);
    assignAdminRole($secondAdmin);

    $this->actingAs($firstAdmin)
        ->put(route('admin.accounts.update', $secondAdmin), [
            'name' => $secondAdmin->name,
            'email' => $secondAdmin->email,
            'roles' => ['admin'],
            'status' => User::ACCOUNT_STATUS_SUSPENDED,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect();

    expect($secondAdmin->fresh()->account_status)->toBe(User::ACCOUNT_STATUS_SUSPENDED);
});
