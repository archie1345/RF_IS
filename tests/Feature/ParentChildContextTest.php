<?php

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Group;
use App\Models\ParentProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('keeps legacy child switching available for bookmarked actions', function () {
    $parentUser = User::factory()->create([
        'name' => 'Parent User',
        'email' => 'parent@example.com',
        'gender' => 'FEMALE',
        'role' => 'parent',
    ]);

    $parentProfile = ParentProfile::create([
        'id' => $parentUser->id,
        'relation' => 'mother',
    ]);

    $branch = Branch::create(['branch_name' => 'Jakarta', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => 'Group A']);

    $childUser = User::factory()->create([
        'name' => 'Child User',
        'email' => 'child@example.com',
        'gender' => 'MALE',
        'role' => 'athlete',
    ]);

    $childAthlete = Athlete::create([
        'id' => $childUser->id,
        'group_id' => $group->group_id,
        'parent_id' => $parentProfile->parent_id,
        'branch_id' => $branch->branch_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'nik_hash' => hash('sha256', 'nik'),
        'bpjs_hash' => hash('sha256', 'bpjs'),
        'alamat' => null,
        'geup' => 'GEUP_1',
    ]);

    $this->actingAs($parentUser)
        ->get(route('parent.children.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ParentChildSwitcherPage')
            ->where('children.0.athlete_id', $childAthlete->athlete_id)
            ->where('children.0.user_id', $childUser->id)
            ->where('auth.activeChild', null));

    $this->actingAs($parentUser)
        ->post(route('parent.children.switch'), ['athlete_id' => $childAthlete->athlete_id])
        ->assertRedirect();

    expect(session('active_child_id'))->toBe($childAthlete->athlete_id);
});

it('prevents a parent from switching to another parent child context', function () {
    $parentUser = User::factory()->create([
        'name' => 'Parent User',
        'email' => 'parent2@example.com',
        'gender' => 'FEMALE',
        'role' => 'parent',
    ]);

    ParentProfile::create([
        'id' => $parentUser->id,
        'relation' => 'mother',
    ]);

    $otherParentUser = User::factory()->create([
        'name' => 'Other Parent User',
        'email' => 'other-parent@example.com',
        'gender' => 'MALE',
        'role' => 'parent',
    ]);

    $otherParentProfile = ParentProfile::create([
        'id' => $otherParentUser->id,
        'relation' => 'father',
    ]);

    $branch = Branch::create(['branch_name' => 'Bandung', 'location' => 'Bandung']);
    $group = Group::create(['group_name' => 'Group B']);

    $childUser = User::factory()->create([
        'name' => 'Other Child User',
        'email' => 'other-child@example.com',
        'gender' => 'MALE',
        'role' => 'athlete',
    ]);

    $otherChildAthlete = Athlete::create([
        'id' => $childUser->id,
        'group_id' => $group->group_id,
        'parent_id' => $otherParentProfile->parent_id,
        'branch_id' => $branch->branch_id,
        'height_cm' => 145,
        'weight_kg' => 42,
        'nik_hash' => hash('sha256', 'nik-2'),
        'bpjs_hash' => hash('sha256', 'bpjs-2'),
        'alamat' => null,
        'geup' => 'GEUP_2',
    ]);

    $this->actingAs($parentUser)
        ->post(route('parent.children.switch'), ['athlete_id' => $otherChildAthlete->athlete_id])
        ->assertForbidden();
});

it('shows every linked child attendance even when a legacy active child remains in session', function () {
    $parentUser = User::factory()->create(['role' => 'parent']);
    $parentProfile = ParentProfile::create(['id' => $parentUser->id, 'relation' => 'guardian']);
    $branch = Branch::create(['branch_name' => 'String Branch', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => 'String Group']);

    $firstChildUser = User::factory()->create(['name' => 'Visible Child', 'role' => 'athlete']);
    $secondChildUser = User::factory()->create(['name' => 'Second Child', 'role' => 'athlete']);

    $firstAthlete = Athlete::create([
        'id' => $firstChildUser->id,
        'group_id' => $group->group_id,
        'parent_id' => $parentProfile->parent_id,
        'branch_id' => $branch->branch_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'nik_hash' => hash('sha256', 'visible-nik'),
        'bpjs_hash' => hash('sha256', 'visible-bpjs'),
        'geup' => 'GEUP_1',
    ]);

    $secondAthlete = Athlete::create([
        'id' => $secondChildUser->id,
        'group_id' => $group->group_id,
        'parent_id' => $parentProfile->parent_id,
        'branch_id' => $branch->branch_id,
        'height_cm' => 151,
        'weight_kg' => 46,
        'nik_hash' => hash('sha256', 'hidden-nik'),
        'bpjs_hash' => hash('sha256', 'hidden-bpjs'),
        'geup' => 'GEUP_2',
    ]);

    Attendance::create([
        'athlete_id' => $firstAthlete->athlete_id,
        'date' => now()->toDateString(),
        'status' => 'PRESENT',
    ]);

    Attendance::create([
        'athlete_id' => $secondAthlete->athlete_id,
        'date' => now()->toDateString(),
        'status' => 'ABSENT',
    ]);

    $this->actingAs($parentUser)
        ->withSession(['active_child_id' => $firstAthlete->athlete_id])
        ->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AttendancePage')
            ->has('rows', 2)
            ->where('rows.0.athlete', 'Second Child')
            ->where('rows.1.athlete', 'Visible Child')
            ->where('auth.activeChild', null));
});
