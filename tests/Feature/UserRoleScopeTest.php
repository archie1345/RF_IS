<?php

use App\Models\User;
use App\Models\UserRoleAssignment;

it('uses the legacy role when no role assignments exist', function () {
    $coach = User::factory()->create(['role' => 'COACH']);
    User::factory()->create(['role' => 'athlete']);

    expect(User::query()->withRole('coach')->pluck('id')->all())
        ->toBe([$coach->id]);
});

it('uses explicit role assignments instead of the legacy role', function () {
    $user = User::factory()->create(['role' => 'admin']);
    UserRoleAssignment::query()->create([
        'user_id' => $user->id,
        'role' => 'athlete',
    ]);

    expect(User::query()->withRole('admin')->whereKey($user)->exists())->toBeFalse();
    expect(User::query()->withRole('athlete')->whereKey($user)->exists())->toBeTrue();
});

it('returns multi-role users for every assigned role', function () {
    $user = User::factory()->create(['role' => 'athlete']);
    UserRoleAssignment::query()->insert([
        ['user_id' => $user->id, 'role' => 'athlete', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $user->id, 'role' => 'coach', 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(User::query()->withRole('athlete')->whereKey($user)->exists())->toBeTrue();
    expect(User::query()->withRole('coach')->whereKey($user)->exists())->toBeTrue();
});

it('returns no users for an unsupported role', function () {
    User::factory()->create(['role' => 'admin']);

    expect(User::query()->withRole('super-admin')->exists())->toBeFalse();
});
