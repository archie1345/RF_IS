<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['web', 'auth', 'role:coach'])
        ->get('/_tests/multi-role/coach', fn () => response()->json(['ok' => true]));

    Route::middleware(['web', 'auth', 'role:admin,coach'])
        ->get('/_tests/multi-role/staff', fn () => response()->json(['ok' => true]));
});

test('a multi-role user can open a route for any assigned role without switching session state', function () {
    $user = User::factory()->create(['role' => 'athlete']);
    $user->roleAssignments()->createMany([
        ['role' => 'athlete'],
        ['role' => 'coach'],
    ]);

    $this->actingAs($user)
        ->get('/_tests/multi-role/coach')
        ->assertOk()
        ->assertJsonPath('ok', true);
});

test('a multi-role user can open a route that accepts more than one assigned role', function () {
    $user = User::factory()->create(['role' => 'coach']);
    $user->roleAssignments()->createMany([
        ['role' => 'admin'],
        ['role' => 'coach'],
    ]);

    $this->actingAs($user)
        ->get('/_tests/multi-role/staff')
        ->assertOk()
        ->assertJsonPath('ok', true);
});

test('a route remains forbidden when none of its roles are assigned to the user', function () {
    $user = User::factory()->create(['role' => 'athlete']);
    $user->roleAssignments()->create(['role' => 'athlete']);

    $this->actingAs($user)
        ->get('/_tests/multi-role/coach')
        ->assertForbidden();
});
