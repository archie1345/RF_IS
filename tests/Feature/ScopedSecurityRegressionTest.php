<?php

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Event;
use App\Models\EventCoachRegistration;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\AdminDataExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function securityRegressionCoach(string $name): array
{
    $user = User::factory()->create([
        'name' => $name,
        'role' => 'coach',
        'email_verified_at' => now(),
    ]);
    $coach = Coach::query()->create(['id' => $user->id, 'status' => 'active']);

    return [$user, $coach];
}

function securityRegressionBranch(string $name): Branch
{
    return Branch::query()->create(['branch_name' => $name, 'location' => 'Jakarta']);
}

function securityRegressionAthlete(string $name, Group $group, Branch $branch): Athlete
{
    $user = User::factory()->create(['name' => $name, 'role' => 'athlete']);

    return Athlete::query()->create([
        'id' => $user->id,
        'group_id' => $group->group_id,
        'branch_id' => $branch->branch_id,
        'height_cm' => 160,
        'weight_kg' => 50,
        'geup' => 'GEUP_1',
    ]);
}

it('limits a coach class page to assigned classes and their athletes', function () {
    [$coachUser, $coach] = securityRegressionCoach('Scoped Coach');
    $branch = securityRegressionBranch('Scoped Class Branch');
    $assignedGroup = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'coach_id' => $coach->coach_id,
        'group_name' => 'Assigned Class',
        'class_type' => 'reguler',
        'is_active' => true,
    ]);
    $unassignedGroup = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'group_name' => 'Unassigned Class',
        'class_type' => 'reguler',
        'is_active' => true,
    ]);
    $assignedAthlete = securityRegressionAthlete('Assigned Athlete', $assignedGroup, $branch);
    securityRegressionAthlete('Unassigned Athlete', $unassignedGroup, $branch);

    $this->actingAs($coachUser)
        ->get(route('admin.classes'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('classes', 1)
            ->where('classes.0.id', $assignedGroup->group_id)
            ->has('athleteOptions', 1)
            ->where('athleteOptions.0.value', $assignedAthlete->athlete_id));
});

it('rejects coach session creation for an unassigned class but allows an assigned class', function () {
    [$coachUser, $coach] = securityRegressionCoach('Session Scope Coach');
    $branch = securityRegressionBranch('Session Scope Branch');
    $assignedGroup = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'coach_id' => $coach->coach_id,
        'group_name' => 'Assigned Session Class',
        'is_active' => true,
    ]);
    $unassignedGroup = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'group_name' => 'Unassigned Session Class',
        'is_active' => true,
    ]);
    $payload = [
        'title' => 'Scoped Session',
        'branch_id' => $branch->branch_id,
        'session_date' => today()->addDay()->toDateString(),
        'start_time' => '16:00',
        'end_time' => '17:00',
        'status' => 'CONFIRMED',
    ];

    $response = $this->actingAs($coachUser)
        ->post(route('sessions.store'), [...$payload, 'group_id' => $unassignedGroup->group_id]);
    expect(TrainingSession::query()->where('title', 'Scoped Session')->exists())->toBeFalse();
    $response->assertForbidden();

    $this->actingAs($coachUser)
        ->post(route('sessions.store'), [...$payload, 'group_id' => $assignedGroup->group_id])
        ->assertRedirect();

    $this->assertDatabaseHas('training_sessions', [
        'title' => 'Scoped Session',
        'group_id' => $assignedGroup->group_id,
        'coach_id' => $coach->coach_id,
    ]);
});

it('rejects coach manual attendance for athletes ineligible for the session', function () {
    [$coachUser, $coach] = securityRegressionCoach('Attendance Scope Coach');
    $branch = securityRegressionBranch('Attendance Scope Branch');
    $sessionGroup = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'coach_id' => $coach->coach_id,
        'group_name' => 'Session Athlete Class',
        'is_active' => true,
    ]);
    $otherGroup = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'group_name' => 'Other Athlete Class',
        'is_active' => true,
    ]);
    $athlete = securityRegressionAthlete('Out Of Scope Athlete', $otherGroup, $branch);
    $session = TrainingSession::query()->create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $sessionGroup->group_id,
        'title' => 'Attendance Scope Session',
        'session_date' => today()->toDateString(),
        'start_time' => '16:00',
        'end_time' => '18:00',
        'status' => 'CONFIRMED',
    ]);

    $response = $this->actingAs($coachUser)
        ->post(route('attendance.store'), [
            'athlete_id' => $athlete->athlete_id,
            'training_session_id' => $session->training_session_id,
            'date' => today()->toDateString(),
            'status' => 'PRESENT',
        ]);
    expect(Attendance::query()
        ->where('athlete_id', $athlete->athlete_id)
        ->where('training_session_id', $session->training_session_id)
        ->exists())->toBeFalse();
    $response->assertSessionHasErrors('athlete_id');

    $this->assertDatabaseMissing('athlete_attendance', [
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $session->training_session_id,
    ]);

    $eligibleAthlete = securityRegressionAthlete('Eligible Session Athlete', $sessionGroup, $branch);
    $this->actingAs($coachUser)
        ->post(route('attendance.store'), [
            'athlete_id' => $eligibleAthlete->athlete_id,
            'training_session_id' => $session->training_session_id,
            'date' => today()->toDateString(),
            'status' => 'PRESENT',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $eligibleAthlete->athlete_id,
        'training_session_id' => $session->training_session_id,
        'status' => 'PRESENT',
    ]);
});

it('requires event assignment before a coach can use event coach management', function () {
    [$coachUser, $coach] = securityRegressionCoach('Unassigned Event Coach');
    $event = Event::query()->create([
        'e_name' => 'Coach Scope Event',
        'e_date' => now()->addMonth()->toDateString(),
        'registration_deadline' => now()->addWeek(),
        'location' => 'Jakarta',
        'level' => 'NATIONAL',
        'entry_fee' => 200000,
        'max_slots' => 20,
        'status' => 'SCHEDULED',
    ]);

    $this->actingAs($coachUser)
        ->post(route('championships.coaches.store', $event))
        ->assertForbidden();

    $this->assertDatabaseMissing('event_coach_registrations', [
        'event_id' => $event->event_id,
        'coach_id' => $coach->coach_id,
    ]);

    EventCoachRegistration::query()->create([
        'event_id' => $event->event_id,
        'coach_id' => $coach->coach_id,
        'role' => 'Assistant',
    ]);

    $this->actingAs($coachUser)
        ->post(route('championships.coaches.store', $event), ['role' => 'Head coach'])
        ->assertRedirect();

    $this->assertDatabaseHas('event_coach_registrations', [
        'event_id' => $event->event_id,
        'coach_id' => $coach->coach_id,
        'role' => 'Head coach',
    ]);
});

it('escapes formula-like values in combined spreadsheet exports', function () {
    User::factory()->create(['name' => " \t=HYPERLINK(\"https://example.invalid\",\"open\")"]);

    $export = app(AdminDataExportService::class)->makeCombinedExport(
        ['accounts'],
        ['accounts' => ['name']],
        [],
    );

    expect($export->array()[0][1])->toBe("' \t=HYPERLINK(\"https://example.invalid\",\"open\")");
});
