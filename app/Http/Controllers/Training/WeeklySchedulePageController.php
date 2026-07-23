<?php

namespace App\Http\Controllers\Training;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Training\Concerns\BuildsTrainingPayloads;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
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
        $role = $user?->primaryRole() ?? 'athlete';
        $canManageSchedule = in_array($role, ['admin', 'coach'], true);
        $weekStart = $request->date('from')?->startOfDay() ?? now()->startOfWeek();
        $weekEnd = $request->date('to')?->endOfDay() ?? $weekStart->copy()->endOfWeek();
        $weeklySchedulesQuery = $this->weeklyScheduleQuery($weekStart, $weekEnd);
        $athletes = $this->visibleAthletes($request, $role);

        if ($role === 'athlete') {
            $athlete = $athletes->first();
            if (! $athlete) {
                $weeklySchedules = collect();
            } else {
                $weeklySchedules = $weeklySchedulesQuery
                    ->where('is_active', true)
                    ->where('branch_id', $athlete->branch_id)
                    ->where(function ($query) use ($athlete): void {
                        $query->whereNull('dedicated_athlete_id')
                            ->orWhere('dedicated_athlete_id', $athlete->athlete_id);
                    })
                    ->get()
                    ->filter(fn (WeeklyTrainingSchedule $schedule) => $this->athleteCanJoinSchedule($athlete, $schedule))
                    ->values();
            }
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
        } else {
            $weeklySchedules = $weeklySchedulesQuery->get();
        }

        return Inertia::render('WeeklySchedulePage', [
            'title' => 'Jadwal Latihan',
            'subtitle' => $role === 'parent'
                ? 'Jadwal latihan untuk anak yang terhubung'
                : 'Jadwal latihan rutin',
            'canManageSchedule' => $canManageSchedule,
            'currentCoachId' => $role === 'coach' ? $user?->coachProfile?->coach_id : null,
            'weekRange' => [
                'from' => $weekStart->toDateString(),
                'to' => $weekEnd->toDateString(),
            ],
            'weeklySchedules' => $this->weeklySchedulePayload($request, $weeklySchedules),
            'branchOptions' => $canManageSchedule
                ? Branch::query()
                    ->where('is_active', true)
                    ->orderBy('branch_name')
                    ->get()
                    ->map(fn (Branch $branch) => [
                        'value' => $branch->branch_id,
                        'label' => $branch->branch_name,
                    ])->values()
                : [],
            'groupOptions' => $canManageSchedule
                ? Group::query()
                    ->orderBy('group_name')
                    ->get()
                    ->map(fn (Group $group) => [
                        'value' => $group->group_id,
                        'label' => $group->group_name,
                    ])->values()
                : [],
            'coachOptions' => $role === 'admin' ? $this->coachOptions() : [],
            'athleteOptions' => $canManageSchedule
                ? $this->authorizedAthleteQuery($request)
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
            return collect([$request->user()?->athleteProfile])->filter();
        }

        if ($role === 'parent') {
            return $request->user()
                ->children()
                ->with(['group:group_id,min_belt'])
                ->get();
        }

        return collect();
    }

    private function authorizedAthleteQuery(Request $request): Builder
    {
        $query = Athlete::query();
        $user = $request->user();

        if (! $user || $user->isAdmin()) {
            return $query;
        }

        if ($user->isCoach()) {
            $coachId = $user->coachProfile?->coach_id;
            $managedGroups = Group::query()
                ->where('coach_id', $coachId)
                ->get(['group_id', 'branch_id']);
            $groupIds = $managedGroups->pluck('group_id')->filter()->values();
            $branchIds = $managedGroups->pluck('branch_id')->filter()->unique()->values();

            if ($groupIds->isEmpty() && $branchIds->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function ($query) use ($groupIds, $branchIds): void {
                if ($groupIds->isNotEmpty()) {
                    $query->whereIn('group_id', $groupIds);
                }

                if ($branchIds->isNotEmpty()) {
                    $groupIds->isNotEmpty()
                        ? $query->orWhereIn('branch_id', $branchIds)
                        : $query->whereIn('branch_id', $branchIds);
                }
            });
        }

        return $query->whereRaw('1 = 0');
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

        return BeltRank::eligible($athlete->geup, $schedule->group?->min_belt);
    }
}
