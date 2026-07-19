<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Attendance\InitializeSessionAttendance;
use App\Actions\Sessions\GenerateWeeklyTrainingSessions;
use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\TrainingGroup;
use App\Models\TrainingSession;
use App\Models\WeeklyTrainingSchedule;
use App\Support\ActivityLogger;
use App\Support\Domain\BeltRank;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    private const CLASS_TYPES = ['reguler', 'prestasi', 'private', 'pemula', 'sparring'];

    private const SCHEDULE_MODES = ['weekly', 'one_day'];

    public function __construct(
        private readonly GenerateWeeklyTrainingSessions $sessionGenerator,
        private readonly InitializeSessionAttendance $initializeAttendance,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validatedGroup($request);
        $group = Group::create($this->payload($validated));
        $this->syncPrivateAthletes($group, $validated);
        $result = $this->syncSessionsForGroup($group->refresh());

        ActivityLogger::log($request, 'admin.group.created', 'admin', 'Created class', $group, [
            'group_name' => $group->group_name,
            'training_group_id' => $group->training_group_id,
            'private_athlete_ids' => $this->privateAthleteIds($validated),
            'day_of_weeks' => $this->weeklyDays($validated),
            'schedule_mode' => $group->schedule_mode,
            'auto_created_sessions' => $result['created'],
        ]);

        return back()->with('status', $this->sessionSyncMessage('Class saved', $group, $result));
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $existingSchedules = WeeklyTrainingSchedule::query()->where('group_id', $group->group_id)->get();
        $validated = $this->validatedGroup($request);
        $group->update($this->payload($validated));
        $this->syncPrivateAthletes($group, $validated);
        $result = $this->syncSessionsForGroup($group->refresh(), $existingSchedules);

        ActivityLogger::log($request, 'admin.group.updated', 'admin', 'Updated class', $group, [
            'group_name' => $group->group_name,
            'training_group_id' => $group->training_group_id,
            'private_athlete_ids' => $this->privateAthleteIds($validated),
            'day_of_weeks' => $this->weeklyDays($validated),
            'schedule_mode' => $group->schedule_mode,
            'auto_created_sessions' => $result['created'],
            'updated_future_sessions' => $result['updated'],
            'removed_future_sessions' => $result['removed'],
        ]);

        return back()->with('status', $this->sessionSyncMessage('Class updated', $group, $result));
    }

    public function athletes(Request $request, Group $group): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if (($group->class_type ?? null) === 'private') {
            $group->load(['privateAthletes.user:id,name', 'privateAthletes.branch:branch_id,branch_name', 'privateAthletes.trainingGroup']);

            return response()->json([
                'athletes' => $this->athletePayloadFromCollection($group->privateAthletes),
            ]);
        }

        $group->load(['athletes.user:id,name', 'athletes.branch:branch_id,branch_name', 'athletes.trainingGroup']);

        return response()->json([
            'athletes' => $this->athletePayload($group),
        ]);
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $hasAthletes = $group->athletes()->exists() || $group->privateAthletes()->exists();
        $hasSessions = TrainingSession::query()->where('group_id', $group->group_id)->exists();
        $schedules = WeeklyTrainingSchedule::query()->where('group_id', $group->group_id)->get();

        if ($hasAthletes || $hasSessions) {
            $group->update(['is_active' => false]);
            $schedules->each->update(['is_active' => false]);
            $removed = $this->removeFutureSessionsForSchedules($schedules);
            $removed += $this->removeFutureOneDaySessions($group);

            return back()->with('status', "Class has linked athletes or sessions, so it was deactivated instead of deleted. Removed {$removed} future sessions; past sessions were kept.");
        }

        $this->removeFutureSessionsForSchedules($schedules);
        $this->removeFutureOneDaySessions($group);
        $schedules->each->delete();
        $group->privateAthletes()->detach();
        ActivityLogger::log($request, 'admin.group.deleted', 'admin', 'Deleted class', $group, ['group_name' => $group->group_name]);
        $group->delete();

        return back()->with('status', 'Class deleted.');
    }

    private function validatedGroup(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'training_group_id' => ['nullable', 'required_unless:class_type,private', 'exists:training_groups,id'],
            'class_type' => ['required', 'string', Rule::in(self::CLASS_TYPES)],
            'schedule_mode' => ['required', 'string', Rule::in(self::SCHEDULE_MODES)],
            'single_session_date' => ['nullable', 'required_if:schedule_mode,one_day', 'date'],
            'coach_id' => ['nullable', 'exists:coaches,coach_id'],
            'dedicated_athlete_ids' => ['nullable', 'required_if:class_type,private', 'array', 'min:1'],
            'dedicated_athlete_ids.*' => ['string', 'distinct', 'exists:athletes,athlete_id'],
            'branch_id' => ['nullable', 'exists:branches,branch_id'],
            'day_of_week' => ['nullable', 'integer', 'between:1,7'],
            'day_of_weeks' => ['nullable', 'required_if:schedule_mode,weekly', 'array', 'min:1'],
            'day_of_weeks.*' => ['integer', 'distinct', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'min_belt' => ['nullable', 'string', Rule::in(BeltRank::values())],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);
    }

    private function payload(array $validated): array
    {
        $classType = str($validated['class_type'] ?? 'reguler')->lower()->slug('_')->toString();
        $scheduleMode = in_array($validated['schedule_mode'] ?? 'weekly', self::SCHEDULE_MODES, true)
            ? $validated['schedule_mode']
            : 'weekly';
        $singleSessionDate = $scheduleMode === 'one_day' ? ($validated['single_session_date'] ?? null) : null;
        $privateAthleteIds = $this->privateAthleteIds($validated);
        $primaryPrivateAthleteId = $classType === 'private' ? ($privateAthleteIds[0] ?? null) : null;
        $weeklyDays = $scheduleMode === 'weekly' ? $this->weeklyDays($validated) : [];

        if ($scheduleMode === 'one_day' && $singleSessionDate) {
            $validated['day_of_week'] = Carbon::parse($singleSessionDate)->isoWeekday();
        }

        $validated['class_type'] = $classType;
        $validated['schedule_mode'] = $scheduleMode;
        $validated['single_session_date'] = $singleSessionDate;
        $validated['dedicated_athlete_id'] = $primaryPrivateAthleteId;
        $validated['day_of_weeks'] = $weeklyDays;

        return [
            'group_name' => $validated['name'],
            'training_group_id' => $classType === 'private' ? null : ($validated['training_group_id'] ?? null),
            'class_type' => $classType,
            'schedule_mode' => $scheduleMode,
            'single_session_date' => $singleSessionDate,
            'coach_id' => $classType === 'private' ? ($validated['coach_id'] ?? null) : null,
            'dedicated_athlete_id' => $primaryPrivateAthleteId,
            'branch_id' => $validated['branch_id'] ?? null,
            'day_of_week' => $scheduleMode === 'weekly' ? ($weeklyDays[0] ?? null) : ($validated['day_of_week'] ?? null),
            'day_of_weeks' => $scheduleMode === 'weekly' ? $weeklyDays : null,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'min_belt' => BeltRank::normalize($validated['min_belt'] ?? null) ?: null,
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true) && $this->canActivate($validated),
        ];
    }

    private function syncPrivateAthletes(Group $group, array $validated): void
    {
        if (($validated['class_type'] ?? null) !== 'private') {
            $group->privateAthletes()->detach();
            return;
        }

        $group->privateAthletes()->sync($this->privateAthleteIds($validated));
    }

    private function privateAthleteIds(array $validated): array
    {
        return collect($validated['dedicated_athlete_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function weeklyDays(array $validated): array
    {
        $days = collect($validated['day_of_weeks'] ?? []);

        if ($days->isEmpty() && filled($validated['day_of_week'] ?? null)) {
            $days = collect([$validated['day_of_week']]);
        }

        return $days
            ->map(fn ($day) => (int) $day)
            ->filter(fn (int $day) => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function weeklyDaysForGroup(Group $group): array
    {
        $days = $group->day_of_weeks ?? [];

        if (is_string($days)) {
            $days = json_decode($days, true) ?: [];
        }

        return $this->weeklyDays([
            'day_of_weeks' => $days,
            'day_of_week' => $group->day_of_week,
        ]);
    }

    /** @return array{created:int, skipped:int, updated:int, removed:int, from:string, to:string} */
    private function syncSessionsForGroup(Group $group, ?Collection $previousSchedules = null): array
    {
        if (($group->schedule_mode ?? 'weekly') === 'one_day') {
            $previousSchedules ??= WeeklyTrainingSchedule::query()->where('group_id', $group->group_id)->get();
            $previousSchedules->each->update(['is_active' => false]);
            $result = $this->syncOneDaySession($group);
            $result['removed'] += $this->removeFutureSessionsForSchedules($previousSchedules);
            return $result;
        }

        $sync = $this->syncWeeklySchedules($group);
        $result = $this->generateSessionsForSchedules($sync['schedules']);
        $result['removed'] += $sync['removed'];
        $result['removed'] += $this->removeFutureOneDaySessions($group);

        return $result;
    }

    /** @return array{schedules:Collection<int, WeeklyTrainingSchedule>, removed:int} */
    private function syncWeeklySchedules(Group $group): array
    {
        $group->loadMissing('branch', 'trainingGroup', 'privateAthletes');
        $existingSchedules = WeeklyTrainingSchedule::withTrashed()->where('group_id', $group->group_id)->get();
        $isPrivate = ($group->class_type ?? null) === 'private';
        $privateAthleteIds = $group->privateAthletes->pluck('athlete_id')->filter()->values();
        $weeklyDays = $this->weeklyDaysForGroup($group);
        $removed = 0;

        $isSchedulable = (bool) (
            ($group->schedule_mode ?? 'weekly') === 'weekly'
            && $group->is_active
            && ($isPrivate || ($group->training_group_id && $group->trainingGroup?->is_active))
            && $group->branch?->is_active
            && $group->branch_id
            && count($weeklyDays) > 0
            && $group->start_time
            && $group->end_time
            && (! $isPrivate || (filled($group->coach_id) && $privateAthleteIds->isNotEmpty()))
        );

        if (! $isSchedulable) {
            foreach ($existingSchedules as $schedule) {
                if (! $schedule->trashed()) {
                    $schedule->update(['is_active' => false]);
                }
                $removed += $this->sessionGenerator->removeFutureSessionsForSchedule($schedule);
            }

            return ['schedules' => collect(), 'removed' => $removed];
        }

        $sessionType = str($group->class_type ?? 'reguler')->lower()->slug('_')->toString();
        $activeSchedules = collect();

        foreach ($weeklyDays as $day) {
            $schedule = $existingSchedules->first(fn (WeeklyTrainingSchedule $item) => (int) $item->day_of_week === (int) $day);

            if (! $schedule) {
                $schedule = new WeeklyTrainingSchedule();
                $schedule->group_id = $group->group_id;
            }

            if ($schedule->trashed()) {
                $schedule->restore();
            }

            $schedule->forceFill([
                'title' => $group->group_name,
                'branch_id' => $group->branch_id,
                'group_id' => $group->group_id,
                'dedicated_athlete_id' => $sessionType === 'private' ? $privateAthleteIds->first() : null,
                'coach_id' => $sessionType === 'private' ? $group->coach_id : null,
                'session_type' => $sessionType,
                'day_of_week' => $day,
                'start_time' => $group->start_time,
                'end_time' => $group->end_time,
                'location' => $group->branch?->location ?? $group->branch?->branch_name,
                'is_active' => true,
            ])->save();

            $activeSchedules->push($schedule->refresh());
        }

        $obsoleteSchedules = $existingSchedules->filter(fn (WeeklyTrainingSchedule $schedule) => ! in_array((int) $schedule->day_of_week, $weeklyDays, true));
        foreach ($obsoleteSchedules as $schedule) {
            if (! $schedule->trashed()) {
                $schedule->update(['is_active' => false]);
            }
            $removed += $this->sessionGenerator->removeFutureSessionsForSchedule($schedule);
        }

        return ['schedules' => $activeSchedules, 'removed' => $removed];
    }

    /** @return array{created:int, skipped:int, updated:int, removed:int, from:string, to:string} */
    private function generateSessionsForSchedules(Collection $schedules): array
    {
        if ($schedules->isEmpty()) {
            return $this->emptySessionSyncResult();
        }

        return $this->sessionGenerator->handle(
            now()->startOfDay(),
            now()->copy()->addDays(14)->endOfDay(),
            $schedules->pluck('weekly_training_schedule_id')->all(),
        );
    }

    private function removeFutureSessionsForSchedules(Collection $schedules): int
    {
        return $schedules->sum(fn (WeeklyTrainingSchedule $schedule) => $this->sessionGenerator->removeFutureSessionsForSchedule($schedule));
    }

    /** @return array{created:int, skipped:int, updated:int, removed:int, from:string, to:string} */
    private function syncOneDaySession(Group $group): array
    {
        $result = $this->emptySessionSyncResult();
        $group->loadMissing('branch', 'trainingGroup', 'privateAthletes');

        if (! $this->isOneDaySchedulable($group)) {
            $result['removed'] += $this->removeFutureOneDaySessions($group);
            return $result;
        }

        $date = Carbon::parse((string) $group->single_session_date)->startOfDay();
        $result['from'] = $date->toDateString();
        $result['to'] = $date->toDateString();
        $result['removed'] += $this->removeFutureOneDaySessions($group, $date);

        $session = TrainingSession::query()
            ->withTrashed()
            ->where('group_id', $group->group_id)
            ->whereNull('weekly_training_schedule_id')
            ->whereDate('session_date', $date->toDateString())
            ->first();

        if ($session && $date->lt(now()->startOfDay())) {
            $result['skipped']++;
            return $result;
        }

        DB::transaction(function () use ($group, $date, $session, &$result): void {
            if ($session) {
                if ($session->trashed()) {
                    $session->restore();
                }
                $session->forceFill($this->oneDaySessionPayload($group, $date))->save();
                $this->syncSessionCoach($session, $this->coachIdFor($group));
                $this->initializeAttendance->handle($session);
                $result['updated']++;
                return;
            }

            $session = TrainingSession::query()->create($this->oneDaySessionPayload($group, $date));
            $this->syncSessionCoach($session, $this->coachIdFor($group));
            $this->initializeAttendance->handle($session);
            $result['created']++;
        });

        return $result;
    }

    private function removeFutureOneDaySessions(Group $group, ?CarbonInterface $exceptDate = null): int
    {
        $removed = 0;
        $query = TrainingSession::query()->where('group_id', $group->group_id)->whereNull('weekly_training_schedule_id')->whereDate('session_date', '>', now()->toDateString());

        if ($exceptDate) {
            $query->whereDate('session_date', '!=', $exceptDate->toDateString());
        }

        $query->chunkById(100, function ($sessions) use (&$removed): void {
            foreach ($sessions as $session) {
                $session->delete();
                $removed++;
            }
        }, 'training_session_id');

        return $removed;
    }

    private function isOneDaySchedulable(Group $group): bool
    {
        $isPrivate = ($group->class_type ?? null) === 'private';
        $privateAthleteIds = $group->privateAthletes->pluck('athlete_id')->filter();

        return (bool) (($group->schedule_mode ?? 'weekly') === 'one_day'
            && $group->is_active
            && ($isPrivate || ($group->training_group_id && $group->trainingGroup?->is_active))
            && $group->single_session_date
            && $group->branch?->is_active
            && $group->branch_id
            && $group->start_time
            && $group->end_time
            && (! $isPrivate || (filled($group->coach_id) && $privateAthleteIds->isNotEmpty())));
    }

    private function oneDaySessionPayload(Group $group, CarbonInterface $date): array
    {
        $privateAthleteIds = $group->privateAthletes->pluck('athlete_id')->filter()->values();

        return [
            'weekly_training_schedule_id' => null,
            'coach_id' => $this->coachIdFor($group),
            'branch_id' => $group->branch_id,
            'group_id' => $group->group_id,
            'session_type' => $group->class_type,
            'dedicated_athlete_id' => ($group->class_type ?? null) === 'private' ? $privateAthleteIds->first() : null,
            'title' => $group->group_name,
            'location' => $group->branch?->location ?? $group->branch?->branch_name,
            'session_date' => $date->toDateString(),
            'start_time' => $group->start_time,
            'end_time' => $group->end_time,
            'status' => 'CONFIRMED',
        ];
    }

    private function coachIdFor(Group $group): ?string
    {
        return ($group->class_type ?? null) === 'private' && $group->coach_id ? $group->coach_id : null;
    }

    private function syncSessionCoach(TrainingSession $session, ?string $coachId): void
    {
        if (! Schema::hasTable('training_session_coaches')) {
            return;
        }

        $coachId ? $session->assignedCoaches()->sync([$coachId]) : $session->assignedCoaches()->detach();
    }

    /** @return array{created:int, skipped:int, updated:int, removed:int, from:string, to:string} */
    private function emptySessionSyncResult(): array
    {
        return [
            'created' => 0,
            'skipped' => 0,
            'updated' => 0,
            'removed' => 0,
            'from' => now()->toDateString(),
            'to' => now()->toDateString(),
        ];
    }

    private function sessionSyncMessage(string $prefix, Group $group, array $result): string
    {
        $mode = ($group->schedule_mode ?? 'weekly') === 'one_day' ? 'one-day class session' : 'weekly schedule';
        return "{$prefix} and {$mode} synced. Auto-created {$result['created']} sessions; updated {$result['updated']} current/future sessions; removed {$result['removed']} obsolete future sessions; skipped {$result['skipped']} past sessions.";
    }

    private function athletePayload(Group $group): array
    {
        return $this->athletePayloadFromCollection($group->athletes);
    }

    private function athletePayloadFromCollection($athletes): array
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
            ->values()
            ->all();
    }

    private function canActivate(array $validated): bool
    {
        $classType = $validated['class_type'] ?? null;
        $branchId = $validated['branch_id'] ?? null;
        $scheduleMode = $validated['schedule_mode'] ?? 'weekly';
        $trainingGroupId = $validated['training_group_id'] ?? null;
        $privateAthleteIds = $this->privateAthleteIds($validated);
        $weeklyDays = $this->weeklyDays($validated);

        return filled($validated['name'] ?? null)
            && in_array($classType, self::CLASS_TYPES, true)
            && in_array($scheduleMode, self::SCHEDULE_MODES, true)
            && ($classType === 'private' || (filled($trainingGroupId) && TrainingGroup::query()->where('id', $trainingGroupId)->where('is_active', true)->exists()))
            && filled($branchId)
            && Branch::query()->where('branch_id', $branchId)->where('is_active', true)->exists()
            && filled($validated['start_time'] ?? null)
            && filled($validated['end_time'] ?? null)
            && ($scheduleMode === 'weekly' ? count($weeklyDays) > 0 : filled($validated['single_session_date'] ?? null))
            && ($classType !== 'private' || (filled($validated['coach_id'] ?? null) && count($privateAthleteIds) > 0));
    }
}
