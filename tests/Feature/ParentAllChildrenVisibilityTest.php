<?php

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Group;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createLinkedParentChild(ParentProfile $parent, Branch $branch, Group $group, string $name): array
{
    $user = User::factory()->create(['name' => $name, 'role' => 'athlete']);
    $athlete = Athlete::create([
        'id' => $user->id,
        'parent_id' => $parent->parent_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'nik_hash' => hash('sha256', $name.'-parent-nik'),
        'bpjs_hash' => hash('sha256', $name.'-parent-bpjs'),
        'geup' => 'GEUP_3',
    ]);

    return [$user, $athlete];
}

test('parent pages show every linked child and no persistent active child', function () {
    $parentUser = User::factory()->create(['name' => 'Family Parent', 'role' => 'parent']);
    $parent = ParentProfile::create(['id' => $parentUser->id, 'relation' => 'guardian']);
    $branch = Branch::create(['branch_name' => 'Family Branch', 'location' => 'Jakarta']);
    $group = Group::create(['branch_id' => $branch->branch_id, 'group_name' => 'Family Group']);
    [$firstUser, $firstAthlete] = createLinkedParentChild($parent, $branch, $group, 'Child A');
    [$secondUser, $secondAthlete] = createLinkedParentChild($parent, $branch, $group, 'Child B');

    Attendance::create(['athlete_id' => $firstAthlete->athlete_id, 'date' => today(), 'status' => 'PRESENT']);
    Attendance::create(['athlete_id' => $secondAthlete->athlete_id, 'date' => today(), 'status' => 'ABSENT']);
    Payment::create([
        'athlete_id' => $firstAthlete->athlete_id,
        'billable_user_id' => $firstUser->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'TUITION',
        'amount' => 100000,
        'total_amount' => 100000,
        'paid_amount' => 0,
        'remaining_amount' => 100000,
        'status' => 'PENDING',
    ]);
    Payment::create([
        'athlete_id' => $secondAthlete->athlete_id,
        'billable_user_id' => $secondUser->id,
        'bill_kind' => 'INVOICE',
        'payment_type' => 'TUITION',
        'amount' => 150000,
        'total_amount' => 150000,
        'paid_amount' => 0,
        'remaining_amount' => 150000,
        'status' => 'PENDING',
    ]);

    $this->actingAs($parentUser)
        ->withSession(['active_child_id' => $firstAthlete->athlete_id])
        ->get(route('parent.children.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ParentChildSwitcherPage')
            ->has('children', 2)
            ->where('children.0.name', 'Child A')
            ->where('children.1.name', 'Child B')
            ->where('auth.activeChild', null));

    $this->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('role', 'parent')
            ->has('rows', 2));

    $this->get(route('payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PaymentsPage')
            ->has('rows', 2));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles', ['parent'])
            ->has('auth.children', 2)
            ->where('auth.activeChild', null)
            ->where('metrics.0.value', '2'));
});
