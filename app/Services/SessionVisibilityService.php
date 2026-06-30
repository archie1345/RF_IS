<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class SessionVisibilityService
{
    public function hasCoachPivotTable(): bool
    {
        return Schema::hasTable('coach_session_coaches');
    }

    public function coachProfileIdFor(?User $user): ?string
    {
        if (! $user || ! $user->isCoach()) {
            return null;
        }

        $coachId = $user->coachProfile?->coach_id ?? Coach::query()->where('id', $user->id)->value('coach_id');

        return $coachId ? (string) $coachId : null;
    }

    public function coachCanAccessSession(User $user, Session $session): bool
    {
        $coachId = $this->coachProfileIdFor($user);
        if (! $coachId) {
            return false;
        }

        return (string) $session->coach_id === $coachId
            || ($this->hasCoachPivotTable() && $session->coaches()->where('coaches.coach_id', $coachId)->exists());
    }

    public function coachCanJoinSession(?string $coachId, Session $session): bool
    {
        if (! $coachId) {
            return false;
        }

        return ! (
            (string) $session->coach_id === $coachId
            || ($this->hasCoachPivotTable() && $session->relationLoaded('coaches') && $session->coaches->contains('coach_id', $coachId))
        );
    }

    public function resolveSessionCoachId(?User $user, mixed $fallbackCoachId): ?string
    {
        $coachId = $this->coachProfileIdFor($user);
        if ($coachId) {
            return $coachId;
        }

        if ($fallbackCoachId) {
            return (string) $fallbackCoachId;
        }

        $firstCoachId = Coach::query()->orderBy('coach_id')->value('coach_id');

        return $firstCoachId ? (string) $firstCoachId : null;
    }
}
