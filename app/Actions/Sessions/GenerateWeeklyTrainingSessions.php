<?php

namespace App\Actions\Sessions;

use App\Actions\Attendance\InitializeSessionAttendance;
use App\Models\TrainingSession;
use App\Models\WeeklyTrainingSchedule;
use App\Services\SessionVisibilityService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
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
    public function handle(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $from = ($from ?? now()->startOfWeek())->copy()->startOfDay();
        $to = ($to ?? $from->copy()->endOfWeek())->copy()->endOfDay();

        $created = 0;
        $skipped = 0;

        /** @var Collection<int, WeeklyTrainingSchedule> $schedules */
        $schedules = WeeklyTrainingSchedule::query()
            ->with(['branch', 'group'])
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        foreach ($schedules as $schedule) {
            foreach ($this->sessionDatesFor($schedule, $from, $to) as $date) {
                if ($this->sessionExists($schedule, $date)) {
                    $skipped++;
                    continue;
                }

                DB::transaction(function () use ($schedule, $date, &$created): void {
                    $session = TrainingSession::query()->create([
                        'weekly_training_schedule_id' => $schedule->weekly_training_schedule_id,
                        'coach_id' => $schedule->coach_id,
                        'branch_id' => $schedule->branch_id,
                        'group_id' => $schedule->group_id,
                        'title' => $schedule->title,
                        'location' => $schedule->location ?? $schedule->branch?->location,
                        'session_date' => $date->toDateString(),
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'status' => 'CONFIRMED',
                    ]);

                    if ($schedule->coach_id && $this->sessionVisibility->hasCoachPivotTable()) {
                        $session->assignedCoaches()->syncWithoutDetaching([$schedule->coach_id]);
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

            $date->addDay();
        }

        return $dates;
    }

    private function sessionExists(WeeklyTrainingSchedule $schedule, CarbonInterface $date): bool
    {
        $query = TrainingSession::query()->withTrashed()->whereDate('session_date', $date->toDateString());

        if (Schema::hasColumn('training_sessions', 'weekly_training_schedule_id')) {
            $query->where(function ($query) use ($schedule): void {
                $query->where('weekly_training_schedule_id', $schedule->weekly_training_schedule_id)
                    ->orWhere(function ($fallback) use ($schedule): void {
                        $fallback->where('branch_id', $schedule->branch_id)
                            ->where('group_id', $schedule->group_id)
                            ->where('start_time', $schedule->start_time)
                            ->where('end_time', $schedule->end_time);
                    });
            });
        } else {
            $query->where('branch_id', $schedule->branch_id)
                ->where('group_id', $schedule->group_id)
                ->where('start_time', $schedule->start_time)
                ->where('end_time', $schedule->end_time);
        }

        return $query->exists();
    }
}
