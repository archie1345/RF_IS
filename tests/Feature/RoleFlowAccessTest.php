<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('shared primary role uses assignments instead of a stale legacy role', function () {
    $user = User::factory()->create(['role' => 'athlete']);
    UserRoleAssignment::query()->create(['user_id' => $user->id, 'role' => 'parent']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', 'parent')
            ->where('auth.user.roles', ['parent']));
});

test('every role can reach its shared self service pages', function (string $role, array $routes) {
    $user = User::factory()->create(['role' => $role]);

    foreach ($routes as $routeName) {
        $this->actingAs($user)->get(route($routeName))->assertOk();
    }
})->with([
    'admin' => ['admin', ['dashboard', 'training-schedule.index', 'sessions.index', 'payments.index', 'attendance.index', 'announcements.index']],
    'coach' => ['coach', ['dashboard', 'training-schedule.index', 'sessions.index', 'payments.index', 'attendance.index', 'championships.index', 'achievements.index', 'announcements.index']],
    'parent' => ['parent', ['dashboard', 'training-schedule.index', 'parent.children.index', 'payments.index', 'attendance.index', 'championships.index', 'achievements.index', 'announcements.index']],
    'athlete' => ['athlete', ['dashboard', 'training-schedule.index', 'payments.index', 'attendance.index', 'championships.index', 'achievements.index', 'announcements.index']],
]);

test('the athlete directory and its sensitive record endpoint are admin only', function (string $role) {
    $viewer = User::factory()->create(['role' => $role]);
    $subject = User::factory()->create(['role' => 'athlete']);

    $this->actingAs($viewer)->get(route('users.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('users.athlete-record.show', $subject))->assertForbidden();
})->with(['coach', 'parent', 'athlete']);

test('non admins never receive activity log previews on their dashboard', function (string $role) {
    $admin = User::factory()->create(['role' => 'admin']);
    ActivityLog::query()->create([
        'actor_id' => $admin->id,
        'action' => 'account.updated',
        'context' => 'security',
        'description' => 'Sensitive administrative activity',
    ]);
    $viewer = User::factory()->create(['role' => $role]);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('activityPreviewRows', []));
})->with(['coach', 'parent', 'athlete']);
