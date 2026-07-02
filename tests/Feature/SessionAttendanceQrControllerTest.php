<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\User;

it('generating a session QR initializes attendance for eligible athletes', function (){
    $branch = Branch::create(['branch_name' => 'Attendance Branch', 'location' => 'Jakarta']);
    $group = Group::create(['group_name' => 'Attendance Group', 'branch_id'=>$branch->branch_id]);

    $coachUser = User::factory()->create(['role'=>'coach']);

    $coach = Coach::create(['id'=>$coachUser->id, 'status'=>'active']);

    $athleteUser = User::factory()->create(['role'=>'athlete']);

    $athlete = Athlete::create([
        'id'=>$athleteUser->id,
        'branch_id'=>$branch->branch_id,
        'group_id'=>$group->group_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'geup'=>'GEUP_1',
        'nik_hash'=>hash('sha256','nik'),
        'bpjs_hash'=>hash('sha256','bpjs'),
        ]);

    $trainingSession = TrainingSession::create([
        'coach_id'=>$coach->coach_id,
        'branch_id'=>$branch->branch_id,
        'group_id'=>$group->group_id,
        'session_date' => '2026-07-02',
        'start_time' => '09:00:00',
        'end_time' => '10:00:00',
        'status'=>'CONFIRMED',
    ]);

    $response = $this->actingAs($coachUser)->post(route('sessions.attendance-qr.store', ['session' => $trainingSession]), [
        'attendance_opens_at' => \Carbon\Carbon::createFromFormat('H:i:s', '09:00:00')->addMinutes(5)->toDateTimeString(),
        'attendance_closes_at' => \Carbon\Carbon::createFromFormat('H:i:s', '09:00:00')->addMinutes(15)->toDateTimeString(),
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('athlete_attendance', [
        'athlete_id' => $athlete->athlete_id,
        'training_session_id' => $trainingSession->training_session_id,
        'status' => 'ABSENT',
    ]);
});//php artisan test tests/Feature/SessionAttendanceQrControllerTest.php