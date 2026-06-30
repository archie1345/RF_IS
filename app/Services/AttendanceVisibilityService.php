<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Coach;
use App\Models\Session;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AttendanceVisibilityService
{
    public function __construct(private readonly ParentChildContextService $childContext)
    {
    }

    public function scopedAttendanceQuery(Request $request): Builder
    {
        $user = $request->user();
        $query = Attendance::query();

        if (! $user || $user->isAdmin()) {
            return $query;
        }

        if ($user->isParent()) {
            return $query->whereIn('athlete_id', $this->childContext->visibleChildAthleteIds($request));
        }

        if ($user->isAthlete()) {
            return $query->where('athlete_id', $user->athleteProfile?->athlete_id);
        }

        if ($user->isCoach()) {
            return $query->whereIn('coach_session_id', $this->coachSessionIds($user));
        }

        return $query->whereRaw('1 = 0');
    }

    public function visibleSessionQuery(?User $user): Builder
    {
        $query = Session::query();

        if (! $user || $user->isAdmin()) {
            return $query;
        }

        if ($user->isCoach()) {
            $coachId = $user->coachProfile?->coach_id;

            return $query->where(function ($sessionQuery) use ($coachId): void {
                $sessionQuery->where('coach_id', $coachId);
                if ($coachId && Schema::hasTable('coach_session_coaches')) {
                    $sessionQuery->orWhereHas('coaches', fn ($coachQuery) => $coachQuery->where('coaches.coach_id', $coachId));
                }
            });
        }

        if ($user->isAthlete()) {
            $athlete = $user->athleteProfile;

            return $query
                ->when($athlete?->branch_id, fn ($sessionQuery) => $sessionQuery->where('branch_id', $athlete->branch_id))
                ->when($athlete?->group_id, fn ($sessionQuery) => $sessionQuery->where(function ($groupQuery) use ($athlete): void {
                    $groupQuery->whereNull('group_id')->orWhere('group_id', $athlete->group_id);
                }));
        }

        return $query;
    }

    public function userCanUpdate(?User $user, Attendance $attendance): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isCoach()) {
            return $attendance->session
                ? $this->coachCanAccessSession($user, $attendance->session)
                : false;
        }

        if ($user->isAthlete()) {
            return (string) $attendance->athlete_id === (string) $user->athleteProfile?->athlete_id;
        }

        return false;
    }

    public function coachCanAccessSession(User $user, Session $session): bool
    {
        $coachId = $user->coachProfile?->coach_id ?? Coach::query()->where('id', $user->id)->value('coach_id');
        if (! $coachId) {
            return false;
        }

        return (string) $session->coach_id === (string) $coachId
            || (Schema::hasTable('coach_session_coaches') && $session->coaches()->where('coaches.coach_id', $coachId)->exists());
    }

    public function coachSessionIds(User $user): array
    {
        if (! $user->isCoach()) {
            return [];
        }

        return $this->visibleSessionQuery($user)->pluck('csid')->all();
    }
}
