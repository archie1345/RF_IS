<?php

use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\WeeklyTrainingSchedule;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function scheduleAccessCoach(string $name): array
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

function scheduleAccessSchedule(Branch $branch, Group $group, Coach $coach, string $title): WeeklyTrainingSchedule
{
    return WeeklyTrainingSchedule::query()->create([
        'title' => $title,
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

it('shows coaches schedules from multi-coach class assignments and hides unrelated schedules', function () {
    [$coachUser, $coach] = scheduleAccessCoach('Assigned Assistant Coach');
    [, $primaryCoach] = scheduleAccessCoach('Primary Schedule Coach');
    [, $unrelatedCoach] = scheduleAccessCoach('Unrelated Schedule Coach');
    $branch = Branch::query()->create([
        'branch_name' => 'Schedule Access Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $assignedGroup = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'coach_id' => $primaryCoach->coach_id,
        'group_name' => 'Assigned Multi Coach Class',
        'class_type' => 'reguler',
        'is_active' => true,
    ]);
    $assignedGroup->coaches()->attach($coach->coach_id);
    $unrelatedGroup = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'coach_id' => $unrelatedCoach->coach_id,
        'group_name' => 'Unrelated Class',
        'class_type' => 'reguler',
        'is_active' => true,
    ]);

    scheduleAccessSchedule($branch, $assignedGroup, $primaryCoach, 'Visible Multi Coach Schedule');
    $hidden = scheduleAccessSchedule($branch, $unrelatedGroup, $unrelatedCoach, 'Hidden Unrelated Schedule');

    $this->actingAs($coachUser)
        ->get(route('training-schedule.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('WeeklySchedulePage')
            ->has('weeklySchedules', 1)
            ->where('weeklySchedules.0.title', 'Visible Multi Coach Schedule')
            ->where('canManageSchedule', true));

    $this->actingAs($coachUser)
        ->put(route('training-schedules.update', $hidden), [])
        ->assertForbidden();
});

it('deactivates schedules with history and removes their future generated sessions', function () {
    Carbon::setTestNow('2026-07-24 10:00:00');

    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now(),
    ]);
    [, $coach] = scheduleAccessCoach('Lifecycle Coach');
    $branch = Branch::query()->create([
        'branch_name' => 'Lifecycle Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $group = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'coach_id' => $coach->coach_id,
        'group_name' => 'Lifecycle Class',
        'class_type' => 'reguler',
        'is_active' => true,
    ]);
    $schedule = scheduleAccessSchedule($branch, $group, $coach, 'Lifecycle Schedule');
    $futureSession = TrainingSession::query()->create([
        'weekly_training_schedule_id' => $schedule->weekly_training_schedule_id,
        'coach_id' => $coach->coach_id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'session_type' => 'reguler',
        'title' => 'Future Generated Session',
        'location' => 'Dojang',
        'session_date' => now()->addWeek()->toDateString(),
        'start_time' => '16:00:00',
        'end_time' => '18:00:00',
        'status' => 'CONFIRMED',
    ]);

    $this->actingAs($admin)
        ->delete(route('training-schedules.destroy', $schedule))
        ->assertRedirect();

    expect($schedule->fresh()->is_active)->toBeFalse();
    $this->assertSoftDeleted('training_sessions', [
        'training_session_id' => $futureSession->training_session_id,
    ]);

    Carbon::setTestNow();
});
