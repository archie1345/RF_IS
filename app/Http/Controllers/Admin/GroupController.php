<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Sessions\GenerateWeeklyTrainingSessions;
use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\WeeklyTrainingSchedule;
use App\Support\ActivityLogger;
use App\Support\Domain\BeltRank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    private const CLASS_TYPES = ['reguler', 'prestasi', 'private', 'pemula', 'sparring'];

    public function __construct(private readonly GenerateWeeklyTrainingSessions $sessionGenerator) {}

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validatedGroup($request);
        $group = Group::create($this->payload($validated));
        $schedule = $this->syncWeeklySchedule($group->refresh());
        $result = $this->generateSessionsForSchedule($schedule);

        ActivityLogger::log($request, 'admin.group.created', 'admin', 'Created group', $group, ['group_name' => $group->group_name, 'auto_created_sessions' => $result['created']]);

        return back()->with('status', "Class saved and linked to weekly schedule. Auto-created {$result['created']} sessions; updated {$result['updated']} future sessions; removed {$result['removed']} obsolete future sessions; skipped {$result['skipped']} past/duplicate sessions.");
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $existingSchedule = WeeklyTrainingSchedule::query()->where('group_id', $group->group_id)->first();
        $validated = $this->validatedGroup($request);
        $group->update($this->payload($validated));
        $schedule = $this->syncWeeklySchedule($group->refresh());
        $result = $this->generateSessionsForSchedule($schedule);

        if (! $schedule && $existingSchedule) {
            $result['removed'] += $this->sessionGenerator->removeFutureSessionsForSchedule($existingSchedule);
        }

        ActivityLogger::log($request, 'admin.group.updated', 'admin', 'Updated group', $group, [
            'group_name' => $group->group_name,
            'auto_created_sessions' => $result['created'],
            'updated_future_sessions' => $result['updated'],
            'removed_future_sessions' => $result['removed'],
        ]);

        return back()->with('status', "Class updated and weekly schedule synced. Auto-created {$result['created']} sessions; updated {$result['updated']} future sessions; removed {$result['removed']} obsolete future sessions; skipped {$result['skipped']} past/duplicate sessions.");
    }

    public function athletes(Request $request, Group $group): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $group->load(['athletes.user:id,name', 'athletes.branch:branch_id,branch_name']);

        return response()->json([
            'athletes' => $this->athletePayload($group),
        ]);
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $hasAthletes = $group->athletes()->exists();
        $hasSessions = TrainingSession::query()->where('group_id', $group->group_id)->exists();
        $schedule = WeeklyTrainingSchedule::query()->where('group_id', $group->group_id)->first();

        if ($hasAthletes || $hasSessions) {
            $group->update(['is_active' => false]);
            $schedule?->update(['is_active' => false]);
            $removed = $this->sessionGenerator->removeFutureSessionsForSchedule($schedule);

            return back()->with('status', "Class has linked athletes or sessions, so it was deactivated instead of deleted. Removed {$removed} future generated sessions; past sessions were kept.");
        }

        $this->sessionGenerator->removeFutureSessionsForSchedule($schedule);
        $schedule?->delete();
        ActivityLogger::log($request, 'admin.group.deleted', 'admin', 'Deleted group', $group, ['group_name' => $group->group_name]);
        $group->delete();

        return back()->with('status', 'Class deleted.');
    }

    private function validatedGroup(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'class_type' => ['required', 'string', Rule::in(self::CLASS_TYPES)],
            'coach_id' => ['nullable', 'exists:coaches,coach_id'],
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
        $validated['class_type'] = $classType;

        return [
            'group_name' => $validated['name'],
            'class_type' => $classType,
            'coach_id' => $classType === 'private' ? ($validated['coach_id'] ?? null) : null,
            'branch_id' => $validated['branch_id'] ?? null,
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'min_belt' => BeltRank::normalize($validated['min_belt'] ?? null) ?: null,
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true) && $this->canActivate($validated),
        ];
    }

    private function syncWeeklySchedule(Group $group): ?WeeklyTrainingSchedule
    {
        $group->loadMissing('branch');

        $existing = WeeklyTrainingSchedule::query()->where('group_id', $group->group_id)->first();
        $isSchedulable = (bool) (
            $group->is_active
            && $group->branch?->is_active
            && $group->branch_id
            && $group->day_of_week
            && $group->start_time
            && $group->end_time
            && (($group->class_type ?? null) !== 'private' || filled($group->coach_id))
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
                'dedicated_athlete_id' => null,
                'coach_id' => $sessionType === 'private' ? $group->coach_id : null,
                'session_type' => $sessionType,
                'day_of_week' => $group->day_of_week,
                'start_time' => $group->start_time,
                'end_time' => $group->end_time,
                'location' => $group->branch?->location ?? $group->branch?->branch_name,
                'is_active' => true,
            ],
        );
    }

    /**
     * @return array{created:int, skipped:int, updated:int, removed:int, from:string, to:string}
     */
    private function generateSessionsForSchedule(?WeeklyTrainingSchedule $schedule): array
    {
        if (! $schedule) {
            return [
                'created' => 0,
                'skipped' => 0,
                'updated' => 0,
                'removed' => 0,
                'from' => now()->toDateString(),
                'to' => now()->toDateString(),
            ];
        }

        return $this->sessionGenerator->handle(
            now()->startOfDay(),
            now()->copy()->addDays(14)->endOfDay(),
            [$schedule->weekly_training_schedule_id],
        );
    }

    private function athletePayload(Group $group): array
    {
        return $group->athletes
            ->sortBy(fn (Athlete $athlete) => $athlete->user?->name ?? '')
            ->map(fn (Athlete $athlete) => [
                'id' => $athlete->athlete_id,
                'name' => $athlete->user?->name ?? ('Atlet #'.$athlete->athlete_id),
                'geup' => $athlete->geup,
                'branch' => $athlete->branch?->branch_name,
            ])
            ->values()
            ->all();
    }

    private function canActivate(array $validated): bool
    {
        $classType = $validated['class_type'] ?? null;
        $branchId = $validated['branch_id'] ?? null;

        return filled($validated['name'] ?? null)
            && in_array($classType, self::CLASS_TYPES, true)
            && filled($branchId)
            && Branch::query()->where('branch_id', $branchId)->where('is_active', true)->exists()
            && filled($validated['day_of_week'] ?? null)
            && filled($validated['start_time'] ?? null)
            && filled($validated['end_time'] ?? null)
            && ($classType !== 'private' || filled($validated['coach_id'] ?? null));
    }
}
