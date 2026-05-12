<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Session;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\SessionCoachAttendance;
use App\Models\Group;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SessionManagementController extends Controller
{
    use FormatsMvpData;

    public function index(): Response
    {
        $user = request()->user();
        abort_unless($user?->isAdmin() || $user?->isCoach(), 403);
        $currentCoachId = $user?->isCoach() ? Coach::query()->where('id', $user->id)->value('coach_id') : null;
        $hasCoachPivot = $this->hasCoachPivotTable();

        $with = ['coach.user:id,name', 'branch:branch_id,branch_name', 'group:group_id,group_name'];
        if ($hasCoachPivot) {
            $with[] = 'coaches.user:id,name';
        }

        $sessions = Session::query()
            ->with($with)
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();

        return Inertia::render('SessionsPage', [
            'metrics' => [
                ['label' => 'Scheduled sessions', 'value' => (string) $sessions->count(), 'detail' => 'Across all active branches', 'tone' => 'info'],
                ['label' => 'Confirmed coverage', 'value' => (string) $sessions->where('status', 'CONFIRMED')->count(), 'detail' => 'Sessions fully staffed and approved', 'tone' => 'success'],
                ['label' => 'Need support', 'value' => (string) $sessions->where('status', 'NEEDS_ASSISTANT')->count(), 'detail' => 'Still waiting for coach support', 'tone' => 'warning'],
            ],
            'rows' => $sessions->map(fn (Session $session) => [
                'id' => 'SES-'.$session->csid,
                'session_id' => $session->csid,
                'session' => $session->title,
                'branch' => $session->branch?->branch_name ?? 'Unassigned',
                'group' => $session->group?->group_name ?? 'All groups',
                'coach' => $this->coachNames($session),
                'schedule' => $this->formatDateYmd($session->session_date).' '.$this->formatTime24($session->start_time).' - '.$this->formatTime24($session->end_time),
                'status' => $this->sessionBadge((string) $session->status),
                'location' => $session->location,
                'session_date' => $this->formatIsoDate($session->session_date),
                'start_time' => $this->formatTime24($session->start_time),
                'end_time' => $this->formatTime24($session->end_time),
                'branch_id' => $session->branch_id,
                'group_id' => $session->group_id,
                'coach_id' => $session->coach_id,
                'status_value' => $session->status,
                'can_join' => $currentCoachId
                    ? ! ($session->coach_id === (int) $currentCoachId || ($hasCoachPivot && $session->coaches->contains('coach_id', (int) $currentCoachId)))
                    : false,
            ])->values(),
            'branches' => Branch::query()->orderBy('branch_name')->get(['branch_id as value', 'branch_name as label']),
            'groups' => Group::query()->orderBy('group_name')->get(['group_id as value', 'group_name as label']),
            'coaches' => Coach::query()
                ->with('user:id,name')
                ->get()
                ->map(fn (Coach $coach) => ['value' => $coach->coach_id, 'label' => $coach->user?->name ?? 'Unknown coach'])
                ->sortBy('label')
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin() || $user?->isCoach(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'branch_id' => ['required', 'exists:branches,branch_id'],
            'group_id' => ['nullable', 'exists:class_groups,group_id'],
            'location' => ['nullable', 'string', 'max:255'],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'status' => ['required', Rule::in(['DRAFT', 'CONFIRMED', 'NEEDS_ASSISTANT', 'CANCELED'])],
        ]);

        $validated['coach_id'] = $this->resolveSessionCoachId($user?->id, null);

        if (empty($validated['coach_id'])) {
            return back()->withErrors(['coach_id' => 'Coach is required for attendance session.']);
        }

        $session = Session::create($validated);
        if ($this->hasCoachPivotTable()) {
            $session->coaches()->syncWithoutDetaching([$validated['coach_id']]);
        }
        $athletes = Athlete::query()
            ->where('branch_id', $session->branch_id)
            ->when($session->group_id, fn ($query) => $query->where('group_id', $session->group_id))
            ->pluck('athlete_id');

        foreach ($athletes as $athleteId) {
            Attendance::create([
                'athlete_id' => $athleteId,
                'coach_session_id' => $session->csid,
                'date' => $session->session_date,
                'status' => 'ABSENT',
            ]);
        }

        ActivityLogger::log(
            $request,
            'session.created',
            'session',
            'Created training session',
            $session,
            ['title' => $session->title, 'session_date' => $session->session_date, 'default_absent_records' => $athletes->count()],
        );

        return redirect()->route('sessions.index');
    }

    public function update(Request $request, Session $session): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isAdmin() || $user?->isCoach(), 403);
        // if ($user?->isCoach()) {
        //     abort_unless($this->coachCanAccessSession($user->id, $session), 403);
        // }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'branch_id' => ['required', 'exists:branches,branch_id'],
            'group_id' => ['nullable', 'exists:class_groups,group_id'],
            'location' => ['nullable', 'string', 'max:255'],
            'session_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'status' => ['required', Rule::in(['DRAFT', 'CONFIRMED', 'NEEDS_ASSISTANT', 'CANCELED'])],
        ]);

        $validated['coach_id'] = $this->resolveSessionCoachId($user?->id, $session->coach_id);

        if (empty($validated['coach_id'])) {
            return back()->withErrors(['coach_id' => 'Coach is required for attendance session.']);
        }

        $session->update($validated);
        if ($this->hasCoachPivotTable()) {
            $session->coaches()->syncWithoutDetaching([$validated['coach_id']]);
        }

        return redirect()->route('sessions.index');
    }

    public function destroy(Session $session): RedirectResponse
    {
        $user = request()->user();
        abort_unless($user?->isAdmin() || $user?->isCoach(), 403);
        // if ($user?->isCoach()) {
        //     abort_unless($this->coachCanAccessSession($user->id, $session), 403);
        // }
        $session->delete();

        return redirect()->route('sessions.index');
    }

    public function join(Session $session, Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isCoach(), 403);

        $coachId = Coach::query()->where('id', $user->id)->value('coach_id');
        if (! $coachId) {
            return back()->withErrors(['coach_id' => 'Coach profile not found.']);
        }

        if (! $this->hasCoachPivotTable()) {
            return back()->withErrors(['coach_id' => 'Multi-coach table not ready yet. Please run migrations.']);
        }

        $session->coaches()->syncWithoutDetaching([$coachId]);

        return back();
    }

    public function attendanceSheet(Session $session): Response
    {
        $user = request()->user();
        abort_unless($user?->isAdmin() || $user?->isCoach(), 403);

        // if ($user?->isCoach()) {
        //     abort_unless($this->coachCanAccessSession($user->id, $session), 403);
        // }

        $with = ['coach.user:id,name', 'branch:branch_id,branch_name', 'group:group_id,group_name'];
        if ($this->hasCoachPivotTable()) {
            $with[] = 'coaches.user:id,name';
        }
        $session->load($with);

        $athletes = Athlete::query()
            ->with('user:id,name')
            ->where('branch_id', $session->branch_id)
            ->when($session->group_id, fn ($query) => $query->where('group_id', $session->group_id))
            ->orderBy('athlete_id')
            ->get();

        foreach ($athletes as $athlete) {
            Attendance::query()->firstOrCreate(
                [
                    'athlete_id' => $athlete->athlete_id,
                    'coach_session_id' => $session->csid,
                    'date' => $session->session_date,
                ],
                ['status' => 'ABSENT'],
            );
        }

        $attendance = Attendance::query()
            ->with('athlete.user:id,name')
            ->where('coach_session_id', $session->csid)
            ->whereDate('date', $session->session_date)
            ->orderBy('athlete_id')
            ->get();

        if (Schema::hasTable('session_coach_attendance')) {
            $assignedCoachIds = collect([$session->coach_id])
                ->filter()
                ->when($this->hasCoachPivotTable(), fn ($collection) => $collection->merge($session->coaches->pluck('coach_id')))
                ->unique()
                ->values();

            foreach ($assignedCoachIds as $coachId) {
                SessionCoachAttendance::query()->firstOrCreate(
                    ['coach_session_id' => $session->csid, 'coach_id' => $coachId],
                    ['status' => 'TEACH']
                );
            }
        }

        $coachAttendance = Schema::hasTable('session_coach_attendance')
            ? SessionCoachAttendance::query()
                ->with('coach.user:id,name')
                ->where('coach_session_id', $session->csid)
                ->orderBy('scaid')
                ->get()
            : collect();

        $athletePresentCount = $attendance->where('status', 'PRESENT')->count();
        $coachTeachCount = $coachAttendance->where('status', 'TEACH')->count();

        return Inertia::render('SessionsAttendancePage', [
            'session' => [
                'id' => $session->csid,
                'title' => $session->title,
                'date' => $this->formatIsoDate($session->session_date),
                'branch' => $session->branch?->branch_name ?? 'Unassigned',
                'group' => $session->group?->group_name ?? 'All groups',
                'coach' => $this->coachNames($session),
                'athlete_attendance_summary' => $athletePresentCount.' / '.$attendance->count(),
                'coach_attendance_summary' => $coachTeachCount.' / '.$coachAttendance->count(),
            ],
            'rows' => $attendance->map(fn (Attendance $row) => [
                'id' => 'ATT-'.$row->atid,
                'athlete' => $row->athlete?->user?->name ?? 'Unknown athlete',
                'status' => $this->attendanceBadge((string) $row->status),
            ])->values(),
            'coachRows' => $coachAttendance->map(fn (SessionCoachAttendance $row) => [
                'id' => 'SCA-'.$row->scaid,
                'coach' => $row->coach?->user?->name ?? 'Unknown coach',
                'status' => $row->status === 'TEACH' ? $this->badge('Teach', 'success') : $this->badge('Not teach', 'danger'),
                'checked_at' => $row->checked_at ? Carbon::parse((string) $row->checked_at)->format('d/m/Y H:i') : '-',
            ])->values(),
            'coachOptions' => Coach::query()
                ->with('user:id,name')
                ->orderBy('coach_id')
                ->get()
                ->map(fn (Coach $coach) => ['value' => $coach->coach_id, 'label' => $coach->user?->name ?? 'Unknown coach'])
                ->values(),
        ]);
    }

    public function addCoachAttendance(Session $session, Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isCoach(), 403);

        if (! Schema::hasTable('session_coach_attendance')) {
            return back()->withErrors(['coach_id' => 'Coach attendance table not ready. Run migrations first.']);
        }

        $validated = $request->validate([
            'coach_id' => ['required', 'exists:coaches,coach_id'],
        ]);

        SessionCoachAttendance::query()->updateOrCreate(
            ['coach_session_id' => $session->csid, 'coach_id' => $validated['coach_id']],
            ['status' => 'TEACH', 'checked_at' => now()]
        );

        if ($this->hasCoachPivotTable()) {
            $session->coaches()->syncWithoutDetaching([$validated['coach_id']]);
        }

        return back();
    }

    public function updateCoachAttendance(Request $request, SessionCoachAttendance $coachAttendance): RedirectResponse
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

    public function destroyCoachAttendance(Request $request, SessionCoachAttendance $coachAttendance): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isCoach(), 403);
        $coachAttendance->delete();

        return back();
    }

    private function sessionBadge(string $status): array
    {
        return match ($status) {
            'CONFIRMED' => $this->badge('Confirmed', 'success'),
            'NEEDS_ASSISTANT' => $this->badge('Needs assistant', 'warning'),
            'CANCELED' => $this->badge('Canceled', 'danger'),
            default => $this->badge('Draft', 'info'),
        };
    }

    private function attendanceBadge(string $status): array
    {
        return match ($status) {
            'PRESENT' => $this->badge('Present', 'success'),
            'EXCUSED' => $this->badge('Excused', 'info'),
            default => $this->badge('Absent', 'danger'),
        };
    }

    private function coachNames(Session $session): string
    {
        $names = collect();
        if ($session->coach?->user?->name) {
            $names->push($session->coach->user->name);
        }

        $assistantNames = $this->hasCoachPivotTable()
            ? $session->coaches
            ->map(fn (Coach $coach) => $coach->user?->name)
            ->filter()
            ->values()
            : collect();

        return $names
            ->concat($assistantNames)
            ->unique()
            ->join(', ') ?: 'Unassigned';
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

    private function coachCanAccessSession(int $userId, Session $session): bool
    {
        $coachId = Coach::query()->where('id', $userId)->value('coach_id');
        if (! $coachId) {
            return false;
        }

        return $session->coach_id === (int) $coachId
            || ($this->hasCoachPivotTable() && $session->coaches()->where('coaches.coach_id', $coachId)->exists());
    }

    private function hasCoachPivotTable(): bool
    {
        return Schema::hasTable('coach_session_coaches');
    }

    private function resolveSessionCoachId(?int $userId, ?int $fallbackCoachId): ?int
    {
        if ($userId) {
            $coachId = Coach::query()->where('id', $userId)->value('coach_id');
            if ($coachId) {
                return (int) $coachId;
            }
        }

        if ($fallbackCoachId) {
            return (int) $fallbackCoachId;
        }

        $firstCoachId = Coach::query()->orderBy('coach_id')->value('coach_id');

        return $firstCoachId ? (int) $firstCoachId : null;
    }
}

