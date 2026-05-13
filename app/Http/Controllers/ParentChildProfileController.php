<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Response;

class ParentChildProfileController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        return app(ProfileAccessController::class)->show($request, $user);
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $viewer = $request->user();

        abort_unless($viewer?->isParent() && $this->parentOwnsAthleteUser($viewer, $user), 403);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        ActivityLogger::log($request, 'parent.child.password.updated', 'parent', 'Parent updated linked child password', $user, [
            'child_user_id' => $user->id,
        ]);

        return back();
    }

    private function parentOwnsAthleteUser(User $parentUser, User $childUser): bool
    {
        $athlete = $childUser->athleteProfile;

        if (! $athlete) {
            return false;
        }

        return $parentUser->children()
            ->where('athletes.athlete_id', $athlete->athlete_id)
            ->exists();
    }
}
