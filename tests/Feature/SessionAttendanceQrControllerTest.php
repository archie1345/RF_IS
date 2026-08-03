<?php

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Carbon;

function createAttendanceQrScenario(): array
{
    $branch = Branch::create(['branch_name' => 'Attendance Branch', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => 'Attendance Group', 'branch_id' => $branch->branch_id]);
    $coachUser = User::factory()->create(['role' => 'coach']);
    $coach = Coach::create(['id' => $coachUser->id, 'status' => 'active']);
    $athleteUser = User::factory()->create(['role' => 'athlete']);
    $athlete = Athlete::create([
        'id' => $athleteUser->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'geup' => 'GEUP_1',
        'nik_hash' => hash('sha256', 'nik'),
        'bpjs_hash' => hash('sha256', 'bpjs'),
    ]);
    $trainingSession = TrainingSession::create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'session_date' => '2026-07-02',
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'status' => 'CONFIRMED',
    ]);

    return compact('branch', 'group', 'coachUser', 'athlete', 'trainingSession');
}

function createAthleteForAttendance(Branch $branch, Group $group): Athlete
{
    $athleteUser = User::factory()->create(['role' => 'athlete']);
    $uniqueValue = (string) $athleteUser->id;

    return Athlete::create([
        'id' => $athleteUser->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'geup' => 'GEUP_1',
        'nik_hash' => hash('sha256', 'nik-'.$uniqueValue),
        'bpjs_hash' => hash('sha256', 'bpjs-'.$uniqueValue),
    ]);
}

beforeEach(function () {
    Carbon::setTestNow('2026-07-02 09:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('initializes attendance for eligible athletes when opening a session QR', function () {
    [
        'coachUser' => $coachUser,
        'athlete' => $athlete,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $this->actingAs($coachUser)
        ->post(route('sessions.attendance-qr.store', ['session' => $trainingSession]))
        ->assertRedirect()
        ->assertSessionHas('attendanceQr');

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
        'status' => 'ABSENT',
    ]);
});

it('does not duplicate athlete attendance when reopening the session QR', function () {
    [
        'coachUser' => $coachUser,
        'athlete' => $athlete,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();
    $route = route('sessions.attendance-qr.store', ['session' => $trainingSession]);

    $this->actingAs($coachUser)->post($route)->assertRedirect();
    $this->actingAs($coachUser)->post($route)->assertRedirect();

    expect(Attendance::query()
        ->where('athlete_id', $athlete->athlete_id)
        ->where('training_session_id', $trainingSession->training_session_id)
        ->count())->toBe(1);
});

it('initializes attendance only for athletes eligible for the session', function () {
    [
        'branch' => $branch,
        'group' => $group,
        'coachUser' => $coachUser,
        'athlete' => $eligibleAthlete,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $otherGroup = Group::create([
        'group_name' => 'Other Attendance Group',
        'branch_id' => $branch->branch_id,
    ]);
    $otherBranch = Branch::create([
        'branch_name' => 'Other Attendance Branch',
        'location' => 'Bandung',
    ]);
    $otherBranchGroup = Group::create([
        'group_name' => 'Other Branch Group',
        'branch_id' => $otherBranch->branch_id,
    ]);
    $otherGroupAthlete = createAthleteForAttendance($branch, $otherGroup);
    $otherBranchAthlete = createAthleteForAttendance($otherBranch, $otherBranchGroup);

    $this->actingAs($coachUser)
        ->post(route('sessions.attendance-qr.store', ['session' => $trainingSession]))
        ->assertRedirect();

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $eligibleAthlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
    ]);
    $this->assertDatabaseMissing('athlete_attendance', [
        'athlete_id' => $otherGroupAthlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
    ]);
    $this->assertDatabaseMissing('athlete_attendance', [
        'athlete_id' => $otherBranchAthlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
    ]);
});

it('returns 403 when an unrelated coach attempts to open the QR', function () {
    ['trainingSession' => $trainingSession] = createAttendanceQrScenario();
    $otherCoachUser = User::factory()->create(['role' => 'coach']);

    $this->actingAs($otherCoachUser)
        ->post(route('sessions.attendance-qr.store', ['session' => $trainingSession]))
        ->assertForbidden();

    $this->assertDatabaseMissing('athlete_attendance', [
        'training_session_id' => $trainingSession->training_session_id,
    ]);
});

it('returns 403 when a non-coach user attempts to open the QR', function () {
    ['trainingSession' => $trainingSession] = createAttendanceQrScenario();
    $nonCoachUser = User::factory()->create(['role' => 'athlete']);

    $this->actingAs($nonCoachUser)
        ->post(route('sessions.attendance-qr.store', ['session' => $trainingSession]))
        ->assertForbidden();

    $this->assertDatabaseMissing('athlete_attendance', [
        'training_session_id' => $trainingSession->training_session_id,
    ]);
});

it('initializes every athlete in the branch when the session has no group', function () {
    [
        'branch' => $branch,
        'coachUser' => $coachUser,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();
    $trainingSession->update(['group_id' => null]);

    $sameBranch = Group::create([
        'group_name' => 'Same Branch Group',
        'branch_id' => $branch->branch_id,
    ]);
    $sameBranchAthlete = createAthleteForAttendance($branch, $sameBranch);
    $otherBranch = Branch::create([
        'branch_name' => 'Other Branch',
        'location' => 'Bandung',
    ]);
    $otherBranchGroup = Group::create([
        'group_name' => 'Other Branch Group',
        'branch_id' => $otherBranch->branch_id,
    ]);
    $otherBranchAthlete = createAthleteForAttendance($otherBranch, $otherBranchGroup);

    $this->actingAs($coachUser)
        ->post(route('sessions.attendance-qr.store', ['session' => $trainingSession]))
        ->assertRedirect();

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $sameBranchAthlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
    ]);
    $this->assertDatabaseMissing('athlete_attendance', [
        'athlete_id' => $otherBranchAthlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
    ]);
});

it('opens immediately and ignores legacy timed-window input', function () {
    [
        'coachUser' => $coachUser,
        'athlete' => $athlete,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $this->actingAs($coachUser)
        ->post(route('sessions.attendance-qr.store', ['session' => $trainingSession]), [
            'attendance_opens_at' => '2026-07-01 08:00:00',
            'attendance_closes_at' => '2026-07-01 08:01:00',
        ])
        ->assertRedirect()
        ->assertSessionHas('attendanceQr');

    $trainingSession->refresh();

    expect($trainingSession->attendance_token_hash)->not->toBeNull()
        ->and($trainingSession->attendance_opens_at?->format('Y-m-d H:i:s'))->toBe('2026-07-02 09:30:00')
        ->and($trainingSession->attendance_closes_at)->toBeNull()
        ->and($trainingSession->attendance_qr_revoked_at)->toBeNull();

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
        'status' => 'ABSENT',
    ]);
});

it('manually closes and revokes the active QR', function () {
    [
        'coachUser' => $coachUser,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $this->actingAs($coachUser)
        ->post(route('sessions.attendance-qr.store', ['session' => $trainingSession]))
        ->assertRedirect();

    Carbon::setTestNow('2026-07-02 09:45:00');

    $this->actingAs($coachUser)
        ->delete(route('sessions.attendance-qr.destroy', ['session' => $trainingSession]))
        ->assertRedirect();

    $trainingSession->refresh();

    expect($trainingSession->attendance_token_hash)->toBeNull()
        ->and($trainingSession->attendance_qr_token)->toBeNull()
        ->and($trainingSession->attendance_closes_at?->format('Y-m-d H:i:s'))->toBe('2026-07-02 09:45:00')
        ->and($trainingSession->attendance_qr_revoked_at?->format('Y-m-d H:i:s'))->toBe('2026-07-02 09:45:00');
});
