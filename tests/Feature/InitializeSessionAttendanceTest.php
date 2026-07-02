<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Athlete;
use App\Models\Session;
use App\Models\Attendance;
use App\Actions\Attendance\InitializeSessionAttendance;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Coach;
use App\Models\User;
use App\Models\CoachSession;
use App\Models\AthleteAttendance;

uses(RefreshDatabase::class);

it('creates absent attendance rows for eligible athletes', function() {
    $branch = Branch::factory()->create();
    $group = Group::factory()->create();
    $coachSession = CoachSession::factory()->create([
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
    ]);
    $coachUser = User::factory()->create();
    $coach = Coach::factory()->create([
        'coach_id' => $coachUser->id
    ]);
    $session = Session::factory()->create([
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'coach_id' => $coach->coach_id,
        'coach_session_id' => $coachSession->coach_session_id,
    ]);

    $athleteUser = User::factory()->create();

    $athlete = Athlete::factory()->create([
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'user_id' => $athleteUser->id,
    ]);

    $created = app(InitializeSessionAttendance::class)->handle($session);

    expect($created)->toBe(1);

    $this->assertDatabaseHas('athlete_attendances', [
        'athlete_id' => $athlete->athlete_id,
        'coach_session_id' => $session->coach_session_id,
        'status' => 'absent',
    ]);
});