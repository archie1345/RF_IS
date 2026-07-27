<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Event;
use App\Models\EventCoachRegistration;
use App\Models\EventRegistration;
use App\Models\Group;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Inertia\Testing\AssertableInertia as Assert;

it('shows athletes the full participant count and limits coach metrics to assigned events', function () {
    $user = User::factory()->create([
        'name' => 'Championship Multi Role',
        'role' => 'coach',
        'email_verified_at' => now(),
    ]);
    $coach = Coach::query()->create([
        'id' => $user->id,
        'status' => 'active',
    ]);
    $branch = Branch::query()->create([
        'branch_name' => 'Championship Role Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $group = Group::query()->create([
        'branch_id' => $branch->branch_id,
        'coach_id' => $coach->coach_id,
        'group_name' => 'Championship Role Group',
        'class_type' => 'reguler',
        'schedule_mode' => 'weekly',
        'day_of_week' => 1,
        'start_time' => '16:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);
    $athlete = Athlete::query()->create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 170,
        'weight_kg' => 65,
        'geup' => 'GEUP_1',
        'nik_hash' => hash('sha256', 'champ-role-nik'),
        'bpjs_hash' => hash('sha256', 'champ-role-bpjs'),
    ]);
    UserRoleAssignment::query()->create(['user_id' => $user->id, 'role' => 'coach']);
    UserRoleAssignment::query()->create(['user_id' => $user->id, 'role' => 'athlete']);

    $assignedEvent = Event::query()->create([
        'e_name' => 'Assigned Championship',
        'e_date' => today()->addWeek()->toDateString(),
        'location' => 'Malang',
        'entry_fee' => 100000,
        'max_slots' => 20,
        'status' => 'SCHEDULED',
    ]);
    $unassignedEvent = Event::query()->create([
        'e_name' => 'Unassigned Championship',
        'e_date' => today()->addWeeks(2)->toDateString(),
        'location' => 'Surabaya',
        'entry_fee' => 100000,
        'max_slots' => 20,
        'status' => 'SCHEDULED',
    ]);
    EventCoachRegistration::query()->create([
        'event_id' => $assignedEvent->event_id,
        'coach_id' => $coach->coach_id,
        'role' => 'Coach',
    ]);
    EventRegistration::query()->create([
        'event_id' => $assignedEvent->event_id,
        'athlete_id' => $athlete->athlete_id,
        'category' => 'KYORUGI',
        'status' => 'CONFIRMED',
    ]);

    $otherUser = User::factory()->create(['role' => 'athlete']);
    $otherAthlete = Athlete::query()->create([
        'id' => $otherUser->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 160,
        'weight_kg' => 55,
        'geup' => 'GEUP_5',
        'nik_hash' => hash('sha256', 'other-champ-role-nik'),
        'bpjs_hash' => hash('sha256', 'other-champ-role-bpjs'),
    ]);
    EventRegistration::query()->create([
        'event_id' => $unassignedEvent->event_id,
        'athlete_id' => $otherAthlete->athlete_id,
        'category' => 'POOMSAE',
        'status' => 'CONFIRMED',
    ]);

    $this->actingAs($user)
        ->put(route('role-context.update'), ['role' => 'athlete'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('championships.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isAthlete', true)
            ->where('canRegister', true)
            ->has('athletes', 1)
            ->where('athletes.0.value', $athlete->athlete_id)
            ->where('metrics.1.value', '2'));

    $this->put(route('role-context.update'), ['role' => 'coach'])
        ->assertRedirect(route('dashboard'));

    $this->get(route('championships.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('isAthlete', false)
            ->where('canRegister', false)
            ->has('athletes', 0)
            ->where('metrics.1.value', '1'));
});
