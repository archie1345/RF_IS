<?php

use App\Models\User;
use App\Models\UserRoleAssignment;
use Inertia\Testing\AssertableInertia as Assert;

test('multi-role user receives one combined dashboard for every assigned role', function () {
    $user = User::factory()->create(['role' => 'admin', 'name' => 'Combined User']);
    UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'admin']);
    UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'athlete']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('roles', ['admin', 'athlete'])
            ->where('auth.user.isMultiRole', true)
            ->where('metrics.0.label', fn (string $value): bool => str_starts_with($value, 'Admin · '))
            ->where('metrics.2.label', fn (string $value): bool => str_starts_with($value, 'Atlet · ')));
});
