<?php

namespace App\Http\Controllers;

use App\Actions\Sessions\GenerateWeeklyTrainingSessions;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\WeeklyTrainingSchedule;
use App\Support\ActivityLogger;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TrainingManagementController extends Controller
{
    public function __construct(private readonly GenerateWeeklyTrainingSessions $sessionGenerator) {}

    public function index(Request $request): Response
    {
        if ($request->is('training-schedule')) {
            return $this->schedule($request);
        }

        if ($request->is('admin/locations')) {
            return $this->locations($request);
        }

        if ($request->is('admin/classes')) {
            return $this->classes($request);
        }

        $user = $request->user();
        $canManageStructure = (bool) $user?->isAdmin();
        $canManageSchedule = (bool) ($user?->isAdmin() || $user?->isCoach());

        $weekStart = $request->date('from')?->startOfDay() ?? now()->startOfWeek();
        $weekEnd = $request->date('to')?->endOfDay() ?? $weekStart->copy()->endOfWeek();
        $weeklySchedules = $this->weeklyScheduleQuery($weekStart, $weekEnd)->get();
        $branches = Branch::query()->withCount(['groups', 'athletes'])->orderBy('branch_name')->get();
        $groups = Group::query()->with(['branch', 'coach.user'])->withCount('athletes')->orderBy('group_name')->get();

        $sessions = TrainingSession::query()
            ->with(['branch', 'group', 'primaryCoach.user', 'weeklyTrainingSchedule'])
            ->whereBetween('session_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where('status', '!=', 'CANCELED')
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return Inertia::render('TrainingManagementPage', [
            'title' => 'Manajemen Latihan',
            'subtitle' => 'Ringkasan training flow. Lokasi, Kelas, dan Jadwal Latihan sudah dipisah ke halaman khusus.',
            'canManageStructure' => $canManageStructure,
            'canManageSchedule' => $canManageSchedule,
            'weekRange' => ['from' => $weekStart->toDateString(), 'to' => $weekEnd->toDateString()],
            'branches' => $branches->map(fn (Branch $branch) => $this->branchPayload($branch))->values(),
            'groups' => $this->groupPayload($groups, $weeklySchedules),
            'weeklySchedules' => $this->weeklySchedulePayload($request, $weeklySchedules),
            'sessions' => $sessions->map(fn (TrainingSession $session) => [
                'id' => $session->training_session_id,
                'weekly_training_schedule_id' => $session->weekly_training_schedule_id,
                'title' => $session->title,
                'date' => optional($session->session_date)->format('Y-m-d'),
                'day_label' => $session->session_date ? $this->dayName(Carbon::parse((string) $session->session_date)->isoWeekday()) : '-',
                'time' => substr((string) $session->start_time, 0, 5).' - '.substr((string) $session->end_time, 0, 5),
                'branch' => $session->branch?->branch_name ?? 'Belum ada lokasi',
                'group' => $session->group?->group_name ?? 'All groups',
                'coach' => $session->primaryCoach?->user?->name ?? 'Belum ada coach',
                'status' => $session->status,
            ])->values(),
            'branchOptions' => $branches->map(fn (Branch $branch) => ['value' => $branch->branch_id, 'label' => $branch->branch_name])->values(),
            'groupOptions' => $groups->map(fn (Group $group) => ['value' => $group->group_id, 'label' => $group->group_name])->values(),
            'coachOptions' => $this->coachOptions(),
            'beltOptions' => $this->beltOptions(),
        ]);
    }

    public function locations(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $locations = Branch::query()
            ->withCount(['groups', 'athletes'])
            ->orderBy('branch_name')
            ->get()
            ->map(fn (Branch $branch) => $this->branchPayload($branch))
            ->values();

        return Inertia::render('AdminLocationsPage', [
            'title' => 'Lokasi Latihan',
            'subtitle' => 'Master data dojang / lokasi latihan RTFCM.',
            'locations' => $locations,
        ]);
    }

    public function classes(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $weeklySchedules = WeeklyTrainingSchedule::query()->get();
        $groups = Group::query()
            ->with(['branch', 'coach.user'])
            ->withCount('athletes')
            ->orderBy('group_name')
            ->get();
        $branches = Branch::query()->orderBy('branch_name')->get();

        return Inertia::render('AdminClassesPage', [
            'title' => 'Kelas Latihan',
            'subtitle' => 'Master data kelas. Jadwal mingguan otomatis sinkron dari data kelas.',
            'classes' => $this->groupPayload($groups, $weeklySchedules),
            'branchOptions' => $branches->map(fn (Branch $branch) => ['value' => $branch->branch_id, 'label' => $branch->branch_name])->values(),
            'coachOptions' => $this->coachOptions(),
            'beltOptions' => $this->beltOptions(),
        ]);
    }

    public function schedule(Request $request): Response
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
            'subtitle' => 'Jadwal latihan rutin RTFCM',
            'canManageSchedule' => $canManageSchedule,
            'currentCoachId' => $coachId,
            'weekRange' => ['from' => $weekStart->toDateString(), 'to' => $weekEnd->toDateString()],
            'weeklySchedules' => $this->weeklySchedulePayload($request, $weeklySchedules),
            'branchOptions' => $branches->map(fn (Branch $branch) => ['value' => $branch->branch_id, 'label' => $branch->branch_name])->values(),
            'groupOptions' => $groups->map(fn (Group $group) => ['value' => $group->group_id, 'label' => $group->group_name])->values(),
            'coachOptions' => $this->coachOptions(),
        ]);
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $this->authorizeScheduleWrite($request);
        $validated = $this->normalizeScheduleForUser($request, $this->validatedSchedule($request));

        $schedule = WeeklyTrainingSchedule::query()->create($validated);
        $attachScheduleCoach = $request->user()?->isCoach() === true;
        $result = $this->sessionGenerator->handle(now()->startOfDay(), now()->copy()->addDays(14)->endOfDay(), [$schedule->weekly_training_schedule_id], $attachScheduleCoach);
        ActivityLogger::log($request, 'training_schedule.created', 'training', 'Created weekly training schedule', $schedule, ['title' => $validated['title'], 'auto_created_sessions' => $result['created']]);

        return back()->with('status', "Jadwal mingguan disimpan. Auto-created {$result['created']} sesi latihan untuk 14 hari ke depan; skipped {$result['skipped']} duplikat.");
    }

    public function updateSchedule(Request $request, WeeklyTrainingSchedule $schedule): RedirectResponse
    {
        abort_unless($this->canManageSchedule($request, $schedule), 403);
        $validated = $this->normalizeScheduleForUser($request, $this->validatedSchedule($request));

        $schedule->update($validated);
        $attachScheduleCoach = $request->user()?->isCoach() === true;
        $result = $this->sessionGenerator->handle(now()->startOfDay(), now()->copy()->addDays(14)->endOfDay(), [$schedule->weekly_training_schedule_id], $attachScheduleCoach);
        ActivityLogger::log($request, 'training_schedule.updated', 'training', 'Updated weekly training schedule', $schedule, ['title' => $schedule->title, 'auto_created_sessions' => $result['created']]);

        return back()->with('status', "Jadwal mingguan diperbarui. Auto-created {$result['created']} sesi latihan untuk 14 hari ke depan; skipped {$result['skipped']} duplikat.");
    }

    public function destroySchedule(Request $request, WeeklyTrainingSchedule $schedule): RedirectResponse
    {
        abort_unless($this->canManageSchedule($request, $schedule), 403);

        if ($schedule->trainingSessions()->exists()) {
            $schedule->update(['is_active' => false]);
            return back()->with('status', 'Jadwal sudah punya sesi latihan, jadi dinonaktifkan, bukan dihapus.');
        }

        $schedule->delete();
        return back()->with('status', 'Jadwal mingguan dihapus.');
    }

    public function generateSessions(Request $request, GenerateWeeklyTrainingSessions $generator): RedirectResponse
    {
        $this->authorizeScheduleWrite($request);

        $from = $request->date('from')?->startOfDay() ?? now()->startOfWeek();
        $to = $request->date('to')?->endOfDay() ?? $from->copy()->endOfWeek();
        $attachScheduleCoach = $request->user()?->isCoach() === true;
        $result = $generator->handle($from, $to, null, $attachScheduleCoach);

        return back()->with('status', "Generated {$result['created']} sesi latihan. Skipped {$result['skipped']} duplikat.");
    }

    private function weeklyScheduleQuery(CarbonInterface $weekStart, CarbonInterface $weekEnd)
    {
        return WeeklyTrainingSchedule::query()
            ->with([
                'branch',
                'group' => fn ($query) => $query->withCount('athletes'),
                'coach.user',
            ])
            ->withCount(['trainingSessions as generated_sessions_count' => fn ($query) => $query->whereBetween('session_date', [$weekStart->toDateString(), $weekEnd->toDateString()])])
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
            'group' => $schedule->group?->group_name ?? 'All groups',
            'coach_id' => $schedule->coach_id,
            'coach' => $schedule->coach?->user?->name ?? 'Belum ada coach',
            'day_of_week' => $schedule->day_of_week,
            'day_label' => $this->dayName((int) $schedule->day_of_week),
            'start_time' => $schedule->start_time ? substr((string) $schedule->start_time, 0, 5) : '',
            'end_time' => $schedule->end_time ? substr((string) $schedule->end_time, 0, 5) : '',
            'location' => $schedule->location,
            'is_active' => (bool) $schedule->is_active,
            'generated_sessions_count' => $schedule->generated_sessions_count,
            'can_manage' => $this->canManageSchedule($request, $schedule),
            'class_type' => $schedule->group?->class_type,
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

            return [
                'id' => $group->group_id,
                'name' => $group->group_name,
                'class_type' => $group->class_type ?? 'General',
                'branch_id' => $group->branch_id,
                'branch' => $group->branch?->branch_name ?? 'Belum ada lokasi',
                'coach_id' => $group->coach_id,
                'coach' => $group->coach?->user?->name ?? 'Belum ada coach',
                'day_of_week' => $group->day_of_week,
                'day_label' => $this->dayName((int) ($group->day_of_week ?? 1)),
                'start_time' => $group->start_time ? substr((string) $group->start_time, 0, 5) : '',
                'end_time' => $group->end_time ? substr((string) $group->end_time, 0, 5) : '',
                'min_belt' => $group->min_belt,
                'description' => $group->description,
                'athletes_count' => $group->athletes_count ?? 0,
                'is_active' => (bool) ($group->is_active ?? true),
                'weekly_schedule_id' => $schedule?->weekly_training_schedule_id,
                'weekly_schedule_status' => $schedule ? ($schedule->is_active ? 'Aktif' : 'Nonaktif') : 'Belum terhubung',
            ];
        })->values();
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

    private function beltOptions()
    {
        $groupBelts = Group::query()->whereNotNull('min_belt')->where('min_belt', '!=', '')->distinct()->orderBy('min_belt')->pluck('min_belt');

        return $groupBelts->filter()->unique()->values()->map(fn ($belt) => ['value' => (string) $belt, 'label' => (string) $belt]);
    }

    private function dayName(int $day): string
    {
        return [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'][$day] ?? '-';
    }
}
