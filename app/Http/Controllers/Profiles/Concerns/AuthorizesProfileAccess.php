<?php

namespace App\Http\Controllers\Profiles\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

trait AuthorizesProfileAccess
{
    protected function authorizeProfileAccess(Request $request, User $user): void
    {
        $viewer = $request->user();

        if ($viewer?->isAdmin()) {
            return;
        }

        if ($viewer?->isParent() && $this->parentOwnsAthleteUser($viewer, $user)) {
            return;
        }

        abort(403);
    }

    protected function parentOwnsAthleteUser(User $parentUser, User $childUser): bool
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
