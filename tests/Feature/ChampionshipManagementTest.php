<?php

use App\Models\Event;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admin can open a championship detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $event = Event::create([
        'e_name' => 'Jakarta Open',
        'e_date' => now()->toDateString(),
        'location' => 'Jakarta',
        'level' => 'INTERNATIONAL',
        'entry_fee' => 100000,
        'max_slots' => 5,
        'status' => 'SCHEDULED',
    ]);

    $this->actingAs($admin)
        ->get(route('championships.show', $event))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ChampionshipDetailPage')
            ->where('isAdmin', true)
            ->where('event.id', $event->event_id)
            ->where('event.name', 'Jakarta Open'));
});
