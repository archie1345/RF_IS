<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Event;
use App\Models\EventCoachRegistration;
use App\Models\EventRegistration;
use App\Models\Group;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;

function championshipIntegrityAthlete(string $name, Branch $branch, Group $group): array
{
    $user = User::factory()->create([
        'name' => $name,
        'role' => 'athlete',
        'email_verified_at' => now(),
    ]);
    $athlete = Athlete::query()->create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 165,
        'weight_kg' => 55,
        'geup' => 'GEUP_5',
    ]);

    return [$user, $athlete];
}

function championshipIntegrityCoach(string $name): array
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

beforeEach(function () {
    Carbon::setTestNow('2026-07-24 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('prevents duplicate athlete registrations and creates one linked bill', function () {
    $branch = Branch::query()->create(['branch_name' => 'Championship Branch A', 'location' => 'Malang']);
    $group = Group::query()->create(['group_name' => 'Championship Group A']);
    [$athleteUser, $athlete] = championshipIntegrityAthlete('Registered Athlete', $branch, $group);
    $event = Event::query()->create([
        'e_name' => 'Integrity Open',
        'e_date' => today()->addWeek(),
        'location' => 'Malang',
        'entry_fee' => 250000,
        'max_slots' => 10,
        'status' => 'SCHEDULED',
    ]);
    $payload = [
        'athlete_id' => $athlete->athlete_id,
        'event_id' => $event->event_id,
        'category' => 'KYORUGI',
        'team_contingent' => 'Rhino Fighter',
    ];

    $this->actingAs($athleteUser)
        ->post(route('championships.registrations.store'), $payload)
        ->assertRedirect();

    $this->actingAs($athleteUser)
        ->post(route('championships.registrations.store'), $payload)
        ->assertSessionHasErrors('athlete_id');

    expect(EventRegistration::query()
        ->where('event_id', $event->event_id)
        ->where('athlete_id', $athlete->athlete_id)
        ->count())->toBe(1);
    expect(Payment::query()
        ->where('payment_type', 'CHAMPIONSHIP')
        ->whereIn('reference_id', EventRegistration::query()->where('event_id', $event->event_id)->pluck('evrid'))
        ->count())->toBe(1);
});

it('refuses registration after event capacity is reached', function () {
    $branch = Branch::query()->create(['branch_name' => 'Championship Branch B', 'location' => 'Malang']);
    $group = Group::query()->create(['group_name' => 'Championship Group B']);
    [$firstUser, $firstAthlete] = championshipIntegrityAthlete('First Athlete', $branch, $group);
    [$secondUser, $secondAthlete] = championshipIntegrityAthlete('Second Athlete', $branch, $group);
    $event = Event::query()->create([
        'e_name' => 'One Slot Open',
        'e_date' => today()->addWeek(),
        'location' => 'Malang',
        'entry_fee' => 100000,
        'max_slots' => 1,
        'status' => 'SCHEDULED',
    ]);

    $this->actingAs($firstUser)->post(route('championships.registrations.store'), [
        'athlete_id' => $firstAthlete->athlete_id,
        'event_id' => $event->event_id,
        'category' => 'POOMSAE',
    ])->assertRedirect();

    $this->actingAs($secondUser)->post(route('championships.registrations.store'), [
        'athlete_id' => $secondAthlete->athlete_id,
        'event_id' => $event->event_id,
        'category' => 'POOMSAE',
    ])->assertSessionHasErrors('event_id');

    expect(EventRegistration::query()->where('event_id', $event->event_id)->count())->toBe(1);
});

it('allows only assigned event coaches to view the roster and record results', function () {
    $branch = Branch::query()->create(['branch_name' => 'Championship Branch C', 'location' => 'Malang']);
    $group = Group::query()->create(['group_name' => 'Championship Group C']);
    [, $athlete] = championshipIntegrityAthlete('Result Athlete', $branch, $group);
    [$assignedUser, $assignedCoach] = championshipIntegrityCoach('Assigned Event Coach');
    [$unassignedUser] = championshipIntegrityCoach('Unassigned Event Coach');
    $event = Event::query()->create([
        'e_name' => 'Managed Open',
        'e_date' => today(),
        'location' => 'Malang',
        'entry_fee' => 0,
        'max_slots' => 10,
        'status' => 'ONGOING',
    ]);
    $registration = EventRegistration::query()->create([
        'athlete_id' => $athlete->athlete_id,
        'event_id' => $event->event_id,
        'category' => 'KYORUGI',
        'status' => 'PENDING',
    ]);
    EventCoachRegistration::query()->create([
        'event_id' => $event->event_id,
        'coach_id' => $assignedCoach->coach_id,
        'role' => 'Official Coach',
    ]);

    $this->actingAs($unassignedUser)
        ->get(route('championships.export', $event))
        ->assertForbidden();
    $this->actingAs($unassignedUser)
        ->post(route('championships.registrations.result', $registration), ['medal' => 'GOLD'])
        ->assertForbidden();

    $this->actingAs($assignedUser)
        ->post(route('championships.registrations.result', $registration), ['medal' => 'GOLD'])
        ->assertRedirect();

    $this->assertDatabaseHas('event_registrations', [
        'evrid' => $registration->evrid,
        'result_medal' => 'GOLD',
        'status' => 'CONFIRMED',
    ]);
});
