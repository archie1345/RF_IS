<?php

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Parents;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('allows a parent to switch to their own child context', function () {
    $parentUser = User::create([
        'name' => 'Parent User',
        'email' => 'parent@example.com',
        'password' => 'password',
        'gender' => 'FEMALE',
        'role' => 'parent',
    ]);

    $parentProfile = Parents::create([
        'id' => $parentUser->id,
        'relation' => 'mother',
    ]);

    $branch = Branch::create(['branch_name' => 'Jakarta', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => 'Group A']);

    $childUser = User::create([
        'name' => 'Child User',
        'email' => 'child@example.com',
        'password' => 'password',
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
            ->where('children.0.user_id', $childUser->id));

    $this->actingAs($parentUser)
        ->post(route('parent.children.switch', $childAthlete))
        ->assertRedirect();

    expect(session('active_child_id'))->toBe($childAthlete->athlete_id);
});

it('prevents a parent from switching to another parent child context', function () {
    $parentUser = User::create([
        'name' => 'Parent User',
        'email' => 'parent2@example.com',
        'password' => 'password',
        'gender' => 'FEMALE',
        'role' => 'parent',
    ]);

    Parents::create([
        'id' => $parentUser->id,
        'relation' => 'mother',
    ]);

    $otherParentUser = User::create([
        'name' => 'Other Parent User',
        'email' => 'other-parent@example.com',
        'password' => 'password',
        'gender' => 'MALE',
        'role' => 'parent',
    ]);

    $otherParentProfile = Parents::create([
        'id' => $otherParentUser->id,
        'relation' => 'father',
    ]);

    $branch = Branch::create(['branch_name' => 'Bandung', 'location' => 'Bandung']);
    $group = Group::create(['group_name' => 'Group B']);

    $childUser = User::create([
        'name' => 'Other Child User',
        'email' => 'other-child@example.com',
        'password' => 'password',
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
        ->post(route('parent.children.switch', $otherChildAthlete))
        ->assertForbidden();
});

it('filters parent attendance by string active child identifiers', function () {
    $parentUser = User::factory()->create(['role' => 'parent']);
    $parentProfile = Parents::create(['id' => $parentUser->id, 'relation' => 'guardian']);
    $branch = Branch::create(['branch_name' => 'String Branch', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => 'String Group']);

    $firstChildUser = User::factory()->create(['name' => 'Visible Child', 'role' => 'athlete']);
    $secondChildUser = User::factory()->create(['name' => 'Hidden Child', 'role' => 'athlete']);

    $visibleAthlete = Athlete::create([
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

    $hiddenAthlete = Athlete::create([
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
        'athlete_id' => $visibleAthlete->athlete_id,
        'date' => now()->toDateString(),
        'status' => 'PRESENT',
    ]);

    Attendance::create([
        'athlete_id' => $hiddenAthlete->athlete_id,
        'date' => now()->toDateString(),
        'status' => 'ABSENT',
    ]);

    $this->actingAs($parentUser)
        ->withSession(['active_child_id' => $visibleAthlete->athlete_id])
        ->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AttendancePage')
            ->where('rows.0.athlete', 'Visible Child')
            ->missing('rows.1'));
});
