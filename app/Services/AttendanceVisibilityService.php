<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Coach;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AttendanceVisibilityService
{
    public function __construct(private readonly ParentChildContextService $childContext) {}

    public function scopedAttendanceQuery(Request $request, ?string $mode = null): Builder
    {
        $user = $request->user();
        $query = Attendance::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $mode = $this->resolveMode($user, $mode);

        if ($mode === 'admin') {
            return $query;
        }

        if ($mode === 'parent') {
            return $query->whereIn('athlete_id', $this->childContext->visibleChildAthleteIds($request));
        }

        if ($mode === 'athlete') {
            return $query->where('athlete_id', $user->athleteProfile?->athlete_id);
        }

        if ($mode === 'coach') {
            return $query->whereIn('training_session_id', $this->coachSessionIds($user));
        }

        return $query->whereRaw('1 = 0');
    }

    public function visibleSessionQuery(?User $user, ?string $mode = null): Builder
    {
        $query = TrainingSession::query();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        $mode = $this->resolveMode($user, $mode);

        if ($mode === 'admin') {
            return $query;
        }

        if ($mode === 'coach') {
            $coachId = $user->coachProfile?->coach_id;

            return $query->where(function ($sessionQuery) use ($coachId): void {
                $sessionQuery->where('coach_id', $coachId);
                if ($coachId && Schema::hasTable('training_session_coaches')) {
                    $sessionQuery->orWhereHas('assignedCoaches', fn ($coachQuery) => $coachQuery->where('coaches.coach_id', $coachId));
                }
            });
        }

        if ($mode === 'parent') {
            $children = $this->childContext->childrenFor($user);
            $childAthleteIds = $children->pluck('athlete_id')->map(fn ($id) => (string) $id)->all();
            $childBranchIds = $children->pluck('branch_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
            $childGroupIds = $children->pluck('group_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();

            if ($children->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            return $query
                ->whereIn('branch_id', $childBranchIds)
                ->where(function ($sessionQuery) use ($childAthleteIds, $childGroupIds): void {
                    $sessionQuery->whereNull('group_id');

                    if ($childGroupIds !== []) {
                        $sessionQuery->orWhereIn('group_id', $childGroupIds);
                    }

                    if ($childAthleteIds !== []) {
                        $sessionQuery->orWhereHas('group.privateAthletes', fn ($athleteQuery) => $athleteQuery->whereIn('athletes.athlete_id', $childAthleteIds));
                    }
                });
        }

        if ($mode === 'athlete') {
            $athlete = $user->athleteProfile;

            return $query
                ->when($athlete?->branch_id, fn ($sessionQuery) => $sessionQuery->where('branch_id', $athlete->branch_id))
                ->when($athlete?->group_id, fn ($sessionQuery) => $sessionQuery->where(function ($groupQuery) use ($athlete): void {
                    $groupQuery->whereNull('group_id')->orWhere('group_id', $athlete->group_id);
                }));
        }

        return $query->whereRaw('1 = 0');
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
            return $attendance->trainingSession
                ? $this->coachCanAccessSession($user, $attendance->trainingSession)
                : false;
        }

        return false;
    }

    public function coachCanAccessSession(User $user, TrainingSession $session): bool
    {
        $coachId = $user->coachProfile?->coach_id ?? Coach::query()->where('id', $user->id)->value('coach_id');
        if (! $coachId) {
            return false;
        }

        return (string) $session->coach_id === (string) $coachId
            || (Schema::hasTable('training_session_coaches') && $session->assignedCoaches()->where('coaches.coach_id', $coachId)->exists());
    }

    public function coachSessionIds(User $user): array
    {
        if (! $user->isCoach()) {
            return [];
        }

        return $this->visibleSessionQuery($user, 'coach')->pluck('training_session_id')->all();
    }

    private function resolveMode(User $user, ?string $mode): string
    {
        $resolved = strtolower(trim((string) ($mode ?: $user->primaryRole())));

        return $user->hasRole($resolved) ? $resolved : '__none__';
    }
}
