<?php

use App\Mail\UserInvitationMail;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function lifecycleUser(array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'account_status' => User::ACCOUNT_STATUS_ACTIVE,
        'email_verified_at' => now(),
        'role' => 'athlete',
    ], $overrides));
}

function invitationFor(User $user, string $token = 'test-invitation-token', array $overrides = []): UserInvitation
{
    return UserInvitation::create(array_merge([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->addDay(),
    ], $overrides));
}

it('allows active users to log in', function () {
    $user = lifecycleUser(['password' => Hash::make('password')]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('blocks suspended users from logging in', function () {
    $user = lifecycleUser([
        'account_status' => User::ACCOUNT_STATUS_SUSPENDED,
        'password' => Hash::make('password'),
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('blocks invited users from normal login before accepting their invitation', function () {
    $user = lifecycleUser([
        'account_status' => User::ACCOUNT_STATUS_INVITED,
        'email_verified_at' => null,
        'password' => Hash::make('password'),
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('keeps invalid credentials failing normally', function () {
    $user = lifecycleUser(['password' => Hash::make('password')]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('logs out an already authenticated suspended user on the next request', function () {
    $user = lifecycleUser();

    $this->actingAs($user);
    $user->update(['account_status' => User::ACCOUNT_STATUS_SUSPENDED]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('blocks invited users from protected routes', function () {
    $user = lifecycleUser([
        'account_status' => User::ACCOUNT_STATUS_INVITED,
        'email_verified_at' => null,
    ]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('redirects active unverified users to the verification notice', function () {
    $user = lifecycleUser(['email_verified_at' => null]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

it('allows verified active users to access protected routes', function () {
    $user = lifecycleUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('sends verification notifications for active unverified users', function () {
    Notification::fake();
    $user = lifecycleUser(['email_verified_at' => null]);

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('home'));

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('admin-created invited users receive an invitation email without storing the raw token', function () {
    Mail::fake();
    $admin = lifecycleUser(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.accounts.store'), [
        'name' => 'Invited User',
        'email' => 'invited@example.com',
        'roles' => ['athlete'],
        'status' => User::ACCOUNT_STATUS_INVITED,
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect();

    $user = User::where('email', 'invited@example.com')->firstOrFail();
    expect($user->isInvited())->toBeTrue();

    $invitation = UserInvitation::where('user_id', $user->id)->firstOrFail();
    expect($invitation->token_hash)->toHaveLength(64)
        ->and($invitation->token_hash)->not->toContain('invited@example.com');

    Mail::assertSent(UserInvitationMail::class, fn (UserInvitationMail $mail) => $mail->user->is($user));
});

it('loads a valid invitation page and rejects invalid expired or accepted invitations', function () {
    $user = lifecycleUser(['account_status' => User::ACCOUNT_STATUS_INVITED, 'email_verified_at' => null]);
    invitationFor($user, 'valid-token');
    invitationFor($user, 'expired-token', ['expires_at' => now()->subMinute()]);
    invitationFor($user, 'accepted-token', ['accepted_at' => now()]);

    $this->get(route('invitations.show', 'valid-token'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/AcceptInvitation')->where('email', $user->email));

    $this->get(route('invitations.show', 'invalid-token'))->assertRedirect(route('login'));
    $this->get(route('invitations.show', 'expired-token'))->assertRedirect(route('login'));
    $this->get(route('invitations.show', 'accepted-token'))->assertRedirect(route('login'));
});

it('accepts an invitation once and activates the user with a verified email', function () {
    $user = lifecycleUser(['account_status' => User::ACCOUNT_STATUS_INVITED, 'email_verified_at' => null]);
    $invitation = invitationFor($user, 'accept-token');

    $this->post(route('invitations.accept', 'accept-token'), [
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user->refresh();
    $invitation->refresh();

    expect($user->isActiveAccount())->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('new-password-123', $user->password))->toBeTrue()
        ->and($invitation->accepted_at)->not->toBeNull();

    $this->assertAuthenticatedAs($user);
    auth()->logout();

    $this->post(route('invitations.accept', 'accept-token'), [
        'password' => 'another-password-123',
        'password_confirmation' => 'another-password-123',
    ])->assertSessionHasErrors('invitation');
});

it('resending an invitation invalidates the previous active invitation', function () {
    Mail::fake();
    $admin = lifecycleUser(['role' => 'admin']);
    $user = lifecycleUser(['account_status' => User::ACCOUNT_STATUS_INVITED, 'email_verified_at' => null]);
    $oldInvitation = invitationFor($user, 'old-token');

    $this->actingAs($admin)
        ->post(route('admin.accounts.invitation.resend', $user))
        ->assertRedirect();

    expect($oldInvitation->refresh()->invalidated_at)->not->toBeNull()
        ->and(UserInvitation::where('user_id', $user->id)->whereNull('invalidated_at')->whereNull('accepted_at')->count())->toBe(1);

    Mail::assertSent(UserInvitationMail::class, fn (UserInvitationMail $mail) => $mail->user->is($user));
});

it('lets invited users log in after accepting their invitation', function () {
    $user = lifecycleUser(['account_status' => User::ACCOUNT_STATUS_INVITED, 'email_verified_at' => null]);
    invitationFor($user, 'login-after-accept-token');

    $this->post(route('invitations.accept', 'login-after-accept-token'), [
        'password' => 'accepted-password-123',
        'password_confirmation' => 'accepted-password-123',
    ])->assertRedirect(route('dashboard', absolute: false));
    auth()->logout();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'accepted-password-123',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user->refresh());
});

it('enforces supported admin status transitions', function () {
    $admin = lifecycleUser(['role' => 'admin']);
    $activeUser = lifecycleUser();
    $suspendedUser = lifecycleUser(['account_status' => User::ACCOUNT_STATUS_SUSPENDED]);
    $invitedUser = lifecycleUser(['account_status' => User::ACCOUNT_STATUS_INVITED, 'email_verified_at' => null]);

    $payload = fn (User $user, string $status) => [
        'name' => $user->name,
        'email' => $user->email,
        'roles' => [$user->role],
        'status' => $status,
        'password' => '',
        'password_confirmation' => '',
    ];

    $this->actingAs($admin)
        ->put(route('admin.accounts.update', $activeUser), $payload($activeUser, User::ACCOUNT_STATUS_SUSPENDED))
        ->assertRedirect(route('admin.index'));
    expect($activeUser->refresh()->isSuspended())->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.accounts.update', $suspendedUser), $payload($suspendedUser, User::ACCOUNT_STATUS_ACTIVE))
        ->assertRedirect(route('admin.index'));
    expect($suspendedUser->refresh()->isActiveAccount())->toBeTrue();

    $this->actingAs($admin)
        ->put(route('admin.accounts.update', $invitedUser), $payload($invitedUser, User::ACCOUNT_STATUS_ACTIVE))
        ->assertSessionHasErrors('status');

    $this->actingAs($admin)
        ->put(route('admin.accounts.update', $suspendedUser, absolute: false), $payload($suspendedUser, User::ACCOUNT_STATUS_INVITED))
        ->assertSessionHasErrors('status');
});
