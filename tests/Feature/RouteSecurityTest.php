<?php

use App\Models\User;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

it('requires authentication for management API routes', function () {
    $this->getJson('/api/branches')->assertUnauthorized();
    $this->postJson('/api/users', [])->assertUnauthorized();
});

it('requires the admin role for management API routes', function () {
    $athlete = User::factory()->create(['role' => 'athlete']);
    Sanctum::actingAs($athlete);

    $this->getJson('/api/branches')->assertForbidden();
});

it('allows an active admin to access management API routes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Sanctum::actingAs($admin);

    $this->getJson('/api/branches')->assertOk();
});

it('rejects suspended accounts on API routes without requiring a web session', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
    ]);
    Sanctum::actingAs($admin);

    $this->getJson('/api/branches')
        ->assertForbidden()
        ->assertJsonPath('message', 'This account has been suspended. Please contact an administrator.');
});

it('registers one throttled QR attendance submission route', function () {
    $matchingRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (IlluminateRoute $route): bool =>
            in_array('POST', $route->methods(), true)
            && $route->uri() === 'attendance/scan/{token}'
        )
        ->values();

    expect($matchingRoutes)->toHaveCount(1);
    expect($matchingRoutes->first()->gatherMiddleware())->toContain('throttle:qr-scan');
});

it('protects the admin web namespace with role middleware', function () {
    $route = Route::getRoutes()->getByName('admin.index');

    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('role:admin');
});
