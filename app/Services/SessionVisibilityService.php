<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class SessionVisibilityService
{
    public function hasCoachPivotTable(): bool
    {
        return Schema::hasTable('training_session_coaches');
    }

    public function visibleSessionsQuery(User $user): Builder
    {
        $query = TrainingSession::query();

        if ($user->isAdmin()) {
            return $query;
        }

        $coachId = $this->coachProfileIdFor($user);
        if (! $coachId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $sessionQuery) use ($coachId): void {
            $sessionQuery->where('coach_id', $coachId);

            if ($this->hasCoachPivotTable()) {
                $sessionQuery->orWhereHas(
                    'assignedCoaches',
                    fn (Builder $coachQuery): Builder => $coachQuery->where('coaches.coach_id', $coachId),
                );
            }
        });
    }

    public function coachProfileIdFor(?User $user): ?string
    {
        if (! $user || ! $user->isCoach()) {
            return null;
        }

        $coachId = $user->coachProfile?->coach_id ?? Coach::query()->where('id', $user->id)->value('coach_id');

        return $coachId ? (string) $coachId : null;
    }

    public function coachCanAccessSession(User $user, TrainingSession $session): bool
    {
        $coachId = $this->coachProfileIdFor($user);
        if (! $coachId) {
            return false;
        }

        return (string) $session->coach_id === $coachId
            || ($this->hasCoachPivotTable() && $session->assignedCoaches()->where('coaches.coach_id', $coachId)->exists());
    }

    public function coachCanJoinSession(?string $coachId, TrainingSession $session): bool
    {
        if (! $coachId || ! $this->hasCoachPivotTable()) {
            return false;
        }

        if ((string) $session->coach_id === $coachId) {
            return false;
        }

        return ! $session->assignedCoaches()
            ->where('coaches.coach_id', $coachId)
            ->exists();
    }

    public function resolveSessionCoachId(?User $user, mixed $fallbackCoachId): ?string
    {
        $coachId = $this->coachProfileIdFor($user);
        if ($coachId) {
            return $coachId;
        }

        return $fallbackCoachId ? (string) $fallbackCoachId : null;
    }
}
