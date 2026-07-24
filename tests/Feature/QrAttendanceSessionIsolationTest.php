<?php

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\Domain\AttendanceStatus;
use App\Support\Domain\SessionStatus;
use Illuminate\Support\Carbon;

it('never reassigns another session attendance row from the same date', function () {
    Carbon::setTestNow('2026-07-24 10:00:00');

    $branch = Branch::query()->create([
        'branch_name' => 'QR Isolation Branch',
        'location' => 'Malang',
    ]);
    $group = Group::query()->create([
        'group_name' => 'QR Isolation Group',
    ]);
    $coachUser = User::factory()->create(['role' => 'coach']);
    $coach = Coach::query()->create([
        'id' => $coachUser->id,
        'status' => 'active',
    ]);

    $firstSession = TrainingSession::query()->create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => 'Morning Session',
        'location' => 'Dojang',
        'session_date' => today()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
        'status' => SessionStatus::CONFIRMED,
    ]);

    $token = str_repeat('q', 96);
    $secondSession = TrainingSession::query()->create([
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'title' => 'Evening Session',
        'location' => 'Dojang',
        'session_date' => today()->toDateString(),
        'start_time' => '17:00:00',
        'end_time' => '18:00:00',
        'status' => SessionStatus::CONFIRMED,
        'attendance_token_hash' => hash('sha256', $token),
    ]);

    $athleteUser = User::factory()->create(['role' => 'athlete']);
    $athlete = Athlete::query()->create([
        'id' => $athleteUser->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 160,
        'weight_kg' => 55,
        'geup' => 'GEUP_5',
    ]);

    $firstAttendance = Attendance::query()->create([
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $firstSession->training_session_id,
        'date' => today()->toDateString(),
        'status' => AttendanceStatus::ABSENT,
    ]);

    $this->actingAs($athleteUser)
        ->post(route('attendance.scan.store', $token))
        ->assertRedirect()
        ->assertSessionHas('attendanceScan.status', 'recorded');

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_attendance_id' => $firstAttendance->athlete_attendance_id,
        'training_session_id' => $firstSession->training_session_id,
        'status' => AttendanceStatus::ABSENT,
    ]);
    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $secondSession->training_session_id,
        'status' => AttendanceStatus::PRESENT,
    ]);

    Carbon::setTestNow();
});
