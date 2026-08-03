<?php

use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingGroup;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

function classAccessCoach(string $name): array
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

it('allows a coach to open the class page and creates the class with that coach assigned', function () {
    Carbon::setTestNow('2026-07-24 10:00:00');

    [$coachUser, $coach] = classAccessCoach('Class Creator Coach');
    $branch = Branch::query()->create([
        'branch_name' => 'Coach Class Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $trainingGroup = TrainingGroup::query()->create([
        'name' => 'Coach Class Category',
        'is_active' => true,
    ]);

    $this->actingAs($coachUser)
        ->get(route('admin.classes'))
        ->assertOk();

    $this->actingAs($coachUser)
        ->post(route('admin.groups.store'), [
            'name' => 'Coach Created Class',
            'training_group_id' => $trainingGroup->id,
            'class_type' => 'reguler',
            'schedule_mode' => 'weekly',
            'branch_id' => $branch->branch_id,
            'day_of_week' => 1,
            'start_time' => '16:00',
            'end_time' => '18:00',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    $group = Group::query()->where('group_name', 'Coach Created Class')->sole();

    expect((string) $group->coach_id)->toBe((string) $coach->coach_id)
        ->and($group->coaches()->where('coaches.coach_id', $coach->coach_id)->exists())->toBeTrue();

    Carbon::setTestNow();
});

it('prevents an unrelated coach from reading another class athlete roster', function () {
    [, $assignedCoach] = classAccessCoach('Assigned Class Coach');
    [$unrelatedUser] = classAccessCoach('Unrelated Class Coach');
    $branch = Branch::query()->create([
        'branch_name' => 'Class Athlete Scope Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $group = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'coach_id' => $assignedCoach->coach_id,
        'group_name' => 'Scoped Athlete Class',
        'class_type' => 'reguler',
        'schedule_mode' => 'weekly',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);
    $group->coaches()->attach($assignedCoach->coach_id);

    $this->actingAs($unrelatedUser)
        ->get(route('admin.groups.athletes', $group))
        ->assertForbidden();
});

it('keeps class update and deletion admin only at the route boundary', function () {
    expect(Route::getRoutes()->getByName('admin.classes')?->gatherMiddleware())
        ->toContain('role:admin,coach');
    expect(Route::getRoutes()->getByName('admin.groups.store')?->gatherMiddleware())
        ->toContain('role:admin,coach');
    expect(Route::getRoutes()->getByName('admin.groups.athletes')?->gatherMiddleware())
        ->toContain('role:admin,coach');
    expect(Route::getRoutes()->getByName('admin.groups.update')?->gatherMiddleware())
        ->toContain('role:admin');
    expect(Route::getRoutes()->getByName('admin.groups.destroy')?->gatherMiddleware())
        ->toContain('role:admin');
});
