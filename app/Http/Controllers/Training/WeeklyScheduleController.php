<?php

namespace App\Http\Controllers\Training;

use App\Actions\Sessions\GenerateWeeklyTrainingSessions;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Training\Concerns\BuildsTrainingPayloads;
use App\Models\Branch;
use App\Models\Group;
use App\Models\WeeklyTrainingSchedule;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyScheduleController extends Controller
{
    use BuildsTrainingPayloads;

    public function __construct(private readonly GenerateWeeklyTrainingSessions $sessionGenerator) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canManageSchedule = (bool) ($user?->isAdmin() || $user?->isCoach());
        $coachId = $user?->coachProfile?->coach_id;
        $weekStart = $request->date('from')?->startOfDay() ?? now()->startOfWeek();
        $weekEnd = $request->date('to')?->endOfDay() ?? $weekStart->copy()->endOfWeek();
        $weeklySchedules = $this->weeklyScheduleQuery($weekStart, $weekEnd)->get();
        $branches = Branch::query()->orderBy('branch_name')->get();
        $groups = Group::query()->orderBy('group_name')->get();

        return Inertia::render('WeeklySchedulePage', [
            'title' => 'Jadwal Latihan',
            'subtitle' => 'Jadwal latihan rutin',
            'canManageSchedule' => $canManageSchedule,
            'currentCoachId' => $coachId,
            'weekRange' => ['from' => $weekStart->toDateString(), 'to' => $weekEnd->toDateString()],
            'weeklySchedules' => $this->weeklySchedulePayload($request, $weeklySchedules),
            'branchOptions' => $branches->map(fn (Branch $branch) => ['value' => $branch->branch_id, 'label' => $branch->branch_name])->values(),
            'groupOptions' => $groups->map(fn (Group $group) => ['value' => $group->group_id, 'label' => $group->group_name])->values(),
            'coachOptions' => $this->coachOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeScheduleWrite($request);
        $validated = $this->normalizeScheduleForUser($request, $this->validatedSchedule($request));

        $schedule = WeeklyTrainingSchedule::query()->create($validated);
        $result = $this->sessionGenerator->handle(now()->startOfDay(), now()->copy()->addDays(14)->endOfDay(), [$schedule->weekly_training_schedule_id]);
        ActivityLogger::log($request, 'training_schedule.created', 'training', 'Created weekly training schedule', $schedule, ['title' => $validated['title'], 'auto_created_sessions' => $result['created']]);

        return back()->with('status', "Jadwal mingguan disimpan. Auto-created {$result['created']} sesi latihan untuk 14 hari ke depan; skipped {$result['skipped']} duplikat.");
    }

    public function update(Request $request, WeeklyTrainingSchedule $schedule): RedirectResponse
    {
        abort_unless($this->canManageSchedule($request, $schedule), 403);
        $validated = $this->normalizeScheduleForUser($request, $this->validatedSchedule($request));

        $schedule->update($validated);
        $result = $this->sessionGenerator->handle(now()->startOfDay(), now()->copy()->addDays(14)->endOfDay(), [$schedule->weekly_training_schedule_id]);
        ActivityLogger::log($request, 'training_schedule.updated', 'training', 'Updated weekly training schedule', $schedule, ['title' => $schedule->title, 'auto_created_sessions' => $result['created']]);

        return back()->with('status', "Jadwal mingguan diperbarui. Auto-created {$result['created']} sesi latihan untuk 14 hari ke depan; skipped {$result['skipped']} duplikat.");
    }

    public function destroy(Request $request, WeeklyTrainingSchedule $schedule): RedirectResponse
    {
        abort_unless($this->canManageSchedule($request, $schedule), 403);

        if ($schedule->trainingSessions()->exists()) {
            $schedule->update(['is_active' => false]);
            return back()->with('status', 'Jadwal sudah punya sesi latihan, jadi dinonaktifkan, bukan dihapus.');
        }

        $schedule->delete();
        return back()->with('status', 'Jadwal mingguan dihapus.');
    }

    public function generate(Request $request, GenerateWeeklyTrainingSessions $generator): RedirectResponse
    {
        $this->authorizeScheduleWrite($request);

        $from = $request->date('from')?->startOfDay() ?? now()->startOfWeek();
        $to = $request->date('to')?->endOfDay() ?? $from->copy()->endOfWeek();
        $result = $generator->handle($from, $to);

        return back()->with('status', "Generated {$result['created']} sesi latihan. Skipped {$result['skipped']} duplikat.");
    }

    private function validatedSchedule(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'branch_id' => ['required', 'exists:branches,branch_id'],
            'group_id' => ['nullable', 'exists:class_groups,group_id'],
            'coach_id' => ['nullable', 'exists:coaches,coach_id'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }

    private function normalizeScheduleForUser(Request $request, array $validated): array
    {
        if ($request->user()?->isCoach() && ! $request->user()?->isAdmin()) {
            $validated['coach_id'] = $request->user()?->coachProfile?->coach_id;
        }

        $validated['is_active'] = (bool) ($validated['is_active'] ?? true);

        return $validated;
    }

    private function authorizeScheduleWrite(Request $request): void
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isCoach(), 403);
    }
}
