<?php

use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingGroup;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function classCoachTestCoach(string $name): Coach
{
    $user = User::factory()->create([
        'name' => $name,
        'role' => 'coach',
    ]);

    return Coach::query()->create([
        'id' => $user->id,
        'status' => 'active',
    ]);
}

it('assigns multiple coaches to a class and its generated sessions', function () {
    Carbon::setTestNow('2026-07-20 09:00:00');

    $admin = User::factory()->create(['role' => 'admin']);
    $firstCoach = classCoachTestCoach('Coach Satu');
    $secondCoach = classCoachTestCoach('Coach Dua');
    $branch = Branch::query()->create([
        'branch_name' => 'Dojang Multi Coach',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $trainingGroup = TrainingGroup::query()->create([
        'name' => 'Junior',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->from(route('admin.classes'))
        ->post(route('admin.groups.store'), [
            'name' => 'Kelas Multi Coach',
            'class_type' => 'reguler',
            'training_group_id' => $trainingGroup->id,
            'coach_ids' => [$firstCoach->coach_id, $secondCoach->coach_id],
            'schedule_mode' => 'weekly',
            'branch_id' => $branch->branch_id,
            'day_of_week' => 1,
            'start_time' => '16:00',
            'end_time' => '18:00',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $group = Group::query()->where('group_name', 'Kelas Multi Coach')->firstOrFail();

    expect((string) $group->coach_id)->toBe((string) $firstCoach->coach_id)
        ->and($group->coaches()->pluck('coaches.coach_id')->map(fn ($id) => (string) $id)->sort()->values()->all())
        ->toBe(collect([$firstCoach->coach_id, $secondCoach->coach_id])->map(fn ($id) => (string) $id)->sort()->values()->all());

    $session = TrainingSession::query()
        ->where('group_id', $group->group_id)
        ->orderBy('session_date')
        ->firstOrFail();

    expect((string) $session->coach_id)->toBe((string) $firstCoach->coach_id)
        ->and($session->assignedCoaches()->pluck('coaches.coach_id')->map(fn ($id) => (string) $id)->sort()->values()->all())
        ->toBe(collect([$firstCoach->coach_id, $secondCoach->coach_id])->map(fn ($id) => (string) $id)->sort()->values()->all());

    $this->actingAs($admin)
        ->get(route('admin.classes'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('AdminClassesPage')
            ->has('classes', 1)
            ->has('classes.0.coach_ids', 2)
            ->where('classes.0.coach', 'Coach Satu, Coach Dua'));
});

it('keeps the legacy single coach payload compatible', function () {
    Carbon::setTestNow('2026-07-20 09:00:00');

    $admin = User::factory()->create(['role' => 'admin']);
    $coach = classCoachTestCoach('Legacy Coach');
    $branch = Branch::query()->create([
        'branch_name' => 'Dojang Legacy Coach',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $trainingGroup = TrainingGroup::query()->create([
        'name' => 'Senior',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.groups.store'), [
            'name' => 'Kelas Legacy Coach',
            'class_type' => 'reguler',
            'training_group_id' => $trainingGroup->id,
            'coach_id' => $coach->coach_id,
            'schedule_mode' => 'weekly',
            'branch_id' => $branch->branch_id,
            'day_of_week' => 1,
            'start_time' => '18:00',
            'end_time' => '20:00',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $group = Group::query()->where('group_name', 'Kelas Legacy Coach')->firstOrFail();

    expect($group->coaches()->where('coaches.coach_id', $coach->coach_id)->exists())->toBeTrue();
});
