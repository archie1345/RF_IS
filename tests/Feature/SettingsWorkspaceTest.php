<?php

use App\Models\User;
use App\Models\UserRoleAssignment;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated user can open the settings overview and profile workspace', function () {
    $user = User::factory()->create([
        'name' => 'Settings User',
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Overview')
            ->where('account.name', 'Settings User')
            ->where('account.active_role', 'athlete')
            ->where('account.roles.0', 'athlete'));

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ProfileWorkspace')
            ->where('user.name', 'Settings User')
            ->where('accountUpdateUrl', '/settings/profile')
            ->has('branches')
            ->has('groups'));
});

test('settings overview exposes selected active role for a multi role account', function () {
    $user = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    UserRoleAssignment::query()->create(['user_id' => $user->id, 'role' => 'admin']);
    UserRoleAssignment::query()->create(['user_id' => $user->id, 'role' => 'athlete']);

    $this->actingAs($user)
        ->withSession(['active_role' => 'athlete'])
        ->get(route('settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Overview')
            ->where('account.active_role', 'athlete')
            ->where('account.is_multi_role', true)
            ->has('account.roles', 2));
});
