<?php

use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Support\RoleResolver;

it('uses role assignments as the primary role source', function () {
    $user = User::factory()->create(['role' => 'athlete']);

    UserRoleAssignment::create([
        'user_id' => $user->id,
        'role' => 'parent',
    ]);

    expect($user->fresh()->assignedRoles())->toBe(['parent'])
        ->and($user->fresh()->primaryRole())->toBe('parent')
        ->and($user->fresh()->isParent())->toBeTrue()
        ->and($user->fresh()->isAthlete())->toBeFalse();
});

it('falls back to users role when no assignment exists', function () {
    $user = User::factory()->create(['role' => 'coach']);

    expect(app(RoleResolver::class)->rolesFor($user))->toBe(['coach'])
        ->and($user->isCoach())->toBeTrue();
});
