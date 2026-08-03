<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\ParentProfile;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\AttendanceVisibilityService;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function privateVisibilityAthlete(Branch $branch, Group $group, string $name): array
{
    $user = User::factory()->create([
        'name' => $name,
        'role' => 'athlete',
        'email_verified_at' => now(),
    ]);
    $athlete = Athlete::query()->create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'geup' => 'GEUP_5',
        'nik_hash' => hash('sha256', $name.'-nik'),
        'bpjs_hash' => hash('sha256', $name.'-bpjs'),
    ]);

    return [$user, $athlete];
}

it('does not expose another athletes dedicated session through visibility queries or dashboard data', function () {
    Carbon::setTestNow('2026-07-24 10:00:00');

    $branch = Branch::query()->create([
        'branch_name' => 'Private Visibility Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $group = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'group_name' => 'Private Visibility Group',
        'class_type' => 'reguler',
        'is_active' => true,
    ]);
    $coachUser = User::factory()->create(['role' => 'coach']);
    $coach = Coach::query()->create(['id' => $coachUser->id, 'status' => 'active']);
    [$dedicatedUser, $dedicatedAthlete] = privateVisibilityAthlete($branch, $group, 'Dedicated Dashboard Athlete');
    [$otherUser] = privateVisibilityAthlete($branch, $group, 'Other Dashboard Athlete');

    $session = TrainingSession::query()->create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => null,
        'session_type' => 'private',
        'dedicated_athlete_id' => $dedicatedAthlete->athlete_id,
        'title' => 'Secret Dedicated Session',
        'location' => 'Private Dojang',
        'session_date' => now()->addDay()->toDateString(),
        'start_time' => '16:00:00',
        'end_time' => '17:00:00',
        'status' => 'CONFIRMED',
    ]);

    expect(app(AttendanceVisibilityService::class)
        ->visibleSessionQuery($otherUser, 'athlete')
        ->whereKey($session->training_session_id)
        ->exists())->toBeFalse();

    $this->actingAs($otherUser)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('trainingDays', []));

    expect(app(AttendanceVisibilityService::class)
        ->visibleSessionQuery($dedicatedUser, 'athlete')
        ->whereKey($session->training_session_id)
        ->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('only exposes dedicated sessions belonging to a parents linked children', function () {
    $branch = Branch::query()->create([
        'branch_name' => 'Parent Private Visibility Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $group = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'group_name' => 'Parent Private Visibility Group',
        'class_type' => 'reguler',
        'is_active' => true,
    ]);
    $coachUser = User::factory()->create(['role' => 'coach']);
    $coach = Coach::query()->create(['id' => $coachUser->id, 'status' => 'active']);
    $parentUser = User::factory()->create([
        'role' => 'parent',
        'email_verified_at' => now(),
    ]);
    $parent = ParentProfile::query()->create([
        'id' => $parentUser->id,
        'relation' => 'guardian',
    ]);
    [, $linkedChild] = privateVisibilityAthlete($branch, $group, 'Linked Private Child');
    $linkedChild->update(['parent_id' => $parent->parent_id]);
    [, $unrelatedAthlete] = privateVisibilityAthlete($branch, $group, 'Unrelated Private Child');

    $linkedSession = TrainingSession::query()->create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'session_type' => 'private',
        'dedicated_athlete_id' => $linkedChild->athlete_id,
        'title' => 'Linked Child Private Session',
        'session_date' => today()->toDateString(),
        'start_time' => '16:00:00',
        'end_time' => '17:00:00',
        'status' => 'CONFIRMED',
    ]);
    $unrelatedSession = TrainingSession::query()->create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'session_type' => 'private',
        'dedicated_athlete_id' => $unrelatedAthlete->athlete_id,
        'title' => 'Unrelated Child Private Session',
        'session_date' => today()->toDateString(),
        'start_time' => '18:00:00',
        'end_time' => '19:00:00',
        'status' => 'CONFIRMED',
    ]);

    $visibleIds = app(AttendanceVisibilityService::class)
        ->visibleSessionQuery($parentUser, 'parent')
        ->pluck('training_session_id');

    expect($visibleIds)->toContain($linkedSession->training_session_id)
        ->not->toContain($unrelatedSession->training_session_id);
});
