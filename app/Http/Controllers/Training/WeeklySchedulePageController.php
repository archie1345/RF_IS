<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Training\Concerns\BuildsTrainingPayloads;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\User;
use App\Models\WeeklyTrainingSchedule;
use App\Services\ActiveRoleContextService;
use App\Support\Domain\BeltRank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class WeeklySchedulePageController extends Controller
{
    use BuildsTrainingPayloads;

    public function __construct(private readonly ActiveRoleContextService $activeRoleContext) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = $this->activeRoleContext->activeRole($request, $user);
        $coachId = $role === 'coach' ? $user?->coachProfile?->coach_id : null;
        $canManageSchedule = $role === 'admin' || ($role === 'coach' && $coachId !== null);
        $weekStart = $request->date('from')?->startOfDay() ?? now()->startOfWeek();
        $weekEnd = $request->date('to')?->endOfDay() ?? $weekStart->copy()->endOfWeek();
        $weeklySchedulesQuery = $this->weeklyScheduleQuery($weekStart, $weekEnd);
        $athletes = $this->visibleAthletes($request, $role);

        if ($role === 'athlete') {
            $athlete = $athletes->first();
            $weeklySchedules = ! $athlete
                ? collect()
                : $weeklySchedulesQuery
                    ->where('is_active', true)
                    ->where('branch_id', $athlete->branch_id)
                    ->where(function (Builder $query) use ($athlete): void {
                        $query->whereNull('dedicated_athlete_id')
                            ->orWhere('dedicated_athlete_id', $athlete->athlete_id);
                    })
                    ->get()
                    ->filter(fn (WeeklyTrainingSchedule $schedule) => $this->athleteCanJoinSchedule($athlete, $schedule))
                    ->values();
        } elseif ($role === 'parent') {
            $branchIds = $athletes->pluck('branch_id')->filter()->unique()->values();
            $weeklySchedules = $athletes->isEmpty()
                ? collect()
                : $weeklySchedulesQuery
                    ->where('is_active', true)
                    ->whereIn('branch_id', $branchIds)
                    ->get()
                    ->filter(fn (WeeklyTrainingSchedule $schedule) => $athletes->contains(
                        fn (Athlete $athlete) => $this->athleteCanJoinSchedule($athlete, $schedule),
                    ))
                    ->values();
        } elseif ($role === 'coach') {
            $weeklySchedules = ! $coachId
                ? collect()
                : $weeklySchedulesQuery
                    ->where(function (Builder $query) use ($coachId): void {
                        $query->where('coach_id', $coachId)
                            ->orWhereHas('group', fn (Builder $group) => $group->assignedToCoach($coachId));
                    })
                    ->get();
        } elseif ($role === 'admin') {
            $weeklySchedules = $weeklySchedulesQuery->get();
        } else {
            $weeklySchedules = collect();
        }

        $scheduleModels = $weeklySchedules->keyBy(
            fn (WeeklyTrainingSchedule $schedule): string => (string) $schedule->weekly_training_schedule_id,
        );
        $schedulePayload = $this->weeklySchedulePayload($request, $weeklySchedules)
            ->map(function (array $row) use ($role, $athletes, $scheduleModels): array {
                /** @var WeeklyTrainingSchedule|null $schedule */
                $schedule = $scheduleModels->get((string) $row['id']);
                $childNames = $role === 'parent' && $schedule
                    ? $athletes
                        ->filter(fn (Athlete $athlete): bool => $this->athleteCanJoinSchedule($athlete, $schedule))
                        ->map(fn (Athlete $athlete): string => $athlete->user?->name ?? 'Atlet')
                        ->unique()
                        ->values()
                        ->join(', ')
                    : null;

                return [
                    ...$row,
                    'child' => $childNames,
                    'athletes' => filled($row['dedicated_athlete'] ?? null)
                        ? (string) $row['dedicated_athlete']
                        : null,
                ];
            })
            ->values();

        return Inertia::render('WeeklySchedulePage', [
            'title' => 'Jadwal Latihan',
            'subtitle' => $role === 'parent'
                ? 'Jadwal semua anak yang terhubung; gunakan nama anak pada setiap jadwal untuk membedakannya.'
                : 'Jadwal latihan rutin',
            'canManageSchedule' => $canManageSchedule,
            'currentCoachId' => $coachId,
            'weekRange' => [
                'from' => $weekStart->toDateString(),
                'to' => $weekEnd->toDateString(),
            ],
            'weeklySchedules' => $schedulePayload,
            'branchOptions' => $canManageSchedule ? $this->branchOptions($role, $user) : [],
            'groupOptions' => $canManageSchedule ? $this->groupOptions($role, $user) : [],
            'coachOptions' => $role === 'admin' ? $this->coachOptions() : [],
            'athleteOptions' => $canManageSchedule
                ? $this->authorizedAthleteQuery($role, $user)
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

    private function visibleAthletes(Request $request, string $role): Collection
    {
        if ($role === 'athlete') {
            $athlete = $request->user()?->athleteProfile;
            $athlete?->loadMissing(['user:id,name', 'group:group_id,min_belt']);

            return collect([$athlete])->filter();
        }

        if ($role === 'parent') {
            return $request->user()
                ->children()
                ->with(['user:id,name', 'group:group_id,min_belt'])
                ->get();
        }

        return collect();
    }

    private function authorizedAthleteQuery(string $role, ?User $user): Builder
    {
        $query = Athlete::query();

        if ($role === 'admin') {
            return $query;
        }

        if ($role !== 'coach') {
            return $query->whereRaw('1 = 0');
        }

        $coachId = $user?->coachProfile?->coach_id;
        if (! $coachId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $athletes) use ($coachId): void {
            $athletes->whereHas('group', fn (Builder $group) => $group->assignedToCoach($coachId))
                ->orWhereHas('privateGroups', fn (Builder $group) => $group->assignedToCoach($coachId));
        });
    }

    private function branchOptions(string $role, ?User $user): Collection
    {
        $query = Branch::query()->where('is_active', true);

        if ($role === 'coach') {
            $coachId = $user?->coachProfile?->coach_id;
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

    private function groupOptions(string $role, ?User $user): Collection
    {
        $query = Group::query()->where('is_active', true);

        if ($role === 'coach') {
            $query->assignedToCoach($user?->coachProfile?->coach_id);
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
