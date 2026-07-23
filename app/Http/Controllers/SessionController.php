<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\InitializeSessionAttendance;
use App\Actions\Sessions\CreateSession;
use App\Actions\Sessions\UpdateSession;
use App\Http\Controllers\Concerns\FormatsPresentationData;
use App\Http\Requests\Sessions\StoreSessionRequest;
use App\Http\Requests\Sessions\UpdateSessionRequest;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\CoachAttendance;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Presenters\SessionRowPresenter;
use App\Services\SessionVisibilityService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    use FormatsPresentationData;

    public function __construct(
        private readonly SessionVisibilityService $sessionVisibility,
        private readonly SessionRowPresenter $sessionRows,
        private readonly CreateSession $createSession,
        private readonly UpdateSession $updateSession,
        private readonly InitializeSessionAttendance $initializeAttendance,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorize('viewAny', TrainingSession::class);
        $currentCoachId = $this->sessionVisibility->coachProfileIdFor($user);
        $hasCoachPivot = $this->sessionVisibility->hasCoachPivotTable();
        $now = now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');
        $visibility = $request->query('visibility', 'upcoming');
        $visibility = $visibility === 'past' ? 'archived' : $visibility;
        $visibility = in_array($visibility, ['upcoming', 'archived', 'all'], true) ? $visibility : 'upcoming';

        $with = ['primaryCoach.user:id,name', 'branch:branch_id,branch_name', 'group:group_id,group_name,schedule_mode,single_session_date,class_type'];
        if ($hasCoachPivot) {
            $with[] = 'assignedCoaches.user:id,name';
        }

        $sessionsQuery = TrainingSession::query()->with($with);
        $this->applySessionVisibility($sessionsQuery, $visibility, $today, $currentTime);

        $sessions = $sessionsQuery
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        $archivedCountQuery = TrainingSession::query();
        $this->applySessionVisibility($archivedCountQuery, 'archived', $today, $currentTime);
        $archivedCount = $archivedCountQuery->count();

        $upcomingCountQuery = TrainingSession::query();
        $this->applySessionVisibility($upcomingCountQuery, 'upcoming', $today, $currentTime);
        $upcomingCount = $upcomingCountQuery->count();

        return Inertia::render('SessionsPage', [
            'metrics' => [
                ['label' => 'Scheduled sessions', 'value' => (string) $sessions->count(), 'detail' => $visibility === 'all' ? 'All visible sessions' : ($visibility === 'archived' ? 'Archived sessions only' : 'Current and future sessions'), 'tone' => 'info'],
                ['label' => 'Confirmed coverage', 'value' => (string) $sessions->where('status', 'CONFIRMED')->count(), 'detail' => 'Sessions fully staffed and approved', 'tone' => 'success'],
                ['label' => 'Need support', 'value' => (string) $sessions->where('status', 'NEEDS_ASSISTANT')->count(), 'detail' => 'Still waiting for coach support', 'tone' => 'warning'],
            ],
            'filters' => [
                'visibility' => $visibility,
                'archived_count' => $archivedCount,
                'upcoming_count' => $upcomingCount,
                'all_count' => $archivedCount + $upcomingCount,
            ],
            'rows' => $sessions->map(fn (TrainingSession $session) => $this->sessionRows->row($session, $currentCoachId))->values(),
            'branches' => Branch::query()->orderBy('branch_name')->get(['branch_id as value', 'branch_name as label']),
            'groups' => Group::query()->orderBy('group_name')->get(['group_id as value', 'group_name as label']),
            'coaches' => $sessions->contains(fn (TrainingSession $session): bool => $this->sessionUsesPrivateGroup($session))
                ? $this->coachOptions()
                : collect(),
        ]);
    }

    public function store(StoreSessionRequest $request): RedirectResponse
    {
        [$session, $defaultAbsentCount] = $this->createSession->handle($request->user(), $request->validated());

        ActivityLogger::log(
            $request,
            'session.created',
            'session',
            'Created training session',
            $session,
            ['title' => $session->title, 'session_date' => $session->session_date, 'default_absent_records' => $defaultAbsentCount],
        );

        return redirect()->route('sessions.index');
    }

    public function update(UpdateSessionRequest $request, TrainingSession $session): RedirectResponse
    {
        $this->updateSession->handle($request->user(), $session, $request->validated());

        return redirect()->route('sessions.index');
    }

    public function destroy(TrainingSession $session): RedirectResponse
    {
        $this->authorize('update', $session);
        $session->delete();

        return redirect()->route('sessions.index');
    }

    public function join(TrainingSession $session, Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isCoach(), 403);

        $coachId = Coach::query()->where('id', $user->id)->value('coach_id');
        if (! $coachId) {
            return back()->withErrors(['coach_id' => 'Coach profile not found.']);
        }

        if (! $this->sessionVisibility->hasCoachPivotTable()) {
            return back()->withErrors(['coach_id' => 'Multi-coach table not ready yet. Please run migrations.']);
        }

        $session->assignedCoaches()->syncWithoutDetaching([$coachId]);

        return back();
    }

    public function attendanceSheet(TrainingSession $session): Response
    {
        $user = request()->user();
        abort_unless($user?->isAdmin() || $user?->isCoach(), 403);

        $with = ['primaryCoach.user:id,name', 'branch:branch_id,branch_name', 'group:group_id,group_name,class_type'];
        if ($this->sessionVisibility->hasCoachPivotTable()) {
            $with[] = 'assignedCoaches.user:id,name';
        }
        $session->load($with);
        $isPrivateSession = $this->sessionUsesPrivateGroup($session);

        $this->initializeAttendance->handle($session);

        $attendance = Attendance::query()
            ->with('athlete.user:id,name')
            ->where('training_session_id', $session->training_session_id)
            ->orderBy('athlete_id')
            ->get();

        if (Schema::hasTable('coach_attendance')) {
            $assignedCoachIds = collect([$session->coach_id])
                ->filter()
                ->when($this->sessionVisibility->hasCoachPivotTable(), fn ($collection) => $collection->merge($session->assignedCoaches->pluck('coach_id')))
                ->unique()
                ->values();

            foreach ($assignedCoachIds as $coachId) {
                CoachAttendance::query()->firstOrCreate(
                    ['training_session_id' => $session->training_session_id, 'coach_id' => $coachId],
                    ['status' => 'TEACH']
                );
            }
        }

        $coachAttendance = Schema::hasTable('coach_attendance')
            ? CoachAttendance::query()
                ->with('coach.user:id,name')
                ->where('training_session_id', $session->training_session_id)
                ->orderBy('coach_attendance_id')
                ->get()
            : collect();

        $athletePresentCount = $attendance->where('status', 'PRESENT')->count();
        $coachTeachCount = $coachAttendance->where('status', 'TEACH')->count();

        return Inertia::render('SessionAttendancePage', [
            'session' => [
                'id' => $session->training_session_id,
                'title' => $session->title,
                'date' => $this->formatIsoDate($session->session_date),
                'start_time' => $session->start_time ? Carbon::parse((string) $session->start_time)->format('H:i') : null,
                'end_time' => $session->end_time ? Carbon::parse((string) $session->end_time)->format('H:i') : null,
                'branch_id' => $session->branch_id,
                'group_id' => $session->group_id,
                'location' => $session->location,
                'status' => $session->status,
                'branch' => $session->branch?->branch_name ?? 'Unassigned',
                'group' => $session->group?->group_name ?? 'All groups',
                'coach' => $this->coachNames($session),
                'is_private' => $isPrivateSession,
                'athlete_attendance_summary' => $athletePresentCount.' / '.$attendance->count(),
                'coach_attendance_summary' => $coachTeachCount.' / '.$coachAttendance->count(),
                'attendance_qr' => [
                    'is_active' => $session->attendance_token_hash !== null && $session->attendance_qr_revoked_at === null,
                    'opens_at' => $session->attendance_opens_at?->toIso8601String(),
                    'closes_at' => $session->attendance_closes_at?->toIso8601String(),
                    'generated_at' => $session->attendance_qr_generated_at?->toIso8601String(),
                    'revoked_at' => $session->attendance_qr_revoked_at?->toIso8601String(),
                ],
            ],
            'rows' => $attendance->map(fn (Attendance $row) => [
                'id' => 'ATT-'.$row->athlete_attendance_id,
                'athlete' => $row->athlete?->user?->name ?? 'Unknown athlete',
                'status' => $this->attendanceBadge((string) $row->status),
            ])->values(),
            'branches' => Branch::query()->orderBy('branch_name')->get(['branch_id as value', 'branch_name as label']),
            'groups' => Group::query()->orderBy('group_name')->get(['group_id as value', 'group_name as label']),
            'coachRows' => $coachAttendance->map(fn (CoachAttendance $row) => [
                'id' => 'SCA-'.$row->coach_attendance_id,
                'coach' => $row->coach?->user?->name ?? 'Unknown coach',
                'status' => $row->status === 'TEACH' ? $this->badge('Teach', 'success') : $this->badge('Not teach', 'danger'),
                'checked_at' => $row->checked_at ? Carbon::parse((string) $row->checked_at)->format('d/m/Y H:i') : '-',
            ])->values(),
            'coachOptions' => $isPrivateSession ? $this->coachOptions() : collect(),
        ]);
    }

    public function addCoachAttendance(TrainingSession $session, Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isCoach(), 403);

        $session->loadMissing('group:group_id,group_name,class_type');
        if (! $this->sessionUsesPrivateGroup($session)) {
            return back()->withErrors(['coach_id' => 'Coach selection is only available for private class sessions.']);
        }

        if (! Schema::hasTable('coach_attendance')) {
            return back()->withErrors(['coach_id' => 'Coach attendance table not ready. Run migrations first.']);
        }

        $validated = $request->validate([
            'coach_id' => ['required', 'exists:coaches,coach_id'],
        ]);

        CoachAttendance::query()->updateOrCreate(
            ['training_session_id' => $session->training_session_id, 'coach_id' => $validated['coach_id']],
            ['status' => 'TEACH', 'checked_at' => now()]
        );

        if ($this->sessionVisibility->hasCoachPivotTable()) {
            $session->assignedCoaches()->syncWithoutDetaching([$validated['coach_id']]);
        }

        return back();
    }

    public function updateCoachAttendance(Request $request, CoachAttendance $coachAttendance): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isCoach(), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['TEACH', 'NOT_TEACH'])],
        ]);

        $coachAttendance->update([
            'status' => $validated['status'],
            'checked_at' => now(),
        ]);

        return back();
    }

    public function destroyCoachAttendance(Request $request, CoachAttendance $coachAttendance): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isCoach(), 403);
        $coachAttendance->delete();

        return back();
    }

    private function applySessionVisibility($query, string $visibility, string $today, string $currentTime)
    {
        if ($visibility === 'upcoming') {
            return $query->where(function ($query) use ($today, $currentTime): void {
                $query->whereDate('session_date', '>', $today)
                    ->orWhere(function ($sameDay) use ($today, $currentTime): void {
                        $sameDay->whereDate('session_date', $today)
                            ->where(function ($timeQuery) use ($currentTime): void {
                                $timeQuery->whereTime('end_time', '>=', $currentTime)
                                    ->orWhere(function ($missingEndTime) use ($currentTime): void {
                                        $missingEndTime->whereNull('end_time')
                                            ->whereTime('start_time', '>=', $currentTime);
                                    });
                            });
                    });
            });
        }

        if ($visibility === 'archived') {
            return $query->where(function ($query) use ($today, $currentTime): void {
                $query->whereDate('session_date', '<', $today)
                    ->orWhere(function ($sameDay) use ($today, $currentTime): void {
                        $sameDay->whereDate('session_date', $today)
                            ->where(function ($timeQuery) use ($currentTime): void {
                                $timeQuery->whereTime('end_time', '<', $currentTime)
                                    ->orWhere(function ($missingEndTime) use ($currentTime): void {
                                        $missingEndTime->whereNull('end_time')
                                            ->whereTime('start_time', '<', $currentTime);
                                    });
                            });
                    });
            });
        }

        return $query;
    }

    private function attendanceBadge(string $status): array
    {
        return match ($status) {
            'PRESENT' => $this->badge('Present', 'success'),
            'EXCUSED' => $this->badge('Excused', 'info'),
            default => $this->badge('Absent', 'danger'),
        };
    }

    private function coachNames(TrainingSession $session): string
    {
        $names = collect();
        if ($session->primaryCoach?->user?->name) {
            $names->push($session->primaryCoach->user->name);
        }

        $assistantNames = $this->sessionVisibility->hasCoachPivotTable()
            ? $session->assignedCoaches
                ->map(fn (Coach $coach) => $coach->user?->name)
                ->filter()
                ->values()
            : collect();

        return $names
            ->concat($assistantNames)
            ->unique()
            ->join(', ') ?: 'Unassigned';
    }

    private function sessionUsesPrivateGroup(TrainingSession $session): bool
    {
        return strtolower((string) ($session->session_type ?? '')) === 'private'
            || strtolower((string) ($session->group?->class_type ?? '')) === 'private';
    }

    private function coachOptions(): Collection
    {
        return Coach::query()
            ->with('user:id,name')
            ->get()
            ->map(fn (Coach $coach): array => ['value' => $coach->coach_id, 'label' => $coach->user?->name ?? 'Unknown coach'])
            ->sortBy('label')
            ->values();
    }

    private function formatDateYmd(mixed $value): string
    {
        return Carbon::parse((string) $value)->format('d/m/Y');
    }

    private function formatTime24(mixed $value): string
    {
        return Carbon::parse((string) $value)->format('H:i');
    }

    private function formatIsoDate(mixed $value): string
    {
        return Carbon::parse((string) $value)->format('Y-m-d');
    }
}
