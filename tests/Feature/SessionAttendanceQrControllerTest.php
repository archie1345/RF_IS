<?php

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\User;

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

    return compact(
        'branch',
        'group',
        'coachUser',
        'athlete',
        'trainingSession',
    );
}

function attendanceQrPayload(): array
{
    return [
        'attendance_opens_at' => '2026-07-02 09:05:00',
        'attendance_closes_at' => '2026-07-02 09:15:00',
    ];
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

it('initializes attendance for eligible athletes when generating a session QR', function () {
    [
        'coachUser' => $coachUser,
        'athlete' => $athlete,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $response = $this
        ->actingAs($coachUser)
        ->post(
            route('sessions.attendance-qr.store', [
                'session' => $trainingSession,
            ]),
            attendanceQrPayload(),
        );

    $response->assertRedirect();

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
        'status' => 'ABSENT',
    ]);
});

it('does not duplicate athlete attendance when generating the session QR twice', function () {
    [
        'coachUser' => $coachUser,
        'athlete' => $athlete,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $route = route('sessions.attendance-qr.store', [
        'session' => $trainingSession,
    ]);

    $this
        ->actingAs($coachUser)
        ->post($route, attendanceQrPayload())
        ->assertRedirect();

    $this
        ->actingAs($coachUser)
        ->post($route, attendanceQrPayload())
        ->assertRedirect();

    $attendanceCount = Attendance::query()
        ->where('athlete_id', $athlete->athlete_id)
        ->where(
            'training_session_id',
            $trainingSession->training_session_id,
        )
        ->count();

    expect($attendanceCount)->toBe(1);
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

    $otherGroupAthlete = createAthleteForAttendance(
        $branch,
        $otherGroup,
    );

    $otherBranchAthlete = createAthleteForAttendance(
        $otherBranch,
        $otherBranchGroup,
    );

    $route = route('sessions.attendance-qr.store', [
        'session' => $trainingSession,
    ]);

    $this
        ->actingAs($coachUser)
        ->post($route, attendanceQrPayload())
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

it('returns 403 when a coach not assigned to the session attempts to generate the QR', function () {
    [
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $otherCoachUser = User::factory()->create(['role' => 'coach']);

    $route = route('sessions.attendance-qr.store', ['session' => $trainingSession]);

    $this->actingAs($otherCoachUser)->post($route, attendanceQrPayload())->assertForbidden();

    $this->assertDatabaseMissing('athlete_attendance', [
        'training_session_id' => $trainingSession->training_session_id,
    ]);

});

it('returns 403 when a non-coach user attempts to generate the QR', function () {
    [
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $nonCoachUser = User::factory()->create(['role' => 'athlete']);

    $route = route('sessions.attendance-qr.store', ['session' => $trainingSession]);

    $this->actingAs($nonCoachUser)->post($route, attendanceQrPayload())->assertForbidden();

    $this->assertDatabaseMissing('athlete_attendance', [
        'training_session_id' => $trainingSession->training_session_id,
    ]);
});

it('initialize attendance for all athlete in the branch when the session has no group', function () {
    [
        'branch' => $branch,
        'coachUser' => $coachUser,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $trainingSession->group_id = null;
    $trainingSession->save();

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

    $route = route('sessions.attendance-qr.store', [
        'session' => $trainingSession,
    ]);

    $this->actingAs($coachUser)->post($route, attendanceQrPayload())->assertRedirect();

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $sameBranchAthlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
    ]);

    $this->assertDatabaseMissing('athlete_attendance', [
        'athlete_id' => $otherBranchAthlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
    ]);
});

it('rejects a QR attendance window when closing time is before opening time', function () {
    [
        'coachUser' => $coachUser,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $route = route('sessions.attendance-qr.store', [
        'session' => $trainingSession,
    ]);

    $response = $this
        ->actingAs($coachUser)
        ->post($route, [
            'attendance_opens_at' => '2026-07-02 09:15:00',
            'attendance_closes_at' => '2026-07-02 09:05:00',
        ]);

    $response->assertSessionHasErrors([
        'attendance_closes_at',
    ]);

    $this->assertDatabaseMissing('athlete_attendance', [
        'training_session_id' => $trainingSession->training_session_id,
    ]);
});

it('rejects a QR attendance window before the session starts', function () {
    [
        'coachUser' => $coachUser,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $route = route('sessions.attendance-qr.store', [
        'session' => $trainingSession,
    ]);

    $response = $this
        ->actingAs($coachUser)
        ->post($route, [
            'attendance_opens_at' => '2026-07-02 08:30:00',
            'attendance_closes_at' => '2026-07-02 09:15:00',
        ]);

    $response->assertSessionHasErrors([
        'attendance_opens_at',
    ]);

    $this->assertDatabaseMissing('athlete_attendance', [
        'training_session_id' => $trainingSession->training_session_id,
    ]);
});

it('rejects a QR attendance window after the session ends', function () {
    [
        'coachUser' => $coachUser,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $route = route('sessions.attendance-qr.store', [
        'session' => $trainingSession,
    ]);

    $response = $this
        ->actingAs($coachUser)
        ->post($route, [
            'attendance_opens_at' => '2026-07-02 09:30:00',
            'attendance_closes_at' => '2026-07-02 10:30:00',
        ]);

    $response->assertSessionHasErrors([
        'attendance_closes_at',
    ]);

    $this->assertDatabaseMissing('athlete_attendance', [
        'training_session_id' => $trainingSession->training_session_id,
    ]);
});

it('accepts a QR attendance window exactly matching session boundaries', function () {
    [
        'coachUser' => $coachUser,
        'athlete' => $athlete,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $this
        ->actingAs($coachUser)
        ->post(route('sessions.attendance-qr.store', ['session' => $trainingSession]), [
            'attendance_opens_at' => '2026-07-02 09:00:00',
            'attendance_closes_at' => '2026-07-02 10:00:00',
        ])
        ->assertRedirect()
        ->assertSessionHas('attendanceQr');

    $trainingSession->refresh();

    expect($trainingSession->attendance_token_hash)->not->toBeNull()
        ->and($trainingSession->attendance_opens_at?->format('Y-m-d H:i:s'))->toBe('2026-07-02 09:00:00')
        ->and($trainingSession->attendance_closes_at?->format('Y-m-d H:i:s'))->toBe('2026-07-02 10:00:00');

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
        'status' => 'ABSENT',
    ]);
});

it('rejects equal open and close QR attendance window without persisting QR config or attendance rows', function () {
    [
        'coachUser' => $coachUser,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $this
        ->actingAs($coachUser)
        ->post(route('sessions.attendance-qr.store', ['session' => $trainingSession]), [
            'attendance_opens_at' => '2026-07-02 09:15:00',
            'attendance_closes_at' => '2026-07-02 09:15:00',
        ])
        ->assertSessionHasErrors(['attendance_closes_at']);

    $trainingSession->refresh();

    expect($trainingSession->attendance_token_hash)->toBeNull()
        ->and($trainingSession->attendance_opens_at)->toBeNull()
        ->and($trainingSession->attendance_closes_at)->toBeNull()
        ->and($trainingSession->attendance_qr_generated_at)->toBeNull();

    $this->assertDatabaseMissing('athlete_attendance', [
        'training_session_id' => $trainingSession->training_session_id,
    ]);
});

it('rejects a QR attendance window opening at session end without persisting QR config or attendance rows', function () {
    [
        'coachUser' => $coachUser,
        'trainingSession' => $trainingSession,
    ] = createAttendanceQrScenario();

    $this
        ->actingAs($coachUser)
        ->post(route('sessions.attendance-qr.store', ['session' => $trainingSession]), [
            'attendance_opens_at' => '2026-07-02 10:00:00',
            'attendance_closes_at' => '2026-07-02 10:15:00',
        ])
        ->assertSessionHasErrors(['attendance_opens_at', 'attendance_closes_at']);

    $trainingSession->refresh();

    expect($trainingSession->attendance_token_hash)->toBeNull()
        ->and($trainingSession->attendance_opens_at)->toBeNull()
        ->and($trainingSession->attendance_closes_at)->toBeNull()
        ->and($trainingSession->attendance_qr_generated_at)->toBeNull();

    $this->assertDatabaseMissing('athlete_attendance', [
        'training_session_id' => $trainingSession->training_session_id,
    ]);
});
