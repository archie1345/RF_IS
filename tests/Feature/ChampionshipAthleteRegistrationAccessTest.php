<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Group;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createChampionshipAthlete(string $name): array
{
    $branch = Branch::create([
        'branch_name' => $name.' Branch',
        'location' => 'Jakarta',
    ]);
    $group = Group::create(['group_name' => $name.' Group']);
    $user = User::factory()->create([
        'name' => $name,
        'role' => 'athlete',
    ]);
    $athlete = Athlete::create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 170,
        'weight_kg' => 60,
        'nik_hash' => hash('sha256', $name.'-nik'),
        'bpjs_hash' => hash('sha256', $name.'-bpjs'),
        'geup' => 'GEUP_1',
    ]);

    return [$user, $athlete];
}

function createEditableChampionship(): Event
{
    return Event::create([
        'e_name' => 'Editable Open',
        'e_date' => now()->addMonth()->toDateString(),
        'registration_deadline' => now()->addWeek(),
        'location' => 'Jakarta',
        'level' => 'NATIONAL',
        'entry_fee' => 100000,
        'max_slots' => 24,
        'status' => 'SCHEDULED',
    ]);
}

test('athlete sees every participant but can edit only their own championship entry', function () {
    [$user, $athlete] = createChampionshipAthlete('Visible Athlete');
    [, $otherAthlete] = createChampionshipAthlete('Other Athlete');
    $event = createEditableChampionship();

    $ownRegistration = EventRegistration::create([
        'event_id' => $event->event_id,
        'athlete_id' => $athlete->athlete_id,
        'category' => 'KYORUGI',
        'classification' => 'Senior',
        'status' => 'PENDING',
    ]);
    $otherRegistration = EventRegistration::create([
        'event_id' => $event->event_id,
        'athlete_id' => $otherAthlete->athlete_id,
        'category' => 'POOMSAE',
        'classification' => 'Junior',
        'status' => 'PENDING',
    ]);

    $this->actingAs($user)
        ->get(route('championships.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ChampionshipDetailPage')
            ->where('event.registration_open', true)
            ->has('athleteRows', 2)
            ->where('athleteRows.0.registration_id', $ownRegistration->evrid)
            ->where('athleteRows.0.is_own_registration', true)
            ->where('athleteRows.0.can_edit_registration', true)
            ->where('athleteRows.1.registration_id', $otherRegistration->evrid)
            ->where('athleteRows.1.is_own_registration', false)
            ->where('athleteRows.1.can_edit_registration', false));
});

test('registered athlete receives edit state instead of another registration action', function () {
    [$user, $athlete] = createChampionshipAthlete('Registered Athlete');
    $event = createEditableChampionship();
    $registration = EventRegistration::create([
        'event_id' => $event->event_id,
        'athlete_id' => $athlete->athlete_id,
        'category' => 'KYORUGI',
        'classification' => 'Senior',
        'class_name' => 'Under 68 kg',
        'division' => 'Male',
        'team_contingent' => 'Rhino Fighter',
        'status' => 'PENDING',
    ]);

    $this->actingAs($user)
        ->get(route('championships.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ChampionshipsPage')
            ->where('rows.0.registration_open', true)
            ->where('rows.0.can_edit_registration', true)
            ->where('rows.0.my_registration.registration_id', $registration->evrid)
            ->where('rows.0.my_registration.class_name', 'Under 68 kg'));
});

test('athlete can update their own entry before the admin deadline but not another athlete entry', function () {
    [$user, $athlete] = createChampionshipAthlete('Owner Athlete');
    [, $otherAthlete] = createChampionshipAthlete('Protected Athlete');
    $event = createEditableChampionship();
    $ownRegistration = EventRegistration::create([
        'event_id' => $event->event_id,
        'athlete_id' => $athlete->athlete_id,
        'category' => 'KYORUGI',
        'status' => 'PENDING',
    ]);
    $otherRegistration = EventRegistration::create([
        'event_id' => $event->event_id,
        'athlete_id' => $otherAthlete->athlete_id,
        'category' => 'POOMSAE',
        'status' => 'PENDING',
    ]);

    $payload = [
        'category' => 'FREESTYLE',
        'classification' => 'Senior',
        'class_name' => 'Individual',
        'division' => 'Male',
        'team_contingent' => 'Rhino Fighter A',
    ];

    $this->actingAs($user)
        ->put(route('championships.registrations.update', $ownRegistration), $payload)
        ->assertRedirect();

    expect($ownRegistration->refresh())
        ->category->toBe('FREESTYLE')
        ->classification->toBe('Senior')
        ->class_name->toBe('Individual')
        ->division->toBe('Male')
        ->team_contingent->toBe('Rhino Fighter A');

    $this->actingAs($user)
        ->put(route('championships.registrations.update', $otherRegistration), $payload)
        ->assertForbidden();
});

test('athlete championship entry becomes read only after the admin deadline', function () {
    [$user, $athlete] = createChampionshipAthlete('Late Athlete');
    $event = createEditableChampionship();
    $event->update(['registration_deadline' => now()->subMinute()]);
    $registration = EventRegistration::create([
        'event_id' => $event->event_id,
        'athlete_id' => $athlete->athlete_id,
        'category' => 'KYORUGI',
        'classification' => 'Junior',
        'status' => 'PENDING',
    ]);

    $this->actingAs($user)
        ->from(route('championships.show', $event))
        ->put(route('championships.registrations.update', $registration), [
            'category' => 'POOMSAE',
            'classification' => 'Senior',
            'class_name' => 'Individual',
            'division' => 'Female',
            'team_contingent' => 'Rhino Fighter',
        ])
        ->assertRedirect(route('championships.show', $event))
        ->assertSessionHasErrors('registration');

    expect($registration->refresh())
        ->category->toBe('KYORUGI')
        ->classification->toBe('Junior');

    $this->actingAs($user)
        ->get(route('championships.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('event.registration_open', false)
            ->where('athleteRows.0.can_edit_registration', false));
});

test('admin can set the championship registration deadline', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $eventDate = now()->addMonth()->startOfDay();
    $deadline = $eventDate->copy()->subDays(3)->setTime(17, 30);

    $this->actingAs($admin)
        ->post(route('championships.events.store'), [
            'name' => 'Deadline Open',
            'date' => $eventDate->toDateString(),
            'registration_deadline' => $deadline->format('Y-m-d H:i:s'),
            'location' => 'Bandung',
            'entry_fee' => 150000,
            'max_slots' => 30,
            'level' => 'NATIONAL',
            'status' => 'SCHEDULED',
        ])
        ->assertRedirect(route('championships.index'));

    $event = Event::query()->where('e_name', 'Deadline Open')->firstOrFail();
    expect($event->registration_deadline?->format('Y-m-d H:i:s'))->toBe($deadline->format('Y-m-d H:i:s'));
});
