<?php

use App\Actions\Sessions\GenerateWeeklyTrainingSessions;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\WeeklyTrainingSchedule;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function weeklyScheduleTestBranch(string $name = 'Schedule Branch'): Branch
{
    return Branch::create(['branch_name' => $name, 'location' => 'Jakarta']);
}

function weeklyScheduleTestGroup(Branch $branch, ?string $coachId = null, string $name = 'Schedule Group'): Group
{
    return Group::create([
        'branch_id' => $branch->branch_id,
        'coach_id' => $coachId,
        'group_name' => $name,
        'class_type' => 'Reguler',
        'is_active' => true,
    ]);
}

function weeklyScheduleTestAthlete(Branch $branch, Group $group, string $name = 'Athlete'): Athlete
{
    $user = User::factory()->create(['name' => $name, 'role' => 'athlete']);

    return Athlete::create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 150,
        'weight_kg' => 45,
        'nik_hash' => hash('sha256', $name.'-nik'),
        'bpjs_hash' => hash('sha256', $name.'-bpjs'),
        'geup' => 'GEUP_1',
    ]);
}

function weeklyScheduleTestCoach(string $name = 'Coach'): array
{
    $user = User::factory()->create(['name' => $name, 'role' => 'coach']);
    $coach = Coach::create(['id' => $user->id, 'status' => 'active']);

    return [$user, $coach];
}

it('allows multiple schedule types in the same group and time window', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [, $coach] = weeklyScheduleTestCoach('Admin Schedule Coach');
    $branch = weeklyScheduleTestBranch();
    $group = weeklyScheduleTestGroup($branch, $coach->coach_id);

    $payload = [
        'title' => 'Same Window Reguler',
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'coach_id' => $coach->coach_id,
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'location' => null,
        'is_active' => true,
    ];

    $this->actingAs($admin)->from(route('training-schedule.index'))->post(route('training-schedules.store'), $payload + ['session_type' => 'reguler'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->from(route('training-schedule.index'))->post(route('training-schedules.store'), $payload + ['title' => 'Same Window Prestasi', 'session_type' => 'prestasi'])->assertSessionHasNoErrors();

    expect(WeeklyTrainingSchedule::query()->where('branch_id', $branch->branch_id)->count())->toBe(2);
});

it('generates separate sessions for each weekly schedule and remains idempotent', function () {
    [, $coach] = weeklyScheduleTestCoach('Generator Coach');
    $branch = weeklyScheduleTestBranch();
    $group = weeklyScheduleTestGroup($branch, $coach->coach_id);

    $reguler = WeeklyTrainingSchedule::create([
        'title' => 'Reguler Monday',
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'coach_id' => $coach->coach_id,
        'session_type' => 'reguler',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    $prestasi = WeeklyTrainingSchedule::create([
        'title' => 'Prestasi Monday',
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'coach_id' => $coach->coach_id,
        'session_type' => 'prestasi',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    $result = app(GenerateWeeklyTrainingSessions::class)->handle(Carbon::parse('2026-07-13'), Carbon::parse('2026-07-13'));
    $secondRun = app(GenerateWeeklyTrainingSessions::class)->handle(Carbon::parse('2026-07-13'), Carbon::parse('2026-07-13'));

    expect($result['created'])->toBe(2)
        ->and($secondRun['created'])->toBe(0)
        ->and($secondRun['skipped'])->toBe(2);

    $this->assertDatabaseHas('training_sessions', [
        'weekly_training_schedule_id' => $reguler->weekly_training_schedule_id,
        'session_type' => 'reguler',
    ]);
    $this->assertDatabaseHas('training_sessions', [
        'weekly_training_schedule_id' => $prestasi->weekly_training_schedule_id,
        'session_type' => 'prestasi',
    ]);
    expect(TrainingSession::query()->count())->toBe(2);
});

it('generates private sessions for the dedicated athlete only', function () {
    [, $coach] = weeklyScheduleTestCoach('Private Generator Coach');
    $branch = weeklyScheduleTestBranch();
    $group = weeklyScheduleTestGroup($branch, $coach->coach_id);
    $dedicated = weeklyScheduleTestAthlete($branch, $group, 'Dedicated Athlete');
    $other = weeklyScheduleTestAthlete($branch, $group, 'Other Athlete');

    $schedule = WeeklyTrainingSchedule::create([
        'title' => 'Private Monday',
        'branch_id' => $branch->branch_id,
        'group_id' => null,
        'dedicated_athlete_id' => $dedicated->athlete_id,
        'coach_id' => $coach->coach_id,
        'session_type' => 'private',
        'day_of_week' => 1,
        'start_time' => '17:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    app(GenerateWeeklyTrainingSessions::class)->handle(Carbon::parse('2026-07-13'), Carbon::parse('2026-07-13'));

    $session = TrainingSession::query()->where('weekly_training_schedule_id', $schedule->weekly_training_schedule_id)->firstOrFail();

    expect($session->session_type)->toBe('private')
        ->and((string) $session->dedicated_athlete_id)->toBe((string) $dedicated->athlete_id);

    $this->assertDatabaseHas('athlete_attendance', [
        'training_session_id' => $session->training_session_id,
        'athlete_id' => $dedicated->athlete_id,
    ]);
    $this->assertDatabaseMissing('athlete_attendance', [
        'training_session_id' => $session->training_session_id,
        'athlete_id' => $other->athlete_id,
    ]);
});

it('rejects private schedules without an athlete and normalizes private group data', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [, $coach] = weeklyScheduleTestCoach('Validation Coach');
    $branch = weeklyScheduleTestBranch();
    $group = weeklyScheduleTestGroup($branch, $coach->coach_id);
    $athlete = weeklyScheduleTestAthlete($branch, $group, 'Private Athlete');

    $basePayload = [
        'title' => 'Private Validation',
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'coach_id' => $coach->coach_id,
        'session_type' => 'private',
        'day_of_week' => 2,
        'start_time' => '15:00',
        'end_time' => '16:00',
        'location' => null,
        'is_active' => true,
    ];

    $this->actingAs($admin)
        ->from(route('training-schedule.index'))
        ->post(route('training-schedules.store'), $basePayload)
        ->assertSessionHasErrors('dedicated_athlete_id');

    $this->actingAs($admin)
        ->from(route('training-schedule.index'))
        ->post(route('training-schedules.store'), $basePayload + ['dedicated_athlete_id' => $athlete->athlete_id])
        ->assertSessionHasNoErrors();

    $schedule = WeeklyTrainingSchedule::query()->where('title', 'Private Validation')->firstOrFail();
    expect($schedule->group_id)->toBeNull()
        ->and((string) $schedule->dedicated_athlete_id)->toBe((string) $athlete->athlete_id);
});

it('clears dedicated athletes from non-private schedules', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [, $coach] = weeklyScheduleTestCoach('Non Private Coach');
    $branch = weeklyScheduleTestBranch();
    $group = weeklyScheduleTestGroup($branch, $coach->coach_id);
    $athlete = weeklyScheduleTestAthlete($branch, $group, 'Cleared Athlete');

    $this->actingAs($admin)
        ->from(route('training-schedule.index'))
        ->post(route('training-schedules.store'), [
            'title' => 'Non Private',
            'branch_id' => $branch->branch_id,
            'group_id' => $group->group_id,
            'dedicated_athlete_id' => $athlete->athlete_id,
            'coach_id' => $coach->coach_id,
            'session_type' => 'reguler',
            'day_of_week' => 3,
            'start_time' => '15:00',
            'end_time' => '16:00',
            'location' => null,
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $schedule = WeeklyTrainingSchedule::query()->where('title', 'Non Private')->firstOrFail();
    expect($schedule->dedicated_athlete_id)->toBeNull()
        ->and((int) $schedule->group_id)->toBe((int) $group->group_id);
});

it('shows athletes their active group schedules and dedicated private schedules only', function () {
    $branch = weeklyScheduleTestBranch();
    $group = weeklyScheduleTestGroup($branch);
    $otherGroup = weeklyScheduleTestGroup($branch, null, 'Other Group');
    $athlete = weeklyScheduleTestAthlete($branch, $group, 'Visible Athlete');
    $otherAthlete = weeklyScheduleTestAthlete($branch, $otherGroup, 'Hidden Athlete');

    WeeklyTrainingSchedule::create(['title' => 'Own Group', 'branch_id' => $branch->branch_id, 'group_id' => $group->group_id, 'session_type' => 'reguler', 'day_of_week' => 1, 'start_time' => '10:00', 'end_time' => '11:00', 'is_active' => true]);
    WeeklyTrainingSchedule::create(['title' => 'Own Private', 'branch_id' => $branch->branch_id, 'dedicated_athlete_id' => $athlete->athlete_id, 'session_type' => 'private', 'day_of_week' => 2, 'start_time' => '10:00', 'end_time' => '11:00', 'is_active' => true]);
    WeeklyTrainingSchedule::create(['title' => 'Other Private', 'branch_id' => $branch->branch_id, 'dedicated_athlete_id' => $otherAthlete->athlete_id, 'session_type' => 'private', 'day_of_week' => 3, 'start_time' => '10:00', 'end_time' => '11:00', 'is_active' => true]);
    WeeklyTrainingSchedule::create(['title' => 'Inactive Own', 'branch_id' => $branch->branch_id, 'group_id' => $group->group_id, 'session_type' => 'reguler', 'day_of_week' => 4, 'start_time' => '10:00', 'end_time' => '11:00', 'is_active' => false]);

    $this->actingAs($athlete->user)
        ->get(route('training-schedule.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('WeeklySchedulePage')
            ->has('weeklySchedules', 2)
            ->where('weeklySchedules.0.title', 'Own Group')
            ->where('weeklySchedules.1.title', 'Own Private'));
});

it('prevents coaches from assigning private sessions outside their managed groups or branches', function () {
    [$coachUser, $coach] = weeklyScheduleTestCoach('Scoped Coach');
    $branch = weeklyScheduleTestBranch('Managed Branch');
    $managedGroup = weeklyScheduleTestGroup($branch, $coach->coach_id, 'Managed Group');
    $managedAthlete = weeklyScheduleTestAthlete($branch, $managedGroup, 'Managed Athlete');

    $otherBranch = weeklyScheduleTestBranch('Other Branch');
    $otherGroup = weeklyScheduleTestGroup($otherBranch, null, 'Other Group');
    $otherAthlete = weeklyScheduleTestAthlete($otherBranch, $otherGroup, 'Unauthorized Athlete');

    $payload = [
        'title' => 'Coach Private',
        'branch_id' => $branch->branch_id,
        'group_id' => null,
        'coach_id' => $coach->coach_id,
        'session_type' => 'private',
        'day_of_week' => 5,
        'start_time' => '15:00',
        'end_time' => '16:00',
        'location' => null,
        'is_active' => true,
    ];

    $this->actingAs($coachUser)
        ->from(route('training-schedule.index'))
        ->post(route('training-schedules.store'), $payload + ['dedicated_athlete_id' => $otherAthlete->athlete_id])
        ->assertSessionHasErrors('dedicated_athlete_id');

    $this->actingAs($coachUser)
        ->from(route('training-schedule.index'))
        ->post(route('training-schedules.store'), $payload + ['dedicated_athlete_id' => $managedAthlete->athlete_id])
        ->assertSessionHasNoErrors();
});

it('generates sessions for an active class schedule from today and stays idempotent', function () {
    Carbon::setTestNow('2026-07-16 09:00:00');

    $branch = weeklyScheduleTestBranch('Auto Session Branch');
    $group = Group::create([
        'branch_id' => $branch->branch_id,
        'group_name' => 'Test Auto Session',
        'class_type' => 'reguler',
        'day_of_week' => now()->isoWeekday(),
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    $schedule = WeeklyTrainingSchedule::create([
        'title' => $group->group_name,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'session_type' => 'reguler',
        'day_of_week' => now()->isoWeekday(),
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    $result = app(GenerateWeeklyTrainingSessions::class)->handle(now()->startOfDay(), now()->copy()->addDays(14)->endOfDay(), [$schedule->weekly_training_schedule_id]);
    $secondRun = app(GenerateWeeklyTrainingSessions::class)->handle(now()->startOfDay(), now()->copy()->addDays(14)->endOfDay(), [$schedule->weekly_training_schedule_id]);

    expect($schedule->fresh()->is_active)->toBeTrue()
        ->and($result['created'])->toBeGreaterThanOrEqual(1)
        ->and($secondRun['created'])->toBe(0)
        ->and($secondRun['skipped'])->toBe($result['created']);

    $this->assertDatabaseHas('training_sessions', [
        'weekly_training_schedule_id' => $schedule->weekly_training_schedule_id,
        'session_date' => now()->toDateString(),
        'group_id' => $group->group_id,
        'branch_id' => $branch->branch_id,
        'status' => 'CONFIRMED',
    ]);

    Carbon::setTestNow();
});

it('does not generate sessions for inactive branch or inactive class schedules', function () {
    Carbon::setTestNow('2026-07-16 09:00:00');

    $inactiveBranch = weeklyScheduleTestBranch('Inactive Branch');
    $inactiveBranch->update(['is_active' => false]);
    $activeBranch = weeklyScheduleTestBranch('Active Branch');

    $activeGroup = Group::create([
        'branch_id' => $inactiveBranch->branch_id,
        'group_name' => 'Active Group In Inactive Branch',
        'class_type' => 'reguler',
        'day_of_week' => now()->isoWeekday(),
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);
    $inactiveGroup = Group::create([
        'branch_id' => $activeBranch->branch_id,
        'group_name' => 'Inactive Group',
        'class_type' => 'reguler',
        'day_of_week' => now()->isoWeekday(),
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => false,
    ]);

    $inactiveBranchSchedule = WeeklyTrainingSchedule::create([
        'title' => 'Inactive Branch Schedule',
        'branch_id' => $inactiveBranch->branch_id,
        'group_id' => $activeGroup->group_id,
        'session_type' => 'reguler',
        'day_of_week' => now()->isoWeekday(),
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);
    $inactiveGroupSchedule = WeeklyTrainingSchedule::create([
        'title' => 'Inactive Group Schedule',
        'branch_id' => $activeBranch->branch_id,
        'group_id' => $inactiveGroup->group_id,
        'session_type' => 'reguler',
        'day_of_week' => now()->isoWeekday(),
        'start_time' => '19:00',
        'end_time' => '20:00',
        'is_active' => true,
    ]);

    $result = app(GenerateWeeklyTrainingSessions::class)->handle(
        now()->startOfDay(),
        now()->copy()->addDays(14)->endOfDay(),
        [$inactiveBranchSchedule->weekly_training_schedule_id, $inactiveGroupSchedule->weekly_training_schedule_id]
    );

    expect($result['created'])->toBe(0)
        ->and(TrainingSession::query()->count())->toBe(0);

    Carbon::setTestNow();
});
