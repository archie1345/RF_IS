<?php

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Group;
use App\Models\User;
use App\Models\UserAchievement;
use Inertia\Testing\AssertableInertia as Assert;

function makeHistoricalEventRegistration(): array
{
    $branch = Branch::create([
        'branch_name' => 'History Event Branch',
        'location' => 'Bandung',
    ]);
    $group = Group::create(['group_name' => 'History Event Group']);
    $athleteUser = User::factory()->create([
        'name' => 'Historical Athlete',
        'role' => 'athlete',
    ]);
    $athlete = Athlete::create([
        'id' => $athleteUser->id,
        'group_id' => $group->group_id,
        'branch_id' => $branch->branch_id,
        'height_cm' => 168,
        'weight_kg' => 58,
        'nik_hash' => hash('sha256', 'history athlete nik'),
        'bpjs_hash' => hash('sha256', 'history athlete bpjs'),
        'geup' => 'GEUP_2',
    ]);
    $event = Event::create([
        'e_name' => 'Historical Open & UKT',
        'e_date' => now()->subMonth(),
        'location' => 'Bandung',
        'level' => 'REGIONAL',
        'entry_fee' => 250000,
        'max_slots' => 100,
        'organizer' => 'RF IS',
        'status' => 'COMPLETED',
    ]);
    $registration = EventRegistration::create([
        'athlete_id' => $athlete->athlete_id,
        'event_id' => $event->event_id,
        'category' => 'KYORUGI',
        'classification' => 'PRESTASI',
        'class_name' => 'U-58 KG',
        'division' => 'SENIOR',
        'team_contingent' => 'Rhino Fighter',
        'status' => 'CONFIRMED',
        'result_medal' => 'BRONZE',
        'result_class_name' => 'U-58 KG',
        'result_division' => 'SENIOR',
        'result_category' => 'KYORUGI',
    ]);

    return [$athleteUser, $event, $registration];
}

test('admin can view past event and UKT results from history', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [, $event, $registration] = makeHistoricalEventRegistration();

    $this->actingAs($admin)
        ->get(route('admin.events.history'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('EventHistoryPage')
            ->where('events.0.id', $event->event_id)
            ->where('events.0.results_count', 1)
            ->where('events.0.registrations.0.id', $registration->evrid)
            ->where('events.0.registrations.0.result_medal', 'BRONZE')
            ->where('events.0.registrations.0.result_class_name', 'U-58 KG'));
});

test('admin can correct a historical result without recreating the event', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    [$athleteUser, $event, $registration] = makeHistoricalEventRegistration();

    $this->actingAs($admin)
        ->post(route('championships.registrations.result', $registration), [
            'medal' => 'GOLD',
            'class_name' => 'U-63 KG',
            'division' => 'SENIOR',
            'category' => 'KYORUGI',
        ])
        ->assertRedirect();

    $registration->refresh();

    expect($registration->event_id)->toBe($event->event_id)
        ->and($registration->result_medal)->toBe('GOLD')
        ->and($registration->result_class_name)->toBe('U-63 KG');

    $achievement = UserAchievement::query()
        ->where('event_registration_id', $registration->evrid)
        ->firstOrFail();

    expect($achievement->user_id)->toBe($athleteUser->id)
        ->and($achievement->medal)->toBe('GOLD')
        ->and($achievement->class_name)->toBe('U-63 KG')
        ->and($achievement->is_auto_recorded)->toBeTrue();
});
