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
