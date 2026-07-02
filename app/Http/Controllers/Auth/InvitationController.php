<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Users\AcceptUserInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function show(string $token, AcceptUserInvitation $acceptUserInvitation): Response|RedirectResponse
    {
        try {
            $invitation = $this->findInvitation($token);
            $acceptUserInvitation->ensureAcceptable($invitation);
        } catch (ValidationException $exception) {
            return redirect()->route('login')->withErrors($exception->errors());
        }

        $invitation->load('user:id,name,email,account_status');

        return Inertia::render('auth/AcceptInvitation', [
            'token' => $token,
            'email' => $invitation->user?->email,
            'name' => $invitation->user?->name,
            'expiresAt' => $invitation->expires_at?->toIso8601String(),
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $token, AcceptUserInvitation $acceptUserInvitation): RedirectResponse
    {
        $invitation = $this->findInvitation($token);
        $user = $acceptUserInvitation->handle($invitation, $request->validated('password'));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false))
            ->with('status', 'Invitation accepted. Your account is now active.');
    }

    private function findInvitation(string $token): UserInvitation
    {
        $invitation = UserInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation) {
            throw ValidationException::withMessages(['invitation' => 'This invitation link is invalid.']);
        }

        return $invitation;
    }
}
