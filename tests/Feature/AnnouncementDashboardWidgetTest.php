<?php

use App\Models\Announcement;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows dashboard announcements for every assigned role on a multi-role account', function () {
    $user = User::factory()->create(['role' => 'coach']);
    UserRoleAssignment::query()->create(['user_id' => $user->id, 'role' => 'coach']);
    UserRoleAssignment::query()->create(['user_id' => $user->id, 'role' => 'athlete']);
    Coach::query()->create(['id' => $user->id, 'status' => 'active']);

    $branch = Branch::query()->create([
        'branch_name' => 'Announcement Widget Branch',
        'location' => 'Test Hall',
    ]);
    $group = Group::query()->create([
        'group_name' => 'Announcement Widget Group',
        'branch_id' => $branch->branch_id,
    ]);
    Athlete::query()->create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 170,
        'weight_kg' => 65,
        'nik_hash' => hash('sha256', 'announcement-widget-nik'),
        'bpjs_hash' => hash('sha256', 'announcement-widget-bpjs'),
        'geup' => 'GEUP_1',
    ]);

    foreach ([
        ['Shared widget', 'ALL'],
        ['Coach widget', 'COACH'],
        ['Athlete widget', 'ATHLETE'],
    ] as [$title, $target]) {
        Announcement::query()->create([
            'created_by' => $user->id,
            'title' => $title,
            'message' => $title.' content',
            'target_role' => $target,
            'is_active' => true,
            'publish_at' => now()->subMinute(),
        ]);
    }

    $this->actingAs($user)
        ->put(route('role-context.update'), ['role' => 'athlete'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.activeRole', 'athlete')
            ->has('announcements', 3)
            ->where('announcements.0.title', 'Athlete widget')
            ->where('announcements.0.target', 'Atlet')
            ->where('announcements.1.title', 'Coach widget')
            ->where('announcements.1.target', 'Pelatih')
            ->where('announcements.2.title', 'Shared widget'));

    $this->put(route('role-context.update'), ['role' => 'coach'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.activeRole', 'coach')
            ->has('announcements', 3)
            ->where('announcements.0.title', 'Athlete widget')
            ->where('announcements.1.title', 'Coach widget')
            ->where('announcements.2.title', 'Shared widget'));
});
