<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create an account with every supported role', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('admin.index'))
        ->post(route('admin.accounts.store'), [
            'name' => 'All Roles User',
            'email' => 'all-roles@example.com',
            'roles' => ['admin', 'coach', 'parent', 'athlete'],
            'status' => User::ACCOUNT_STATUS_ACTIVE,
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])
        ->assertRedirect(route('admin.index'));

    $user = User::query()->where('email', 'all-roles@example.com')->firstOrFail();

    expect($user->assignedRoles())->toBe(['admin', 'coach', 'parent', 'athlete'])
        ->and($user->role)->toBe('admin')
        ->and($user->coachProfile)->not->toBeNull()
        ->and($user->parentProfile)->not->toBeNull()
        ->and($user->athleteProfile)->toBeNull();
});

test('changing role combinations preserves athlete data and restores soft-deleted role profiles', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->from(route('admin.index'))
        ->post(route('admin.accounts.store'), [
            'name' => 'Combination User',
            'email' => 'combination@example.com',
            'roles' => ['coach', 'parent', 'athlete'],
            'status' => User::ACCOUNT_STATUS_ACTIVE,
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])
        ->assertRedirect(route('admin.index'));

    $user = User::query()->where('email', 'combination@example.com')->firstOrFail();
    $branch = Branch::query()->create([
        'branch_name' => 'Combination Branch',
        'location' => 'Jakarta',
    ]);
    $group = Group::query()->create([
        'group_name' => 'Combination Group',
    ]);
    $athlete = Athlete::query()->create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 170,
        'weight_kg' => 65,
        'nik_hash' => hash('sha256', 'combination-nik'),
        'bpjs_hash' => hash('sha256', 'combination-bpjs'),
        'geup' => 'GEUP_1',
    ]);
    $coachId = $user->coachProfile?->coach_id;
    $parentId = $user->parentProfile?->parent_id;

    $this->put(route('admin.accounts.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'roles' => ['parent', 'athlete'],
        'status' => User::ACCOUNT_STATUS_ACTIVE,
        'password' => null,
        'password_confirmation' => null,
    ])->assertRedirect(route('admin.index'));

    expect($user->fresh()->assignedRoles())->toBe(['parent', 'athlete'])
        ->and(Athlete::query()->find($athlete->athlete_id))->not->toBeNull()
        ->and(ParentProfile::query()->find($parentId))->not->toBeNull()
        ->and(Coach::withTrashed()->find($coachId)?->trashed())->toBeTrue();

    $this->put(route('admin.accounts.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'roles' => ['admin', 'coach', 'parent', 'athlete'],
        'status' => User::ACCOUNT_STATUS_ACTIVE,
        'password' => null,
        'password_confirmation' => null,
    ])->assertRedirect(route('admin.index'));

    expect($user->fresh()->assignedRoles())->toBe(['admin', 'coach', 'parent', 'athlete'])
        ->and(Athlete::query()->find($athlete->athlete_id))->not->toBeNull()
        ->and(ParentProfile::query()->find($parentId))->not->toBeNull()
        ->and(Coach::query()->find($coachId))->not->toBeNull();
});
