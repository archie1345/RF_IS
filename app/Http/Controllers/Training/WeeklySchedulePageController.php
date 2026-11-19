<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Training\Concerns\BuildsTrainingPayloads;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\User;
use App\Models\WeeklyTrainingSchedule;
use App\Support\Domain\BeltRank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class WeeklySchedulePageController extends Controller
{
    use BuildsTrainingPayloads;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user?->isAdmin() ?? false;
        $isCoach = $user?->isCoach() ?? false;
        $isAthlete = $user?->isAthlete() ?? false;
        $coachId = $isCoach ? $user?->coachProfile?->coach_id : null;
        $canManageSchedule = $isAdmin || ($isCoach && $coachId !== null);
        $weekStart = $request->date('from')?->startOfDay() ?? now()->startOfWeek();
        $weekEnd = $request->date('to')?->endOfDay() ?? $weekStart->copy()->endOfWeek();

        if ($isAdmin) {
            $weeklySchedules = $this->weeklyScheduleQuery($weekStart, $weekEnd)->get();
        } else {
            $schedules = collect();

            if ($isCoach && $coachId) {
                $schedules = $schedules->merge(
                    $this->weeklyScheduleQuery($weekStart, $weekEnd)
                        ->where(function (Builder $query) use ($coachId): void {
                            $query->where('coach_id', $coachId)
                                ->orWhereHas('group', fn (Builder $group) => $group->assignedToCoach($coachId));
                        })
                        ->get(),
                );
            }

            if ($isAthlete) {
                $athlete = $user?->athleteProfile;
                $athlete?->loadMissing(['user:id,name', 'group:group_id,min_belt']);
                if ($athlete) {
                    $schedules = $schedules->merge(
                        $this->weeklyScheduleQuery($weekStart, $weekEnd)
                            ->where('is_active', true)
                            ->where('branch_id', $athlete->branch_id)
                            ->where(function (Builder $query) use ($athlete): void {
                                $query->whereNull('dedicated_athlete_id')
                                    ->orWhere('dedicated_athlete_id', $athlete->athlete_id);
                            })
                            ->get()
                            ->filter(fn (WeeklyTrainingSchedule $schedule) => $this->athleteCanJoinSchedule($athlete, $schedule)),
                    );
                }
            }

            $weeklySchedules = $schedules
                ->unique('weekly_training_schedule_id')
                ->values();
        }

        $schedulePayload = $this->weeklySchedulePayload($request, $weeklySchedules)
            ->map(fn (array $row): array => [
                ...$row,
                'child' => null,
                'athletes' => filled($row['dedicated_athlete'] ?? null)
                    ? (string) $row['dedicated_athlete']
                    : null,
            ])
            ->values();

        return Inertia::render('WeeklySchedulePage', [
            'title' => 'Jadwal Latihan',
            'subtitle' => 'Jadwal latihan rutin',
            'canManageSchedule' => $canManageSchedule,
            'currentCoachId' => $coachId,
            'weekRange' => [
                'from' => $weekStart->toDateString(),
                'to' => $weekEnd->toDateString(),
            ],
            'weeklySchedules' => $schedulePayload,
            'branchOptions' => $canManageSchedule ? $this->branchOptions($user) : [],
            'groupOptions' => $canManageSchedule ? $this->groupOptions($user) : [],
            'coachOptions' => $isAdmin ? $this->coachOptions() : [],
            'athleteOptions' => $canManageSchedule
                ? $this->authorizedAthleteQuery($user)
                    ->with('user:id,name')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Athlete $athlete) => [
                        'value' => $athlete->athlete_id,
                        'label' => $athlete->user?->name ?? ('Atlet #'.$athlete->athlete_id),
                    ])->values()
                : [],
        ]);
    }

    private function authorizedAthleteQuery(?User $user): Builder
    {
        $query = Athlete::query();

        if ($user?->isAdmin()) {
            return $query;
        }

        if (! $user?->isCoach()) {
            return $query->whereRaw('1 = 0');
        }

        $coachId = $user->coachProfile?->coach_id;
        if (! $coachId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $athletes) use ($coachId): void {
            $athletes->whereHas('group', fn (Builder $group) => $group->assignedToCoach($coachId))
                ->orWhereHas('privateGroups', fn (Builder $group) => $group->assignedToCoach($coachId));
        });
    }

    private function branchOptions(?User $user): Collection
    {
        $query = Branch::query()->where('is_active', true);

        if (! $user?->isAdmin() && $user?->isCoach()) {
            $coachId = $user->coachProfile?->coach_id;
            $query->whereIn(
                'branch_id',
                Group::query()->assignedToCoach($coachId)->where('is_active', true)->select('branch_id'),
            );
        }

        return $query
            ->orderBy('branch_name')
            ->get()
            ->map(fn (Branch $branch) => [
                'value' => $branch->branch_id,
                'label' => $branch->branch_name,
            ])
            ->values();
    }

    private function groupOptions(?User $user): Collection
    {
        $query = Group::query()->where('is_active', true);

        if (! $user?->isAdmin() && $user?->isCoach()) {
            $query->assignedToCoach($user->coachProfile?->coach_id);
        }

        return $query
            ->orderBy('group_name')
            ->get()
            ->map(fn (Group $group) => [
                'value' => $group->group_id,
                'label' => $group->group_name,
            ])
            ->values();
    }

    private function athleteCanJoinSchedule(Athlete $athlete, WeeklyTrainingSchedule $schedule): bool
    {
        if ((string) $athlete->branch_id !== (string) $schedule->branch_id) {
            return false;
        }

        if ($schedule->dedicated_athlete_id !== null) {
            return (string) $athlete->athlete_id === (string) $schedule->dedicated_athlete_id;
        }

        if ($schedule->group_id === null) {
            return true;
        }

        if ((string) $athlete->group_id === (string) $schedule->group_id) {
            return true;
        }

        if ($schedule->group?->privateAthletes?->contains(
            fn (Athlete $privateAthlete): bool => (string) $privateAthlete->athlete_id === (string) $athlete->athlete_id,
        )) {
            return true;
        }

        return BeltRank::eligible($athlete->geup, $schedule->group?->min_belt);
    }
}
