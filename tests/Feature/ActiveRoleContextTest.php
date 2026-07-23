<?php

use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\ParentProfile;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function activeRoleProfileData(User $user): array
{
    $branch = Branch::create([
        'branch_name' => 'Active Role Branch '.$user->id,
        'location' => 'Jakarta',
    ]);
    $group = Group::create([
        'group_name' => 'Active Role Group '.$user->id,
    ]);

    $athlete = Athlete::create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 170,
        'weight_kg' => 65,
        'nik_hash' => hash('sha256', 'active-role-nik-'.$user->id),
        'bpjs_hash' => hash('sha256', 'active-role-bpjs-'.$user->id),
        'geup' => 'GEUP_1',
    ]);

    return [$athlete, $branch, $group];
}

test('single-role pages expose one fixed active role and reject unavailable roles', function () {
    $user = User::factory()->create(['role' => 'coach']);
    Coach::create(['id' => $user->id, 'status' => 'active']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', 'coach')
            ->where('auth.user.activeRole', 'coach')
            ->where('auth.user.primaryRole', 'coach')
            ->where('auth.user.roles', ['coach'])
            ->where('auth.user.isMultiRole', false));

    $this->actingAs($user)
        ->put(route('role-context.update'), ['role' => 'athlete'])
        ->assertForbidden();
});

test('multi-role user can switch the global role used by every inertia page', function () {
    $user = User::factory()->create(['role' => 'coach']);
    Coach::create(['id' => $user->id, 'status' => 'active']);
    activeRoleProfileData($user);
    UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'coach']);
    UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'athlete']);

    $this->actingAs($user)
        ->put(route('role-context.update'), ['role' => 'athlete'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', 'athlete')
            ->where('auth.user.activeRole', 'athlete')
            ->where('auth.user.primaryRole', 'coach')
            ->where('auth.user.roles', ['coach', 'athlete'])
            ->where('auth.user.isMultiRole', true));

    $this->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('role', 'athlete')
            ->where('auth.user.activeRole', 'athlete'));

    $this->put(route('role-context.update'), ['role' => 'coach'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('attendance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('role', 'coach')
            ->where('auth.user.activeRole', 'coach')
            ->has('rows', 0));
});

test('admin capabilities are available only while the admin role is active', function () {
    $user = User::factory()->create(['role' => 'admin']);
    activeRoleProfileData($user);
    UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'admin']);
    UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'athlete']);

    $this->actingAs($user)
        ->put(route('role-context.update'), ['role' => 'athlete'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('admin.index'))->assertForbidden();

    $this->put(route('role-context.update'), ['role' => 'admin'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AdminPage')
            ->where('auth.user.activeRole', 'admin'));
});

test('parent child context is shared only while parent mode is active', function () {
    $user = User::factory()->create(['role' => 'parent']);
    $parent = ParentProfile::create(['id' => $user->id, 'relation' => 'guardian']);
    activeRoleProfileData($user);
    UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'parent']);
    UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'athlete']);

    $childUser = User::factory()->create(['name' => 'Linked Role Child', 'role' => 'athlete']);
    [$childAthlete] = activeRoleProfileData($childUser);
    $childAthlete->update(['parent_id' => $parent->parent_id]);

    $this->actingAs($user)
        ->put(route('role-context.update'), ['role' => 'athlete'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.activeRole', 'athlete')
            ->has('auth.children', 0)
            ->where('auth.activeChild', null));

    $this->put(route('role-context.update'), ['role' => 'parent'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.activeRole', 'parent')
            ->has('auth.children', 1)
            ->where('auth.children.0.name', 'Linked Role Child'));
});

test('role-targeted announcements follow the selected role instead of combining assignments', function () {
    $user = User::factory()->create(['role' => 'coach']);
    Coach::create(['id' => $user->id, 'status' => 'active']);
    activeRoleProfileData($user);
    UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'coach']);
    UserRoleAssignment::create(['user_id' => $user->id, 'role' => 'athlete']);

    Announcement::create([
        'created_by' => $user->id,
        'title' => 'Coach-only announcement',
        'message' => 'Coach content',
        'target_role' => 'COACH',
        'is_active' => true,
    ]);
    Announcement::create([
        'created_by' => $user->id,
        'title' => 'Athlete-only announcement',
        'message' => 'Athlete content',
        'target_role' => 'ATHLETE',
        'is_active' => true,
    ]);
    Announcement::create([
        'created_by' => $user->id,
        'title' => 'Shared announcement',
        'message' => 'Shared content',
        'target_role' => 'ALL',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->put(route('role-context.update'), ['role' => 'athlete'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('announcements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 2)
            ->where('rows.0.title', 'Shared announcement')
            ->where('rows.1.title', 'Athlete-only announcement'));

    $this->put(route('role-context.update'), ['role' => 'coach'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('announcements.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 2)
            ->where('rows.0.title', 'Shared announcement')
            ->where('rows.1.title', 'Coach-only announcement'));
});
