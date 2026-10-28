<?php

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\CoachAttendance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\Domain\SessionStatus;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function sessionAuthorizationCoach(string $name): array
{
    $user = User::factory()->create([
        'name' => $name,
        'role' => 'coach',
        'email_verified_at' => now(),
    ]);
    $coach = Coach::query()->create([
        'id' => $user->id,
        'status' => 'active',
    ]);

    return [$user, $coach];
}

function sessionAuthorizationSession(
    Coach $coach,
    Branch $branch,
    Group $group,
    string $title,
    string $status,
): TrainingSession {
    return TrainingSession::query()->create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => $title,
        'location' => 'Authorization Dojang',
        'session_date' => today()->addDay()->toDateString(),
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
        'status' => $status,
    ]);
}

beforeEach(function () {
    Carbon::setTestNow('2026-07-24 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('does not compare UUID route values against numeric primary keys', function () {
    $uuid = '01a17bb6-b6ed-712f-9097-3b9930b33991';

    foreach ([Event::class, Attendance::class, CoachAttendance::class, TrainingSession::class, EventRegistration::class] as $modelClass) {
        $model = new $modelClass;
        $uuidQuery = $model->resolveRouteBindingQuery($modelClass::query(), $uuid);

        expect($uuidQuery->getBindings())->toBe([$uuid]);
    }
});

it('resolves a session UUID to its own attendance sheet and preserves numeric ID routes', function () {
    $branch = Branch::query()->create([
        'branch_name' => 'UUID Route Branch',
        'location' => 'Malang',
    ]);
    $group = Group::query()->create([
        'group_name' => 'UUID Route Group',
    ]);
    [, $coach] = sessionAuthorizationCoach('UUID Route Coach');
    $first = sessionAuthorizationSession($coach, $branch, $group, 'First Session', SessionStatus::CONFIRMED);
    $target = sessionAuthorizationSession($coach, $branch, $group, 'Target Session', SessionStatus::CONFIRMED);

    expect((new TrainingSession)->resolveRouteBinding($target->uuid)?->training_session_id)
        ->toBe($target->training_session_id);

    expect((new TrainingSession)->resolveRouteBinding((string) $target->training_session_id)?->training_session_id)
        ->toBe($target->training_session_id);

    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('sessions.attendance', $target->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SessionAttendancePage')
            ->where('session.id', $target->training_session_id)
            ->where('session.title', 'Target Session'));

    expect($first->training_session_id)->not->toBe($target->training_session_id);
});

it('shows coaches only managed sessions and open sessions needing assistance', function () {
    $branch = Branch::query()->create([
        'branch_name' => 'Session Authorization Branch',
        'location' => 'Malang',
    ]);
    $group = Group::query()->create([
        'group_name' => 'Session Authorization Group',
    ]);
    [$coachUser, $coach] = sessionAuthorizationCoach('Visible Coach');
    [, $otherCoach] = sessionAuthorizationCoach('Other Coach');

    $managed = sessionAuthorizationSession($coach, $branch, $group, 'Managed Session', SessionStatus::CONFIRMED);
    $joinable = sessionAuthorizationSession($otherCoach, $branch, $group, 'Joinable Session', SessionStatus::NEEDS_ASSISTANT);
    $hidden = sessionAuthorizationSession($otherCoach, $branch, $group, 'Hidden Confirmed Session', SessionStatus::CONFIRMED);

    $this->actingAs($coachUser)
        ->get(route('sessions.index', ['visibility' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SessionsPage')
            ->has('rows', 2)
            ->where('rows.0.session_id', $managed->training_session_id)
            ->where('rows.0.can_manage', true)
            ->where('rows.0.can_join', false)
            ->where('rows.1.session_id', $joinable->training_session_id)
            ->where('rows.1.can_manage', false)
            ->where('rows.1.can_join', true)
            ->where('rows', fn ($rows) => collect($rows)->doesntContain(
                fn (array $row): bool => (int) $row['session_id'] === (int) $hidden->training_session_id,
            ))
        );
});

it('allows a coach to join only a future session needing assistance', function () {
    $branch = Branch::query()->create([
        'branch_name' => 'Join Authorization Branch',
        'location' => 'Malang',
    ]);
    $group = Group::query()->create([
        'group_name' => 'Join Authorization Group',
    ]);
    [$coachUser, $coach] = sessionAuthorizationCoach('Joining Coach');
    [, $otherCoach] = sessionAuthorizationCoach('Primary Coach');

    $joinable = sessionAuthorizationSession($otherCoach, $branch, $group, 'Needs Help', SessionStatus::NEEDS_ASSISTANT);
    $confirmed = sessionAuthorizationSession($otherCoach, $branch, $group, 'Already Covered', SessionStatus::CONFIRMED);

    $this->actingAs($coachUser)
        ->post(route('sessions.join', $confirmed))
        ->assertForbidden();

    $this->actingAs($coachUser)
        ->post(route('sessions.join', $joinable))
        ->assertRedirect();

    $this->assertDatabaseHas('training_session_coaches', [
        'training_session_id' => $joinable->training_session_id,
        'coach_id' => $coach->coach_id,
    ]);
});

it('prevents unrelated coaches from changing another sessions coach attendance', function () {
    $branch = Branch::query()->create([
        'branch_name' => 'Attendance Authorization Branch',
        'location' => 'Malang',
    ]);
    $group = Group::query()->create([
        'group_name' => 'Attendance Authorization Group',
    ]);
    [, $sessionCoach] = sessionAuthorizationCoach('Session Coach');
    [$unrelatedUser] = sessionAuthorizationCoach('Unrelated Coach');
    $session = sessionAuthorizationSession($sessionCoach, $branch, $group, 'Protected Session', SessionStatus::CONFIRMED);
    $attendance = CoachAttendance::query()->create([
        'training_session_id' => $session->training_session_id,
        'coach_id' => $sessionCoach->coach_id,
        'status' => 'NOT_TEACH',
    ]);

    $this->actingAs($unrelatedUser)
        ->put(route('sessions.coach-attendance.update', $attendance), ['status' => 'TEACH'])
        ->assertForbidden();

    $this->actingAs($unrelatedUser)
        ->delete(route('sessions.coach-attendance.destroy', $attendance))
        ->assertForbidden();

    $this->assertDatabaseHas('coach_attendance', [
        'coach_attendance_id' => $attendance->coach_attendance_id,
        'status' => 'NOT_TEACH',
        'deleted_at' => null,
    ]);
});
