<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Attendance\InitializeSessionAttendance;
use App\Actions\Sessions\GenerateWeeklyTrainingSessions;
use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\WeeklyTrainingSchedule;
use App\Support\ActivityLogger;
use App\Support\Domain\BeltRank;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        abort_unless($request->user()?->isAdmin() || $request->user()?->isCoach(), 403);

        $validated = $this->validatedGroup($request);
        $group = Group::create($this->payload($validated));
        $this->syncCoaches($group, $validated);
        $this->syncPrivateAthletes($group, $validated);
        $result = $this->syncSessionsForGroup($group->refresh());

        ActivityLogger::log($request, 'admin.group.created', 'admin', 'Created class', $group, [
            'group_name' => $group->group_name,
            'training_group_id' => $group->training_group_id,
            'coach_ids' => $this->coachIds($validated),
            'private_athlete_ids' => $this->privateAthleteIds($validated),
            'schedule_mode' => $group->schedule_mode,
            'auto_created_sessions' => $result['created'],
        ]);

        return back()->with('status', $this->sessionSyncMessage('Class saved', $group, $result));
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $existingSchedule = WeeklyTrainingSchedule::query()->where('group_id', $group->group_id)->first();
        $validated = $this->validatedGroup($request);
        $group->update($this->payload($validated));
        $this->syncCoaches($group, $validated);
        $this->syncPrivateAthletes($group, $validated);
        $result = $this->syncSessionsForGroup($group->refresh(), $existingSchedule);

        ActivityLogger::log($request, 'admin.group.updated', 'admin', 'Updated class', $group, [
            'group_name' => $group->group_name,
            'training_group_id' => $group->training_group_id,
            'coach_ids' => $this->coachIds($validated),
            'private_athlete_ids' => $this->privateAthleteIds($validated),
            'schedule_mode' => $group->schedule_mode,
            'auto_created_sessions' => $result['created'],
            'updated_future_sessions' => $result['updated'],
            'removed_future_sessions' => $result['removed'],
        ]);

        return back()->with('status', $this->sessionSyncMessage('Class updated', $group, $result));
    }

    public function athletes(Request $request, Group $group): JsonResponse
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isCoach(), 403);

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
        $schedule = WeeklyTrainingSchedule::query()->where('group_id', $group->group_id)->first();

        if ($hasAthletes || $hasSessions) {
            $group->update(['is_active' => false]);
            $schedule?->update(['is_active' => false]);
            $removed = $this->sessionGenerator->removeFutureSessionsForSchedule($schedule);
            $removed += $this->removeFutureOneDaySessions($group);

            return back()->with('status', "Class has linked athletes or sessions, so it was deactivated instead of deleted. Removed {$removed} future sessions; past sessions were kept.");
        }

        $this->sessionGenerator->removeFutureSessionsForSchedule($schedule);
        $this->removeFutureOneDaySessions($group);
        $schedule?->delete();
        $group->privateAthletes()->detach();
        $group->coaches()->detach();
        ActivityLogger::log($request, 'admin.group.deleted', 'admin', 'Deleted class', $group, ['group_name' => $group->group_name]);
        $group->delete();

        return back()->with('status', 'Class deleted.');
    }

    private function validatedGroup(Request $request): array
    {
        if (! $request->has('coach_ids') && $request->filled('coach_id')) {
            $request->merge(['coach_ids' => [$request->input('coach_id')]]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'training_group_id' => ['nullable', 'required_unless:class_type,private', 'exists:training_groups,id'],
            'class_type' => ['required', 'string', Rule::in(self::CLASS_TYPES)],
            'schedule_mode' => ['required', 'string', Rule::in(self::SCHEDULE_MODES)],
            'single_session_date' => ['nullable', 'required_if:schedule_mode,one_day', 'date'],
            'coach_ids' => ['nullable', 'required_if:class_type,private', 'array'],
            'coach_ids.*' => ['string', 'distinct', 'exists:coaches,coach_id'],
            'dedicated_athlete_ids' => ['nullable', 'required_if:class_type,private', 'array', 'min:1'],
            'dedicated_athlete_ids.*' => ['string', 'distinct', 'exists:athletes,athlete_id'],
            'branch_id' => ['nullable', 'exists:branches,branch_id'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
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
        $coachIds = $this->coachIds($validated);

        if ($scheduleMode === 'one_day' && $singleSessionDate) {
            $validated['day_of_week'] = Carbon::parse($singleSessionDate)->isoWeekday();
        }

        $validated['class_type'] = $classType;
        $validated['schedule_mode'] = $scheduleMode;
        $validated['single_session_date'] = $singleSessionDate;
        $validated['dedicated_athlete_id'] = $primaryPrivateAthleteId;

        return [
            'group_name' => $validated['name'],
            'training_group_id' => $classType === 'private' ? null : ($validated['training_group_id'] ?? null),
            'class_type' => $classType,
            'schedule_mode' => $scheduleMode,
            'single_session_date' => $singleSessionDate,
            'coach_id' => $coachIds[0] ?? null,
            'dedicated_athlete_id' => $primaryPrivateAthleteId,
            'branch_id' => $validated['branch_id'] ?? null,
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'min_belt' => BeltRank::normalize($validated['min_belt'] ?? null) ?: null,
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true) && $this->canActivate($validated),
        ];
    }

    private function syncCoaches(Group $group, array $validated): void
    {
        $group->coaches()->sync($this->coachIds($validated));
    }

    private function coachIds(array $validated): array
    {
        return collect($validated['coach_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function classCoachIds(Group $group): array
    {
        $group->loadMissing('coaches');

        return collect([$group->coach_id])
            ->merge($group->coaches->pluck('coach_id'))
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
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

    /** @return array{created:int, skipped:int, updated:int, removed:int, from:string, to:string} */
    private function syncSessionsForGroup(Group $group, ?WeeklyTrainingSchedule $previousSchedule = null): array
    {
        if (($group->schedule_mode ?? 'weekly') === 'one_day') {
            $previousSchedule ??= WeeklyTrainingSchedule::query()->where('group_id', $group->group_id)->first();
            $previousSchedule?->update(['is_active' => false]);
            $result = $this->syncOneDaySession($group);
            $result['removed'] += $this->sessionGenerator->removeFutureSessionsForSchedule($previousSchedule);

            return $result;
        }

        $schedule = $this->syncWeeklySchedule($group);
        $result = $this->generateSessionsForSchedule($schedule);
        $result['removed'] += $this->removeFutureOneDaySessions($group);

        if (! $schedule && $previousSchedule) {
            $result['removed'] += $this->sessionGenerator->removeFutureSessionsForSchedule($previousSchedule);
        }

        return $result;
    }

    private function syncWeeklySchedule(Group $group): ?WeeklyTrainingSchedule
    {
        $group->loadMissing('branch', 'trainingGroup', 'privateAthletes', 'coaches');
        $existing = WeeklyTrainingSchedule::query()->where('group_id', $group->group_id)->first();
        $isPrivate = ($group->class_type ?? null) === 'private';
        $privateAthleteIds = $group->privateAthletes->pluck('athlete_id')->filter()->values();
        $coachIds = collect($this->classCoachIds($group));
        $isSchedulable = (bool) (
            ($group->schedule_mode ?? 'weekly') === 'weekly'
            && $group->is_active
            && ($isPrivate || ($group->training_group_id && $group->trainingGroup?->is_active))
            && $group->branch?->is_active
            && $group->branch_id
            && $group->day_of_week
            && $group->start_time
            && $group->end_time
            && (! $isPrivate || ($coachIds->isNotEmpty() && $privateAthleteIds->isNotEmpty()))
        );

        if (! $isSchedulable) {
            $existing?->update(['is_active' => false]);

            return null;
        }

        $sessionType = str($group->class_type ?? 'reguler')->lower()->slug('_')->toString();

        return WeeklyTrainingSchedule::query()->updateOrCreate(
            ['group_id' => $group->group_id],
            [
                'title' => $group->group_name,
                'branch_id' => $group->branch_id,
                'group_id' => $group->group_id,
                'dedicated_athlete_id' => $sessionType === 'private' ? $privateAthleteIds->first() : null,
                'coach_id' => $coachIds->first(),
                'session_type' => $sessionType,
                'day_of_week' => $group->day_of_week,
                'start_time' => $group->start_time,
                'end_time' => $group->end_time,
                'location' => $group->branch?->location ?? $group->branch?->branch_name,
                'is_active' => true,
            ],
        );
    }

    /** @return array{created:int, skipped:int, updated:int, removed:int, from:string, to:string} */
    private function generateSessionsForSchedule(?WeeklyTrainingSchedule $schedule): array
    {
        if (! $schedule) {
            return $this->emptySessionSyncResult();
        }

        return $this->sessionGenerator->handle(now()->startOfDay(), now()->copy()->addDays(14)->endOfDay(), [$schedule->weekly_training_schedule_id]);
    }

    /** @return array{created:int, skipped:int, updated:int, removed:int, from:string, to:string} */
    private function syncOneDaySession(Group $group): array
    {
        $result = $this->emptySessionSyncResult();
        $group->loadMissing('branch', 'trainingGroup', 'privateAthletes', 'coaches');

        if (! $this->isOneDaySchedulable($group)) {
            $result['removed'] += $this->removeFutureOneDaySessions($group);

            return $result;
        }

        $date = Carbon::parse((string) $group->single_session_date)->startOfDay();
        $result['from'] = $date->toDateString();
        $result['to'] = $date->toDateString();
        $result['removed'] += $this->removeFutureOneDaySessions($group, $date);
        $coachIds = $this->classCoachIds($group);

        $session = TrainingSession::query()->updateOrCreate(
            [
                'group_id' => $group->group_id,
                'session_date' => $date->toDateString(),
            ],
            [
                'title' => $group->group_name,
                'branch_id' => $group->branch_id,
                'coach_id' => $coachIds[0] ?? null,
                'dedicated_athlete_id' => ($group->class_type ?? null) === 'private' ? $group->privateAthletes->pluck('athlete_id')->first() : null,
                'session_type' => $group->class_type ?? 'reguler',
                'start_time' => $group->start_time,
                'end_time' => $group->end_time,
                'location' => $group->branch?->location ?? $group->branch?->branch_name,
                'status' => 'CONFIRMED',
                'max_participants' => null,
                'metadata' => ['class_schedule_mode' => 'one_day'],
            ],
        );

        $this->syncSessionCoaches($session, $coachIds);
        $session->loadMissing('group.trainingGroup', 'group.privateAthletes', 'branch');
        $this->initializeAttendance->handle($session);
        $result[$session->wasRecentlyCreated ? 'created' : 'updated']++;

        return $result;
    }

    private function syncSessionCoaches(TrainingSession $session, array $coachIds): void
    {
        if (! Schema::hasTable('training_session_coaches')) {
            return;
        }

        $session->assignedCoaches()->sync($coachIds);
    }

    private function isOneDaySchedulable(Group $group): bool
    {
        $isPrivate = ($group->class_type ?? null) === 'private';

        return (bool) (
            ($group->schedule_mode ?? 'weekly') === 'one_day'
            && $group->is_active
            && $group->single_session_date
            && ($isPrivate || ($group->training_group_id && $group->trainingGroup?->is_active))
            && $group->branch?->is_active
            && $group->branch_id
            && $group->start_time
            && $group->end_time
            && (! $isPrivate || ($this->classCoachIds($group) !== [] && $group->privateAthletes->isNotEmpty()))
        );
    }

    private function removeFutureOneDaySessions(Group $group, ?CarbonInterface $exceptDate = null): int
    {
        $query = TrainingSession::query()
            ->where('group_id', $group->group_id)
            ->whereDate('session_date', '>=', now()->toDateString())
            ->whereJsonContains('metadata->class_schedule_mode', 'one_day');

        if ($exceptDate) {
            $query->whereDate('session_date', '!=', $exceptDate->toDateString());
        }

        $sessions = $query->get();
        $count = $sessions->count();
        $sessions->each->delete();

        return $count;
    }

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
        if (! $group->is_active) {
            return $prefix.'. Class is inactive, so future sessions were not generated.';
        }

        return "{$prefix}. Sessions created: {$result['created']}, updated: {$result['updated']}, skipped: {$result['skipped']}, removed: {$result['removed']}.";
    }

    private function canActivate(array $validated): bool
    {
        $classType = str($validated['class_type'] ?? 'reguler')->lower()->slug('_')->toString();
        $scheduleMode = $validated['schedule_mode'] ?? 'weekly';

        return filled($validated['name'] ?? null)
            && filled($validated['branch_id'] ?? null)
            && filled($validated['start_time'] ?? null)
            && filled($validated['end_time'] ?? null)
            && ($scheduleMode === 'one_day' ? filled($validated['single_session_date'] ?? null) : filled($validated['day_of_week'] ?? null))
            && ($classType === 'private'
                ? count($this->coachIds($validated)) > 0 && count($this->privateAthleteIds($validated)) > 0
                : filled($validated['training_group_id'] ?? null));
    }

    private function athletePayload(Group $group): \Illuminate\Support\Collection
    {
        return $this->athletePayloadFromCollection($group->athletes ?? collect());
    }

    private function athletePayloadFromCollection($athletes): \Illuminate\Support\Collection
    {
        return collect($athletes)
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
}
