<?php

use App\Actions\Attendance\InitializeSessionAttendance;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates absent athlete attendance rows for eligible athletes', function () {
    $branch = Branch::create(['branch_name' => 'Attendance Branch', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => 'Junior']);
    $coachUser = User::factory()->create(['role' => 'coach']);
    $coach = Coach::create(['id' => $coachUser->id, 'status' => 'active']);

    $trainingSession = TrainingSession::create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => 'Morning Training',
        'location' => 'Dojo',
        'session_date' => '2026-07-02',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'CONFIRMED',
    ]);

    $athleteUser = User::factory()->create(['role' => 'athlete']);
    $athlete = Athlete::create([
        'id' => $athleteUser->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'nik_hash' => hash('sha256', 'nik'),
        'bpjs_hash' => hash('sha256', 'bpjs'),
        'geup' => 'GEUP_1',
    ]);

    $created = app(InitializeSessionAttendance::class)->handle($trainingSession);

    expect($created)->toBe(1);

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
        'status' => 'ABSENT',
    ]);
});

it('is idempotent and preserves existing attendance status when session date changes', function () {
    $branch = Branch::create(['branch_name' => 'Idempotent Branch', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => 'Idempotent Group']);
    $coachUser = User::factory()->create(['role' => 'coach']);
    $coach = Coach::create(['id' => $coachUser->id, 'status' => 'active']);
    $admin = User::factory()->create(['role' => 'admin']);

    $trainingSession = TrainingSession::create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => 'Idempotent Training',
        'location' => 'Dojo',
        'session_date' => '2026-07-02',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'CONFIRMED',
    ]);

    $athleteUser = User::factory()->create(['role' => 'athlete']);
    $athlete = Athlete::create([
        'id' => $athleteUser->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'nik_hash' => hash('sha256', 'idempotent-nik'),
        'bpjs_hash' => hash('sha256', 'idempotent-bpjs'),
        'geup' => 'GEUP_1',
    ]);

    $existing = \App\Models\Attendance::create([
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
        'date' => '2026-07-02',
        'status' => 'PRESENT',
    ]);

    $trainingSession->update(['session_date' => '2026-07-05']);

    $this->actingAs($admin)
        ->get(route('sessions.attendance', $trainingSession))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('SessionAttendancePage')
            ->where('rows.0.id', 'ATT-'.$existing->athlete_attendance_id)
            ->where('rows.0.status.text', 'Present'));

    $this->actingAs($admin)
        ->get(route('sessions.attendance', $trainingSession))
        ->assertOk();

    expect(\App\Models\Attendance::query()
        ->where('athlete_id', $athlete->athlete_id)
        ->where('training_session_id', $trainingSession->training_session_id)
        ->count())->toBe(1);

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_attendance_id' => $existing->athlete_attendance_id,
        'status' => 'PRESENT',
    ]);
});

it('restores a soft-deleted matching attendance row instead of inserting a duplicate', function () {
    $branch = Branch::create(['branch_name' => 'Soft Delete Branch', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => 'Soft Delete Group']);
    $coachUser = User::factory()->create(['role' => 'coach']);
    $coach = Coach::create(['id' => $coachUser->id, 'status' => 'active']);

    $trainingSession = TrainingSession::create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => 'Soft Delete Training',
        'location' => 'Dojo',
        'session_date' => '2026-07-02',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'CONFIRMED',
    ]);

    $athleteUser = User::factory()->create(['role' => 'athlete']);
    $athlete = Athlete::create([
        'id' => $athleteUser->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'nik_hash' => hash('sha256', 'soft-nik'),
        'bpjs_hash' => hash('sha256', 'soft-bpjs'),
        'geup' => 'GEUP_1',
    ]);

    $attendance = \App\Models\Attendance::create([
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
        'date' => $trainingSession->session_date,
        'status' => 'EXCUSED',
    ]);
    $attendance->delete();

    $created = app(InitializeSessionAttendance::class)->handle($trainingSession);

    expect($created)->toBe(0)
        ->and(\App\Models\Attendance::withTrashed()
            ->where('athlete_id', $athlete->athlete_id)
            ->where('training_session_id', $trainingSession->training_session_id)
            ->count())->toBe(1);

    $attendance->refresh();
    expect($attendance->deleted_at)->toBeNull()
        ->and($attendance->status)->toBe('EXCUSED');
});

it('initializes attendance for all branch athletes when the session has no group', function () {
    $branch = Branch::create(['branch_name' => 'Groupless Branch', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => 'Groupless Junior']);
    $otherBranch = Branch::create(['branch_name' => 'Other Groupless Branch', 'location' => 'Bandung']);
    $coachUser = User::factory()->create(['role' => 'coach']);
    $coach = Coach::create(['id' => $coachUser->id, 'status' => 'active']);

    $trainingSession = TrainingSession::create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => null,
        'title' => 'Groupless Training',
        'location' => 'Dojo',
        'session_date' => '2026-07-02',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => 'CONFIRMED',
    ]);

    $branchAthleteUser = User::factory()->create(['role' => 'athlete']);
    $branchAthlete = Athlete::create([
        'id' => $branchAthleteUser->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'nik_hash' => hash('sha256', 'groupless-branch-nik'),
        'bpjs_hash' => hash('sha256', 'groupless-branch-bpjs'),
        'geup' => 'GEUP_1',
    ]);

    $otherAthleteUser = User::factory()->create(['role' => 'athlete']);
    $otherAthlete = Athlete::create([
        'id' => $otherAthleteUser->id,
        'branch_id' => $otherBranch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'nik_hash' => hash('sha256', 'groupless-other-nik'),
        'bpjs_hash' => hash('sha256', 'groupless-other-bpjs'),
        'geup' => 'GEUP_1',
    ]);

    $created = app(InitializeSessionAttendance::class)->handle($trainingSession);

    expect($created)->toBe(1);

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $branchAthlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
        'status' => 'ABSENT',
    ]);

    $this->assertDatabaseMissing('athlete_attendance', [
        'athlete_id' => $otherAthlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
    ]);
});
