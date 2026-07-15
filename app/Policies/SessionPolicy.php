<?php

namespace App\Policies;

use App\Models\TrainingSession;
use App\Models\User;
use App\Services\SessionVisibilityService;

class SessionPolicy
{
    public function __construct(private readonly SessionVisibilityService $sessionVisibility) {}

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCoach();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isCoach();
    }

    public function update(User $user, TrainingSession $session): bool
    {
        return $user->isAdmin() || $this->sessionVisibility->coachCanAccessSession($user, $session);
    }

    public function manageAttendance(User $user, TrainingSession $session): bool
    {
        return $this->update($user, $session);
    }

    public function manageAttendanceQr(User $user, TrainingSession $session): bool
    {
        return $this->manageAttendance($user, $session);
    }

    public function join(User $user, TrainingSession $session): bool
    {
        return $user->isCoach() && $this->sessionVisibility->coachCanJoinSession($this->sessionVisibility->coachProfileIdFor($user), $session);
    }
}
