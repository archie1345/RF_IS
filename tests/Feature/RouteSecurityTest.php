<?php

use App\Models\User;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
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
        ->assertJsonPath('message', 'This account is not active. Please contact an administrator.');
});

it('authenticates valid Sanctum personal access tokens and rejects malformed or revoked bearer tokens', function () {
    $user = User::factory()->create([
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $plainTextToken = $user->createToken('audit-test-token')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonPath('uuid', $user->uuid)
        ->assertJsonMissingPath('password');

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', 'Bearer invalid-malformed-token-value')
        ->getJson('/api/user')
        ->assertUnauthorized();

    $user->tokens()->delete();
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
        ->getJson('/api/user')
        ->assertUnauthorized();
});

it('enforces role and coach-scope authorization on the group athletes API endpoint', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $unrelatedCoach = User::factory()->create([
        'role' => 'coach',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    \App\Models\Coach::query()->create(['id' => $unrelatedCoach->id, 'status' => 'active']);
    $athlete = User::factory()->create([
        'role' => 'athlete',
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
    ]);
    $branch = \App\Models\Branch::query()->create([
        'branch_name' => 'API Group Branch',
        'location' => 'Malang',
        'is_active' => true,
    ]);
    $group = \App\Models\Group::query()->create([
        'branch_id' => $branch->branch_id,
        'group_name' => 'API Group Class',
        'class_type' => 'reguler',
        'is_active' => true,
    ]);

    $this->getJson(route('api.admin.groups.athletes', $group))
        ->assertUnauthorized();

    $this->actingAs($athlete)
        ->getJson(route('api.admin.groups.athletes', $group))
        ->assertForbidden();

    $this->actingAs($unrelatedCoach)
        ->getJson(route('api.admin.groups.athletes', $group))
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson(route('api.admin.groups.athletes', $group))
        ->assertOk()
        ->assertJsonStructure(['athletes']);
});

it('registers one role-scoped throttled QR attendance submission route', function () {
    $matchingRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (IlluminateRoute $route): bool => in_array('POST', $route->methods(), true)
            && $route->uri() === 'attendance/scan/{token}'
        )
        ->values();

    expect($matchingRoutes)->toHaveCount(1);
    expect($matchingRoutes->first()->gatherMiddleware())
        ->toContain('role:athlete')
        ->toContain('throttle:qr-scan');
});

it('ignores forwarded client IP headers from an untrusted peer', function (): void {
    $request = Request::create('/', 'GET', server: [
        'REMOTE_ADDR' => '203.0.113.10',
        'HTTP_X_FORWARDED_FOR' => '198.51.100.20',
    ]);

    $response = app(TrustProxies::class)->handle(
        $request,
        fn (Request $request) => response()->json(['ip' => $request->ip()]),
    );

    expect($response->getData(true)['ip'])->toBe('203.0.113.10');
});

it('protects sensitive web mutations with role middleware', function () {
    $adminRoutes = [
        'admin.index',
        'admin.whatsapp-template.update',
        'users.index',
        'users.athlete-record.update',
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

    $adminCoachRoutes = [
        'training-schedules.store',
        'training-schedules.update',
        'training-schedules.destroy',
        'sessions.index',
        'sessions.store',
        'sessions.update',
        'sessions.destroy',
        'sessions.attendance',
        'sessions.attendance-qr.store',
        'sessions.attendance-qr.destroy',
        'sessions.coach-attendance.store',
        'sessions.coach-attendance.update',
        'sessions.coach-attendance.destroy',
        'attendance.store',
        'attendance.bulk-update',
        'attendance.update',
    ];

    foreach ($adminCoachRoutes as $routeName) {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull();
        expect($route->gatherMiddleware())->toContain('role:admin,coach');
    }

    expect(Route::getRoutes()->getByName('sessions.join')?->gatherMiddleware())
        ->toContain('role:coach');
    expect(Route::getRoutes()->getByName('attendance.coach-attend')?->gatherMiddleware())
        ->toContain('role:coach');
    expect(Route::getRoutes()->getByName('payments.proof.submit')?->gatherMiddleware())
        ->toContain('role:athlete');
    expect(Route::getRoutes()->getByName('championships.registrations.store')?->gatherMiddleware())
        ->toContain('role:admin,athlete');
    expect(Route::getRoutes()->getByName('championships.registrations.result')?->gatherMiddleware())
        ->toContain('role:admin,coach');
});
