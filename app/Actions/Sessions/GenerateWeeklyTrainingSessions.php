<?php

namespace App\Actions\Sessions;

use App\Actions\Attendance\InitializeSessionAttendance;
use App\Models\TrainingSession;
use App\Models\WeeklyTrainingSchedule;
use App\Services\SessionVisibilityService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateWeeklyTrainingSessions
{
    public function __construct(
        private readonly InitializeSessionAttendance $initializeAttendance,
        private readonly SessionVisibilityService $sessionVisibility,
    ) {}

    /**
     * @return array{created:int, skipped:int, updated:int, removed:int, from:string, to:string}
     */
    public function handle(?CarbonInterface $from = null, ?CarbonInterface $to = null, array|Enumerable|null $scheduleIds = null, bool $attachScheduleCoach = true): array
    {
        $from = ($from ?? now()->startOfWeek())->copy()->startOfDay();
        $to = ($to ?? $from->copy()->endOfWeek())->copy()->endOfDay();

        $created = 0;
        $skipped = 0;
        $updated = 0;
        $removed = 0;

        $scheduleIds = $scheduleIds instanceof Enumerable ? $scheduleIds->all() : $scheduleIds;

        /** @var Collection<int, WeeklyTrainingSchedule> $schedules */
        $schedules = WeeklyTrainingSchedule::query()
            ->with(['branch', 'group.coaches'])
            ->where('is_active', true)
            ->whereHas('branch', fn ($query) => $query->where('is_active', true))
            ->where(function ($query): void {
                $query->whereNull('group_id')
                    ->orWhereHas('group', fn ($groupQuery) => $groupQuery->where('is_active', true));
            })
            ->when($scheduleIds !== null, fn ($query) => $query->whereIn('weekly_training_schedule_id', $scheduleIds))
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        foreach ($schedules as $schedule) {
            $removed += $this->removeFutureSessionsOutsideSchedule($schedule, $from, $to);

            foreach ($this->sessionDatesFor($schedule, $from, $to) as $date) {
                $existing = $this->existingSession($schedule, $date);

                if ($existing) {
                    $skipped++;

                    if ($this->isFutureSessionDate($date)) {
                        DB::transaction(function () use ($existing, $schedule, $date, $attachScheduleCoach, &$updated): void {
                            $this->refreshSessionFromSchedule($existing, $schedule, $date, $attachScheduleCoach);
                            $updated++;
                        });
                    }

                    continue;
                }

                DB::transaction(function () use ($schedule, $date, $attachScheduleCoach, &$created): void {
                    $coachIds = $this->coachIdsFor($schedule, $attachScheduleCoach);
                    $session = TrainingSession::query()->create(
                        $this->sessionPayload($schedule, $date, $coachIds),
                    );

                    $this->syncSessionCoaches($session, $coachIds);
                    $this->initializeAttendance->handle($session);
                    $created++;
                });
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'updated' => $updated,
            'removed' => $removed,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    public function removeFutureSessionsForSchedule(?WeeklyTrainingSchedule $schedule): int
    {
        if (! $schedule) {
            return 0;
        }

        $removed = 0;

        TrainingSession::query()
            ->where('weekly_training_schedule_id', $schedule->weekly_training_schedule_id)
            ->whereDate('session_date', '>', now()->toDateString())
            ->chunkById(100, function (Collection $sessions) use (&$removed): void {
                foreach ($sessions as $session) {
                    $session->delete();
                    $removed++;
                }
            }, 'training_session_id');

        return $removed;
    }

    public function removeFutureSessionsOutsideSchedule(WeeklyTrainingSchedule $schedule, CarbonInterface $from, CarbonInterface $to): int
    {
        $targetDates = collect($this->sessionDatesFor($schedule, $from, $to))
            ->map(fn (CarbonInterface $date) => $date->toDateString())
            ->all();

        $removed = 0;

        TrainingSession::query()
            ->where('weekly_training_schedule_id', $schedule->weekly_training_schedule_id)
            ->whereDate('session_date', '>', now()->toDateString())
            ->whereNotIn('session_date', $targetDates)
            ->chunkById(100, function (Collection $sessions) use (&$removed): void {
                foreach ($sessions as $session) {
                    $session->delete();
                    $removed++;
                }
            }, 'training_session_id');

        return $removed;
    }

    /**
     * @return array<int, CarbonInterface>
     */
    private function sessionDatesFor(WeeklyTrainingSchedule $schedule, CarbonInterface $from, CarbonInterface $to): array
    {
        $dates = [];
        $date = $from->copy();

        while ($date->lte($to)) {
            if ($date->isoWeekday() === (int) $schedule->day_of_week) {
                $dates[] = $date->copy();
            }

            $date = $date->addDay();
        }

        return $dates;
    }

    private function existingSession(WeeklyTrainingSchedule $schedule, CarbonInterface $date): ?TrainingSession
    {
        $query = TrainingSession::query()->withTrashed()->whereDate('session_date', $date->toDateString());

        if (Schema::hasColumn('training_sessions', 'weekly_training_schedule_id')) {
            return (clone $query)
                ->where('weekly_training_schedule_id', $schedule->weekly_training_schedule_id)
                ->first();
        }

        $query->where('branch_id', $schedule->branch_id)
            ->where('group_id', $schedule->group_id)
            ->where('start_time', $schedule->start_time)
            ->where('end_time', $schedule->end_time);

        if (Schema::hasColumn('training_sessions', 'session_type')) {
            $query->where('session_type', $schedule->session_type);
        }

        if (Schema::hasColumn('training_sessions', 'dedicated_athlete_id')) {
            $schedule->dedicated_athlete_id
                ? $query->where('dedicated_athlete_id', $schedule->dedicated_athlete_id)
                : $query->whereNull('dedicated_athlete_id');
        }

        return $query->first();
    }

    private function refreshSessionFromSchedule(TrainingSession $session, WeeklyTrainingSchedule $schedule, CarbonInterface $date, bool $attachScheduleCoach): void
    {
        if ($session->trashed()) {
            $session->restore();
        }

        $coachIds = $this->coachIdsFor($schedule, $attachScheduleCoach);
        $session->forceFill($this->sessionPayload($schedule, $date, $coachIds))->save();
        $this->syncSessionCoaches($session, $coachIds);
        $this->initializeAttendance->handle($session);
    }

    private function sessionPayload(WeeklyTrainingSchedule $schedule, CarbonInterface $date, array $coachIds): array
    {
        return [
            'weekly_training_schedule_id' => $schedule->weekly_training_schedule_id,
            'coach_id' => $coachIds[0] ?? null,
            'branch_id' => $schedule->branch_id,
            'group_id' => $schedule->group_id,
            'session_type' => $schedule->session_type,
            'dedicated_athlete_id' => $schedule->dedicated_athlete_id,
            'title' => $schedule->title,
            'location' => $schedule->location ?? $schedule->branch?->location,
            'session_date' => $date->toDateString(),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'status' => 'CONFIRMED',
        ];
    }

    private function coachIdsFor(WeeklyTrainingSchedule $schedule, bool $attachScheduleCoach): array
    {
        if (! $attachScheduleCoach) {
            return [];
        }

        return collect([$schedule->coach_id, $schedule->group?->coach_id])
            ->merge($schedule->group?->coaches?->pluck('coach_id') ?? collect())
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function syncSessionCoaches(TrainingSession $session, array $coachIds): void
    {
        if (! $this->sessionVisibility->hasCoachPivotTable()) {
            return;
        }

        $session->assignedCoaches()->sync($coachIds);
    }

    private function isFutureSessionDate(CarbonInterface $date): bool
    {
        return $date->toDateString() > now()->toDateString();
    }
}
