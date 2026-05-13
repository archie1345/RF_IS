<?php

use App\Models\User;

test('coach cannot promote themself to admin through account management', function () {
    $coach = User::factory()->create(['role' => 'coach']);

    $this->actingAs($coach)
        ->put(route('admin.accounts.update', $coach), [
            'name' => $coach->name,
            'email' => $coach->email,
            'roles' => ['admin', 'coach'],
            'status' => 'active',
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertForbidden();

    expect($coach->fresh()->isAdmin())->toBeFalse();
});
