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
use Inertia\Testing\AssertableInertia as Assert;

function managedEventAthlete(string $name, ?string $coachId = null): array
{
    $branch = Branch::create([
        'branch_name' => $name.' Branch',
        'location' => 'Jakarta',
    ]);
    $group = Group::create([
        'branch_id' => $branch->branch_id,
        'coach_id' => $coachId,
        'group_name' => $name.' Group',
        'is_active' => true,
    ]);
    $user = User::factory()->create(['name' => $name, 'role' => 'athlete']);
    $athlete = Athlete::create([
        'id' => $user->id,
        'branch_id' => $branch->branch_id,
        'group_id' => $group->group_id,
        'height_cm' => 170,
        'weight_kg' => 60,
        'nik_hash' => hash('sha256', $name.'-managed-nik'),
        'bpjs_hash' => hash('sha256', $name.'-managed-bpjs'),
        'geup' => 'GEUP_1',
    ]);

    return [$user, $athlete, $group];
}

function managedEvent(string $status = 'SCHEDULED'): Event
{
    return Event::create([
        'e_name' => 'Managed Event '.$status,
        'e_date' => $status === 'COMPLETED' ? now()->subWeek()->toDateString() : now()->addMonth()->toDateString(),
        'registration_deadline' => $status === 'COMPLETED' ? now()->subWeeks(2) : now()->addWeek(),
        'location' => 'Jakarta',
        'level' => 'NATIONAL',
        'entry_fee' => 200000,
        'max_slots' => 1,
        'status' => $status,
    ]);
}

test('admin can add a missing athlete after an event without creating an unexpected bill', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [, $athlete] = managedEventAthlete('Post Event Athlete');
    $event = managedEvent('COMPLETED');

    $this->actingAs($admin)
        ->post(route('championships.participants.store', $event), [
            'athlete_id' => $athlete->athlete_id,
            'category' => 'KYORUGI',
            'classification' => 'Senior',
            'class_name' => 'Under 68 kg',
            'division' => 'Male',
            'team_contingent' => 'Rhino Fighter',
            'create_payment' => false,
        ])
        ->assertRedirect();

    $registration = EventRegistration::query()->firstOrFail();
    expect($registration->event_id)->toBe($event->event_id)
        ->and($registration->athlete_id)->toBe($athlete->athlete_id)
        ->and($registration->status)->toBe('CONFIRMED');

    expect(Payment::query()
        ->where('payment_type', 'CHAMPIONSHIP')
        ->where('reference_id', $registration->evrid)
        ->exists())->toBeFalse();
});

test('assigned coach can add an athlete from their class while registration is open', function () {
    $coachUser = User::factory()->create(['role' => 'coach', 'name' => 'Assigned Coach']);
    $coach = Coach::create(['id' => $coachUser->id, 'status' => 'active']);
    [, $athlete] = managedEventAthlete('Coach Athlete', $coach->coach_id);
    $event = managedEvent();
    EventCoachRegistration::create([
        'event_id' => $event->event_id,
        'coach_id' => $coach->coach_id,
        'role' => 'Head coach',
    ]);

    $this->actingAs($coachUser)
        ->get(route('championships.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canAddRegistration', true)
            ->has('registrationAthleteOptions', 1)
            ->where('registrationAthleteOptions.0.value', $athlete->athlete_id));

    $this->post(route('championships.participants.store', $event), [
        'athlete_id' => $athlete->athlete_id,
        'category' => 'POOMSAE',
        'classification' => 'Junior',
        'class_name' => 'Individual',
        'division' => 'Male',
        'team_contingent' => 'Rhino Fighter',
        'create_payment' => true,
    ])->assertRedirect();

    $registration = EventRegistration::query()->firstOrFail();
    expect($registration->status)->toBe('PENDING');
    $this->assertDatabaseHas('payments', [
        'reference_id' => $registration->evrid,
        'payment_type' => 'CHAMPIONSHIP',
        'total_amount' => 200000,
    ]);
});

test('coach cannot add an athlete outside assigned classes or after deadline', function () {
    $coachUser = User::factory()->create(['role' => 'coach']);
    $coach = Coach::create(['id' => $coachUser->id, 'status' => 'active']);
    [, $outsideAthlete] = managedEventAthlete('Outside Athlete');
    $event = managedEvent();
    EventCoachRegistration::create([
        'event_id' => $event->event_id,
        'coach_id' => $coach->coach_id,
        'role' => 'Coach',
    ]);

    $payload = [
        'athlete_id' => $outsideAthlete->athlete_id,
        'category' => 'KYORUGI',
        'classification' => 'Senior',
        'class_name' => 'Under 80 kg',
        'division' => 'Male',
        'team_contingent' => 'Rhino Fighter',
        'create_payment' => false,
    ];

    $this->actingAs($coachUser)
        ->post(route('championships.participants.store', $event), $payload)
        ->assertForbidden();

    $event->update(['registration_deadline' => now()->subMinute()]);
    [, $assignedAthlete] = managedEventAthlete('Late Assigned Athlete', $coach->coach_id);
    $payload['athlete_id'] = $assignedAthlete->athlete_id;

    $this->from(route('championships.show', $event))
        ->post(route('championships.participants.store', $event), $payload)
        ->assertRedirect(route('championships.show', $event))
        ->assertSessionHasErrors('athlete_id');
});
