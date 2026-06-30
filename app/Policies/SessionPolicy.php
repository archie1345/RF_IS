<?php

namespace App\Policies;

use App\Models\Session;
use App\Models\User;
use App\Services\SessionVisibilityService;

class SessionPolicy
{
    public function __construct(private readonly SessionVisibilityService $sessionVisibility)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCoach();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isCoach();
    }

    public function update(User $user, Session $session): bool
    {
        return $user->isAdmin() || $this->sessionVisibility->coachCanAccessSession($user, $session);
    }

    public function manageAttendance(User $user, Session $session): bool
    {
        return $this->update($user, $session);
    }

    public function join(User $user, Session $session): bool
    {
        return $user->isCoach() && $this->sessionVisibility->coachCanJoinSession($this->sessionVisibility->coachProfileIdFor($user), $session);
    }
}
