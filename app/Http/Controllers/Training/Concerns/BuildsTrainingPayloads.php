<?php

namespace App\Http\Controllers\Training\Concerns;

use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\WeeklyTrainingSchedule;
use App\Support\Domain\BeltRank;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait BuildsTrainingPayloads
{
    private function weeklyScheduleQuery(CarbonInterface $weekStart, CarbonInterface $weekEnd)
    {
        return WeeklyTrainingSchedule::query()
            ->whereHas('branch', fn ($query) => $query->where('is_active', true))
            ->where(function ($query): void {
                $query->whereNull('group_id')
                    ->orWhereHas('group', fn ($groupQuery) => $groupQuery->where('is_active', true));
            })
            ->with([
                'branch',
                'group' => fn ($query) => $query->with(['trainingGroup', 'privateAthletes.user'])->withCount('athletes'),
                'dedicatedAthlete.user',
                'coach.user',
            ])
            ->withCount([
                'trainingSessions as generated_sessions_count' => fn ($query) => $query->whereBetween('session_date', [
                    $weekStart->toDateString(),
                    $weekEnd->toDateString(),
                ]),
            ])
            ->orderBy('day_of_week')
            ->orderBy('start_time');
    }

    private function weeklySchedulePayload(Request $request, Collection $weeklySchedules)
    {
        return $weeklySchedules->map(fn (WeeklyTrainingSchedule $schedule) => [
            'id' => $schedule->weekly_training_schedule_id,
            'title' => $schedule->title,
            'branch_id' => $schedule->branch_id,
            'branch' => $schedule->branch?->branch_name ?? 'Belum ada lokasi',
            'group_id' => $schedule->group_id,
            'dedicated_athlete_id' => $schedule->dedicated_athlete_id,
            'dedicated_athlete' => $schedule->group && ($schedule->group->class_type ?? null) === 'private'
                ? $schedule->group->privateAthletes->map(fn (Athlete $athlete) => $athlete->user?->name)->filter()->join(', ')
                : $schedule->dedicatedAthlete?->user?->name,
            'session_type' => $schedule->session_type ?? 'reguler',
            'group' => $schedule->group?->group_name ?? 'All groups',
            'training_group_id' => $schedule->group?->training_group_id,
            'training_group' => $schedule->group?->trainingGroup?->name,
            'coach_id' => $schedule->coach_id,
            'coach' => $schedule->coach?->user?->name ?? 'Belum ada coach',
            'day_of_week' => $schedule->day_of_week,
            'day_label' => $this->dayName((int) $schedule->day_of_week),
            'start_time' => $schedule->start_time ? substr((string) $schedule->start_time, 0, 5) : '',
            'end_time' => $schedule->end_time ? substr((string) $schedule->end_time, 0, 5) : '',
            'location' => $schedule->location,
            'latitude' => $schedule->branch?->latitude,
            'longitude' => $schedule->branch?->longitude,
            'google_maps_url' => $schedule->branch?->google_maps_url,
            'is_active' => (bool) $schedule->is_active,
            'generated_sessions_count' => $schedule->generated_sessions_count,
            'can_manage' => $this->canManageSchedule($request, $schedule),
            'class_type' => $schedule->group?->class_type,
            'min_belt' => $schedule->group?->min_belt,
            'min_belt_label' => BeltRank::label($schedule->group?->min_belt),
            'athletes_count' => $schedule->group?->athletes_count,
        ])->values();
    }

    private function branchPayload(Branch $branch): array
    {
        return [
            'id' => $branch->branch_id,
            'name' => $branch->branch_name,
            'location' => $branch->location,
            'address' => $branch->address,
            'city' => $branch->city,
            'province' => $branch->province,
            'latitude' => $branch->latitude,
            'longitude' => $branch->longitude,
            'google_maps_url' => $branch->google_maps_url,
            'attendance_radius_meters' => $branch->attendance_radius_meters ?? 100,
            'timezone' => $branch->timezone ?? 'Asia/Jakarta',
            'is_active' => (bool) ($branch->is_active ?? true),
            'groups_count' => $branch->groups_count ?? 0,
            'athletes_count' => $branch->athletes_count ?? 0,
        ];
    }

    private function groupPayload(Collection $groups, Collection $weeklySchedules)
    {
        $scheduleByGroup = $weeklySchedules->whereNotNull('group_id')->keyBy('group_id');

        return $groups->map(function (Group $group) use ($scheduleByGroup): array {
            $schedule = $scheduleByGroup->get($group->group_id);
            $scheduleMode = $group->schedule_mode ?? 'weekly';
            $singleSessionDate = $group->single_session_date?->format('Y-m-d');
            $privateAthletes = $group->privateAthletes ?? collect();
            $isArchived = $this->oneDayClassArchived($group, $singleSessionDate);

            return [
                'id' => $group->group_id,
                'name' => $group->group_name,
                'training_group_id' => $group->training_group_id,
                'training_group' => $group->trainingGroup?->name,
                'class_type' => $group->class_type ?? 'reguler',
                'schedule_mode' => $scheduleMode,
                'single_session_date' => $singleSessionDate,
                'branch_id' => $group->branch_id,
                'branch' => $group->branch?->branch_name ?? 'Belum ada lokasi',
                'coach_id' => $group->coach_id,
                'coach' => $group->coach?->user?->name ?? 'Belum ada coach',
                'dedicated_athlete_ids' => $privateAthletes->pluck('athlete_id')->map(fn ($id) => (string) $id)->values(),
                'dedicated_athlete' => $privateAthletes->map(fn (Athlete $athlete) => $athlete->user?->name)->filter()->join(', '),
                'day_of_week' => $group->day_of_week,
                'day_label' => $scheduleMode === 'one_day' && $singleSessionDate
                    ? $this->dayName((int) ($group->day_of_week ?? 1)).' · '.$singleSessionDate
                    : $this->dayName((int) ($group->day_of_week ?? 1)),
                'start_time' => $group->start_time ? substr((string) $group->start_time, 0, 5) : '',
                'end_time' => $group->end_time ? substr((string) $group->end_time, 0, 5) : '',
                'min_belt' => $group->min_belt,
                'min_belt_label' => BeltRank::label($group->min_belt),
                'description' => $group->description,
                'athletes_count' => ($group->class_type ?? null) === 'private'
                    ? $privateAthletes->count()
                    : ($group->athletes_count ?? 0),
                'athletes' => ($group->class_type ?? null) === 'private'
                    ? $this->classAthletePayload($privateAthletes)
                    : $this->classAthletePayload($group->athletes ?? collect()),
                'is_active' => (bool) ($group->is_active ?? true),
                'is_archived' => $isArchived,
                'weekly_schedule_id' => $schedule?->weekly_training_schedule_id,
                'weekly_schedule_status' => $isArchived
                    ? 'Arsip'
                    : ($scheduleMode === 'one_day'
                        ? 'Sekali jalan'
                        : ($schedule ? ($schedule->is_active ? 'Aktif' : 'Nonaktif') : 'Belum terhubung')),
            ];
        })->values();
    }

    private function classAthletePayload(Collection $athletes): Collection
    {
        return $athletes
            ->sortBy(fn (Athlete $athlete) => $athlete->user?->name ?? '')
            ->map(fn (Athlete $athlete) => [
                'id' => $athlete->athlete_id,
                'name' => $athlete->user?->name ?? ('Atlet #'.$athlete->athlete_id),
                'geup' => $athlete->geup,
                'branch' => $athlete->branch?->branch_name,
                'training_group' => $athlete->trainingGroup?->name ?? $athlete->group?->trainingGroup?->name,
            ])
            ->values();
    }

    private function coachOptions()
    {
        return Coach::query()
            ->with('user:id,name')
            ->get()
            ->map(fn (Coach $coach) => ['value' => $coach->coach_id, 'label' => $coach->user?->name ?? 'Unknown coach'])
            ->sortBy('label')
            ->values();
    }

    private function beltOptions()
    {
        return collect(BeltRank::options())->values();
    }

    private function canManageSchedule(Request $request, WeeklyTrainingSchedule $schedule): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isCoach()) {
            $coachId = $user->coachProfile?->coach_id;

            return $coachId !== null && ((string) $schedule->coach_id === (string) $coachId || $schedule->coach_id === null);
        }

        return false;
    }

    private function oneDayClassArchived(Group $group, ?string $singleSessionDate): bool
    {
        if (($group->schedule_mode ?? 'weekly') !== 'one_day' || ! $singleSessionDate || ! $group->end_time) {
            return false;
        }

        return now()->greaterThan(\Illuminate\Support\Carbon::parse($singleSessionDate.' '.substr((string) $group->end_time, 0, 5)));
    }

    private function dayName(int $day): string
    {
        return [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'][$day] ?? '-';
    }
}
