<?php

use App\Models\User;
use App\Services\ActiveRoleContextService;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['web', 'auth', 'role:coach'])
        ->get('/_tests/multi-role/coach', fn () => response()->json([
            'active_role' => session(ActiveRoleContextService::SESSION_KEY),
        ]));

    Route::middleware(['web', 'auth', 'role:admin,coach'])
        ->get('/_tests/multi-role/staff', fn () => response()->json([
            'active_role' => session(ActiveRoleContextService::SESSION_KEY),
        ]));
});

test('a multi-role user can open a route for any assigned role and the role context switches automatically', function () {
    $user = User::factory()->create(['role' => 'athlete']);
    $user->roleAssignments()->createMany([
        ['role' => 'athlete'],
        ['role' => 'coach'],
    ]);

    $this->actingAs($user)
        ->withSession([ActiveRoleContextService::SESSION_KEY => 'athlete'])
        ->get('/_tests/multi-role/coach')
        ->assertOk()
        ->assertJsonPath('active_role', 'coach');
});

test('an already valid active role is preserved when a route accepts more than one assigned role', function () {
    $user = User::factory()->create(['role' => 'coach']);
    $user->roleAssignments()->createMany([
        ['role' => 'admin'],
        ['role' => 'coach'],
    ]);

    $this->actingAs($user)
        ->withSession([ActiveRoleContextService::SESSION_KEY => 'coach'])
        ->get('/_tests/multi-role/staff')
        ->assertOk()
        ->assertJsonPath('active_role', 'coach');
});

test('a route remains forbidden when none of its roles are assigned to the user', function () {
    $user = User::factory()->create(['role' => 'athlete']);
    $user->roleAssignments()->create(['role' => 'athlete']);

    $this->actingAs($user)
        ->withSession([ActiveRoleContextService::SESSION_KEY => 'athlete'])
        ->get('/_tests/multi-role/coach')
        ->assertForbidden();
});
