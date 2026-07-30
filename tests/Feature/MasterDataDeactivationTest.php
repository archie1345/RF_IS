<?php

use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingGroup;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\WeeklyTrainingSchedule;
use Illuminate\Support\Carbon;

function deactivationCoach(): Coach
{
    $user = User::factory()->create([
        'role' => 'coach',
        'email_verified_at' => now(),
    ]);

    return Coach::query()->create([
        'id' => $user->id,
        'status' => 'active',
    ]);
}

function deactivationBranch(string $name): Branch
{
    return Branch::query()->create([
        'branch_name' => $name,
        'location' => 'Jl. Audit 1',
        'address' => 'Jl. Audit 1',
        'city' => 'Malang',
        'province' => 'Jawa Timur',
        'latitude' => -7.98,
        'longitude' => 112.63,
        'attendance_radius_meters' => 100,
        'timezone' => 'Asia/Jakarta',
        'is_active' => true,
    ]);
}

function deactivationSchedule(Branch $branch, Group $group, Coach $coach): WeeklyTrainingSchedule
{
    return WeeklyTrainingSchedule::query()->create([
        'title' => $group->group_name,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'coach_id' => $coach->coach_id,
        'session_type' => 'reguler',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);
}

function deactivationSession(Branch $branch, Group $group, Coach $coach, WeeklyTrainingSchedule $schedule): TrainingSession
{
    return TrainingSession::query()->create([
        'weekly_training_schedule_id' => $schedule->weekly_training_schedule_id,
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'session_type' => 'reguler',
        'title' => 'Future Master Data Session',
        'location' => $branch->location,
        'session_date' => today()->addWeek()->toDateString(),
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
        'status' => 'CONFIRMED',
        'attendance_token_hash' => hash('sha256', 'master-data-token-'.$group->group_id),
        'attendance_qr_token' => str_repeat('x', 96),
        'attendance_qr_generated_at' => now(),
    ]);
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-07-24 10:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('deactivates branch classes and schedules and removes future sessions', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);
    $coach = deactivationCoach();
    $branch = deactivationBranch('Branch Cascade Audit');
    $trainingGroup = TrainingGroup::query()->create([
        'name' => 'Branch Cascade Category',
        'is_active' => true,
    ]);
    $group = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'training_group_id' => $trainingGroup->id,
        'coach_id' => $coach->coach_id,
        'group_name' => 'Branch Cascade Class',
        'class_type' => 'reguler',
        'schedule_mode' => 'weekly',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);
    $schedule = deactivationSchedule($branch, $group, $coach);
    $session = deactivationSession($branch, $group, $coach, $schedule);

    $this->actingAs($admin)
        ->delete(route('admin.branches.destroy', $branch))
        ->assertRedirect();

    expect($branch->fresh()->is_active)->toBeFalse()
        ->and($group->fresh()->is_active)->toBeFalse()
        ->and($schedule->fresh()->is_active)->toBeFalse();
    $this->assertSoftDeleted('training_sessions', [
        'training_session_id' => $session->training_session_id,
    ]);
    $this->assertDatabaseHas('training_sessions', [
        'training_session_id' => $session->training_session_id,
        'attendance_token_hash' => null,
        'attendance_qr_token' => null,
    ]);
});

it('deactivates linked classes when an athlete category is retired', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);
    $coach = deactivationCoach();
    $branch = deactivationBranch('Category Cascade Branch');
    $trainingGroup = TrainingGroup::query()->create([
        'name' => 'Retired Athlete Category',
        'is_active' => true,
    ]);
    $group = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'training_group_id' => $trainingGroup->id,
        'coach_id' => $coach->coach_id,
        'group_name' => 'Retired Category Class',
        'class_type' => 'prestasi',
        'schedule_mode' => 'weekly',
        'day_of_week' => 2,
        'start_time' => '17:00',
        'end_time' => '19:00',
        'is_active' => true,
    ]);
    $schedule = deactivationSchedule($branch, $group, $coach);
    $session = deactivationSession($branch, $group, $coach, $schedule);

    $this->actingAs($admin)
        ->delete(route('admin.training-groups.destroy', $trainingGroup))
        ->assertRedirect();

    expect($trainingGroup->fresh()->is_active)->toBeFalse()
        ->and($group->fresh()->is_active)->toBeFalse()
        ->and($schedule->fresh()->is_active)->toBeFalse();
    $this->assertSoftDeleted('training_sessions', [
        'training_session_id' => $session->training_session_id,
    ]);
});
