<?php

namespace App\Actions\Sessions;

use App\Actions\Attendance\InitializeSessionAttendance;
use App\Models\Coach;
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
     * @return array{created:int, skipped:int, from:string, to:string}
     */
    public function handle(?CarbonInterface $from = null, ?CarbonInterface $to = null, array|Enumerable|null $scheduleIds = null, bool $attachScheduleCoach = true): array
    {
        $from = ($from ?? now()->startOfWeek())->copy()->startOfDay();
        $to = ($to ?? $from->copy()->endOfWeek())->copy()->endOfDay();

        $created = 0;
        $skipped = 0;

        $scheduleIds = $scheduleIds instanceof Enumerable ? $scheduleIds->all() : $scheduleIds;

        /** @var Collection<int, WeeklyTrainingSchedule> $schedules */
        $schedules = WeeklyTrainingSchedule::query()
            ->with(['branch', 'group'])
            ->where('is_active', true)
            ->when($scheduleIds !== null, fn ($query) => $query->whereIn('weekly_training_schedule_id', $scheduleIds))
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        foreach ($schedules as $schedule) {
            foreach ($this->sessionDatesFor($schedule, $from, $to) as $date) {
                if ($this->sessionExists($schedule, $date)) {
                    $skipped++;

                    continue;
                }

                DB::transaction(function () use ($schedule, $date, $attachScheduleCoach, &$created): void {
                    $coachId = $attachScheduleCoach && $schedule->coach_id
                        ? $schedule->coach_id
                        : Coach::query()->orderBy('coach_id')->value('coach_id');

                    $session = TrainingSession::query()->create([
                        'weekly_training_schedule_id' => $schedule->weekly_training_schedule_id,
                        'coach_id' => $coachId,
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
                    ]);

                    if ($coachId && $this->sessionVisibility->hasCoachPivotTable()) {
                        $session->assignedCoaches()->syncWithoutDetaching([$coachId]);
                    }

                    $this->initializeAttendance->handle($session);
                    $created++;
                });
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
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

    private function sessionExists(WeeklyTrainingSchedule $schedule, CarbonInterface $date): bool
    {
        $query = TrainingSession::query()->withTrashed()->whereDate('session_date', $date->toDateString());

        if (Schema::hasColumn('training_sessions', 'weekly_training_schedule_id')) {
            return (clone $query)
                ->where('weekly_training_schedule_id', $schedule->weekly_training_schedule_id)
                ->exists();
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

        return $query->exists();
    }
}
