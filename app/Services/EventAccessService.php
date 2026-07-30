<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;

class EventAccessService
{
    public function canManage(?User $user, Event $event): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isCoach()) {
            return false;
        }

        $coachId = $user->coachProfile?->coach_id;
        if (! $coachId) {
            return false;
        }

        return $event->coachRegistrations()
            ->where('coach_id', $coachId)
            ->exists();
    }
}
