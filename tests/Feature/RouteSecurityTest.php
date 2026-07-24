<?php

use App\Models\User;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

it('requires authentication for the API user endpoint', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('returns the authenticated active API user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonMissingPath('password');
});

it('rejects suspended accounts on API routes without requiring a web session', function () {
    $user = User::factory()->create([
        'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/user')
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

it('protects sensitive web mutations with role middleware', function () {
    $adminRoutes = [
        'admin.index',
        'users.index',
        'athletes.update',
        'announcements.store',
        'payments.store',
        'payments.transactions.store',
        'payments.proof.review',
        'championships.events.store',
        'championships.payments.settle',
    ];

    foreach ($adminRoutes as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull();
        expect($route->gatherMiddleware())->toContain('role:admin');
    }

    expect(Route::getRoutes()->getByName('parent.children.index')?->gatherMiddleware())
        ->toContain('role:parent');
    expect(Route::getRoutes()->getByName('championships.registrations.store')?->gatherMiddleware())
        ->toContain('role:admin,parent,athlete');
    expect(Route::getRoutes()->getByName('championships.registrations.result')?->gatherMiddleware())
        ->toContain('role:admin,coach');
});
