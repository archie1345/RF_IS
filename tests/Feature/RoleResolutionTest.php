<?php

use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Support\RoleResolver;

it('uses role assignments as the primary role source', function () {
    $user = User::factory()->create(['role' => 'athlete']);

    UserRoleAssignment::create([
        'user_id' => $user->id,
        'role' => 'coach',
    ]);

    expect($user->fresh()->assignedRoles())->toBe(['coach'])
        ->and($user->fresh()->primaryRole())->toBe('coach')
        ->and($user->fresh()->isCoach())->toBeTrue()
        ->and($user->fresh()->isAthlete())->toBeFalse();
});

it('falls back to users role when no assignment exists', function () {
    $user = User::factory()->create(['role' => 'coach']);

    expect(app(RoleResolver::class)->rolesFor($user))->toBe(['coach'])
        ->and($user->isCoach())->toBeTrue();
});
