<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Coach;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AttendanceVisibilityService
{
    public function __construct(private readonly ParentChildContextService $childContext) {}

    public function scopedAttendanceQuery(Request $request, ?string $mode = null): Builder
    {
        $user = $request->user();
        $query = Attendance::query();

        if (! $user) {
            return $this->emptyQuery($query);
        }

        $mode = $this->resolveMode($user, $mode);

        if ($mode === 'admin') {
            return $query;
        }

        if ($mode === 'parent') {
            return $query->whereIn('athlete_id', $this->childContext->visibleChildAthleteIds($request));
        }

        if ($mode === 'athlete') {
            $athleteId = $user->athleteProfile?->athlete_id;

            return $athleteId
                ? $query->where('athlete_id', $athleteId)
                : $this->emptyQuery($query);
        }

        if ($mode === 'coach') {
            $sessionIds = $this->coachSessionIds($user);

            return $sessionIds !== []
                ? $query->whereIn('training_session_id', $sessionIds)
                : $this->emptyQuery($query);
        }

        return $this->emptyQuery($query);
    }

    public function visibleSessionQuery(?User $user, ?string $mode = null): Builder
    {
        $query = TrainingSession::query();

        if (! $user) {
            return $this->emptyQuery($query);
        }

        $mode = $this->resolveMode($user, $mode);

        if ($mode === 'admin') {
            return $query;
        }

        if ($mode === 'coach') {
            $coachId = $user->coachProfile?->coach_id;

            if (! $coachId) {
                return $this->emptyQuery($query);
            }

            return $query->where(function (Builder $sessionQuery) use ($coachId): void {
                $sessionQuery
                    ->where('coach_id', $coachId)
                    ->orWhereHas(
                        'assignedCoaches',
                        fn (Builder $coachQuery): Builder => $coachQuery->where('coaches.coach_id', $coachId),
                    );
            });
        }

        if ($mode === 'parent') {
            $children = $this->childContext->childrenFor($user);
            $childAthleteIds = $children->pluck('athlete_id')->map(fn ($id) => (string) $id)->all();
            $childBranchIds = $children->pluck('branch_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
            $childGroupIds = $children->pluck('group_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();

            if ($children->isEmpty() || $childBranchIds === [] || $childAthleteIds === []) {
                return $this->emptyQuery($query);
            }

            return $query
                ->whereIn('branch_id', $childBranchIds)
                ->where(function (Builder $visibility) use ($childAthleteIds, $childGroupIds): void {
                    $visibility->whereIn('dedicated_athlete_id', $childAthleteIds)
                        ->orWhere(function (Builder $sharedSessions) use ($childAthleteIds, $childGroupIds): void {
                            $sharedSessions->whereNull('dedicated_athlete_id')
                                ->where(function (Builder $groups) use ($childAthleteIds, $childGroupIds): void {
                                    $groups->whereNull('group_id');

                                    if ($childGroupIds !== []) {
                                        $groups->orWhereIn('group_id', $childGroupIds);
                                    }

                                    $groups->orWhereHas(
                                        'group.privateAthletes',
                                        fn (Builder $athletes): Builder => $athletes->whereIn('athletes.athlete_id', $childAthleteIds),
                                    );
                                });
                        });
                });
        }

        if ($mode === 'athlete') {
            $athlete = $user->athleteProfile;

            if (! $athlete || ! $athlete->branch_id) {
                return $this->emptyQuery($query);
            }

            return $query
                ->where('branch_id', $athlete->branch_id)
                ->where(function (Builder $visibility) use ($athlete): void {
                    $visibility->where('dedicated_athlete_id', $athlete->athlete_id)
                        ->orWhere(function (Builder $sharedSessions) use ($athlete): void {
                            $sharedSessions->whereNull('dedicated_athlete_id')
                                ->where(function (Builder $groups) use ($athlete): void {
                                    $groups->whereNull('group_id');

                                    if ($athlete->group_id) {
                                        $groups->orWhere('group_id', $athlete->group_id);
                                    }

                                    $groups->orWhereHas(
                                        'group.privateAthletes',
                                        fn (Builder $athletes): Builder => $athletes->where('athletes.athlete_id', $athlete->athlete_id),
                                    );
                                });
                        });
                });
        }

        return $this->emptyQuery($query);
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
            || $session->assignedCoaches()->where('coaches.coach_id', $coachId)->exists();
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

    private function emptyQuery(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}
