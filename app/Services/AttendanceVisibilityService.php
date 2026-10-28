<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Coach;
use App\Models\TrainingSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Decides which attendance rows and sessions a user may see.
 *
 * Passing no `$mode` scopes by the UNION of every role the account holds
 * (admin sees everything). Passing a mode narrows the result to that single
 * role, which pages use for their stateless `?mode=` view switch.
 */
class AttendanceVisibilityService
{
    public function scopedAttendanceQuery(Request $request, ?string $mode = null): Builder
    {
        $user = $request->user();
        $query = Attendance::query();

        if (! $user) {
            return $this->emptyQuery($query);
        }

        $roles = $this->rolesFor($user, $mode);

        if ($roles === []) {
            return $this->emptyQuery($query);
        }

        if (in_array('admin', $roles, true)) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user, $roles): void {
            foreach ($roles as $role) {
                $scope->orWhere(fn (Builder $roleScope) => $this->constrainAttendance($roleScope, $user, $role));
            }
        });
    }

    public function visibleSessionQuery(?User $user, ?string $mode = null): Builder
    {
        $query = TrainingSession::query();

        if (! $user) {
            return $this->emptyQuery($query);
        }

        $roles = $this->rolesFor($user, $mode);

        if ($roles === []) {
            return $this->emptyQuery($query);
        }

        if (in_array('admin', $roles, true)) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user, $roles): void {
            foreach ($roles as $role) {
                $scope->orWhere(fn (Builder $roleScope) => $this->constrainSessions($roleScope, $user, $role));
            }
        });
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

    /** @return array<int, string> roles to scope by; empty when the requested mode is not held */
    private function rolesFor(User $user, ?string $mode): array
    {
        $mode = strtolower(trim((string) $mode));

        if ($mode === '') {
            return $user->assignedRoles();
        }

        return $user->hasRole($mode) ? [$mode] : [];
    }

    private function constrainAttendance(Builder $query, User $user, string $role): void
    {
        if ($role === 'athlete') {
            $athleteId = $user->athleteProfile?->athlete_id;

            $athleteId ? $query->where('athlete_id', $athleteId) : $query->whereRaw('1 = 0');

            return;
        }

        if ($role === 'coach') {
            $sessionIds = $this->coachSessionIds($user);

            $sessionIds !== [] ? $query->whereIn('training_session_id', $sessionIds) : $query->whereRaw('1 = 0');

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function constrainSessions(Builder $query, User $user, string $role): void
    {
        if ($role === 'coach') {
            $coachId = $user->coachProfile?->coach_id;

            if (! $coachId) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(function (Builder $sessionQuery) use ($coachId): void {
                $sessionQuery
                    ->where('coach_id', $coachId)
                    ->orWhereHas(
                        'assignedCoaches',
                        fn (Builder $coachQuery): Builder => $coachQuery->where('coaches.coach_id', $coachId),
                    );
            });

            return;
        }

        if ($role === 'athlete') {
            $athlete = $user->athleteProfile;

            if (! $athlete || ! $athlete->branch_id) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query
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

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private function emptyQuery(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}
