<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\Session;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceManagementController extends Controller
{
    use FormatsMvpData;

    public function index(): Response
    {
        $request = request();
        $user = $request->user();
        $parentScopedAthleteIds = null;
        $athleteScopedId = null;
        $coachScopedSessionIds = null;
        $role = $user?->role ?? 'athlete';

        if ($user && $user->isParent()) {
            $children = $user->children()->pluck('athletes.athlete_id');
            $activeChildId = request()->session()->get('active_child_id');

            $parentScopedAthleteIds = $activeChildId
                ? $children->where(fn ($id) => (int) $id === (int) $activeChildId)
                : $children;
        }

        if ($user && $user->isAthlete()) {
            $athleteScopedId = $user->athleteProfile?->athlete_id;
        }

        if ($user && $user->isCoach()) {
            $coachId = Coach::query()->where('id', $user->id)->value('coach_id');
            if ($coachId) {
                $sessionQuery = Session::query()->where('coach_id', $coachId);
                if (Schema::hasTable('coach_session_coaches')) {
                    $sessionQuery->orWhereHas('coaches', fn ($query) => $query->where('coaches.coach_id', $coachId));
                }
                $coachScopedSessionIds = $sessionQuery->pluck('csid');
            }
        }

        $attendanceQuery = Attendance::query()
            ->with(['athlete.user:id,name', 'session.coach.user:id,name'])
            ->latest('date')
            ->latest('atid');

        if ($parentScopedAthleteIds !== null) {
            $attendanceQuery->whereIn('athlete_id', $parentScopedAthleteIds);
        }
        if ($athleteScopedId !== null) {
            $attendanceQuery->where('athlete_id', $athleteScopedId);
        }
        if ($coachScopedSessionIds !== null) {
            $attendanceQuery->whereIn('coach_session_id', $coachScopedSessionIds);
        }

        $attendance = $attendanceQuery->get();

        $todayRecords = $attendance->where('date', now()->toDateString());
        $presentToday = $todayRecords->where('status', 'PRESENT')->count();
        $attendanceRate = $todayRecords->count() > 0 ? (int) round(($presentToday / $todayRecords->count()) * 100) : 0;

        return Inertia::render('AttendancePage', [
            'metrics' => [
                ['label' => 'Attendance today', 'value' => $attendanceRate.'%', 'detail' => $presentToday.' present records logged today', 'tone' => 'success'],
                ['label' => 'Absent records this week', 'value' => (string) $attendance->whereBetween('date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->where('status', 'ABSENT')->count(), 'detail' => 'Weekly absences that may need follow-up', 'tone' => 'warning'],
                ['label' => 'Sessions tracked', 'value' => (string) $attendance->pluck('coach_session_id')->filter()->unique()->count(), 'detail' => 'Distinct sessions referenced in the log', 'tone' => 'info'],
            ],
            'rows' => $attendance->map(fn (Attendance $record) => [
                'id' => 'ATT-'.$record->atid,
                'athlete' => $record->athlete?->user?->name ?? 'Unknown athlete',
                'session' => $record->session?->title ?? 'General attendance',
                'session_href' => $record->session ? route('sessions.attendance', $record->session->csid) : '',
                'is_locked' => $this->isAttendanceLocked($record),
                'coach' => $record->session?->coach?->user?->name ?? 'Unassigned',
                'checkin' => $this->formatTimeHm($record->checked_in_at) ?? '-',
                'status' => $this->attendanceBadge((string) $record->status),
            ])->values(),
            'athletes' => Athlete::query()
                ->with('user:id,name')
                ->when($parentScopedAthleteIds !== null, fn ($query) => $query->whereIn('athlete_id', $parentScopedAthleteIds))
                ->when($athleteScopedId !== null, fn ($query) => $query->where('athlete_id', $athleteScopedId))
                ->get()
                ->map(fn (Athlete $athlete) => ['value' => $athlete->athlete_id, 'label' => $athlete->user?->name ?? 'Unknown athlete'])
                ->sortBy('label')
                ->values(),
            'sessions' => Session::query()
                ->with(['coach.user:id,name', 'branch:branch_id,branch_name'])
                ->orderBy('session_date')
                ->get()
                ->map(fn (Session $session) => [
                    'value' => $session->csid,
                    'title' => $session->title,
                    'label' => $session->title.' - '.($session->branch?->branch_name ?? 'No branch'),
                    'href' => route('sessions.attendance', $session->csid),
                    'date' => $this->formatDateYmd($session->session_date),
                ])
                ->values(),
            'branches' => ($role === 'admin' || $role === 'coach')
                ? Branch::query()->orderBy('branch_name')->get(['branch_id as value', 'branch_name as label'])
                : [],
            'groups' => ($role === 'admin' || $role === 'coach')
                ? Group::query()->orderBy('group_name')->get(['group_id as value', 'group_name as label'])
                : [],
            'coaches' => ($role === 'admin')
                ? Coach::query()
                    ->with('user:id,name')
                    ->get()
                    ->map(fn (Coach $coach) => ['value' => $coach->coach_id, 'label' => $coach->user?->name ?? 'Unknown coach'])
                    ->sortBy('label')
                    ->values()
                : [],
            'role' => $role,
            'activeAthleteId' => $athleteScopedId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $parentChildAthleteIds = $user && $user->isParent()
            ? $user->children()->pluck('athletes.athlete_id')->all()
            : null;
        $athleteScopedId = $user && $user->isAthlete()
            ? $user->athleteProfile?->athlete_id
            : null;

        $validated = $request->validate([
            'athlete_id' => ['nullable', 'exists:athletes,athlete_id'],
            'coach_session_id' => ['nullable', 'exists:coach_sessions,csid'],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(['PRESENT', 'ABSENT', 'EXCUSED'])],
            'checked_in_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string'],
            'follow_up_owner' => ['nullable', 'string', 'max:120'],
        ]);

        $athleteId = $athleteScopedId
            ?? ($validated['athlete_id'] ?? null);

        if ($athleteId === null) {
            return back()->withErrors(['athlete_id' => 'Athlete is required.']);
        }

        if ($parentChildAthleteIds !== null && ! in_array((int) $athleteId, array_map('intval', $parentChildAthleteIds), true)) {
            return back()->withErrors(['athlete_id' => 'Selected athlete is not linked to this parent account.']);
        }

        $checkedInAt = null;

        if (! empty($validated['checked_in_time'])) {
            $checkedInAt = Carbon::createFromFormat('Y-m-d H:i', $validated['date'].' '.$validated['checked_in_time']);
        }

        $attendance = Attendance::create([
            'athlete_id' => $athleteId,
            'coach_session_id' => $validated['coach_session_id'] ?? null,
            'date' => $validated['date'],
            'status' => $validated['status'],
            'checked_in_at' => $checkedInAt,
            'notes' => $validated['notes'] ?? null,
            'follow_up_owner' => $validated['follow_up_owner'] ?? null,
        ]);

        ActivityLogger::log(
            $request,
            'attendance.created',
            'attendance',
            'Created attendance entry',
            $attendance,
            ['athlete_id' => $attendance->athlete_id, 'status' => $attendance->status],
        );

        return redirect()->route('attendance.index');
    }

    public function update(Request $request, Attendance $attendance): RedirectResponse
    {
        if ($this->isAttendanceLocked($attendance->loadMissing('session'))) {
            return back()->withErrors([
                'status' => 'Attendance cannot be changed because the session time has passed.',
            ]);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['PRESENT', 'ABSENT', 'EXCUSED'])],
        ]);

        $updates = ['status' => $validated['status']];
        $updates['checked_in_at'] = now();

        $attendance->update($updates);

        ActivityLogger::log(
            $request,
            'attendance.updated',
            'attendance',
            'Updated attendance status',
            $attendance,
            ['status' => $attendance->status],
        );

        return redirect()->route('attendance.index');
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'attendance_ids' => ['required', 'array', 'min:1'],
            'attendance_ids.*' => ['required', 'integer', 'exists:athlete_attendance,atid'],
            'status' => ['required', Rule::in(['PRESENT', 'ABSENT', 'EXCUSED'])],
        ]);

        Attendance::query()
            ->whereIn('atid', $validated['attendance_ids'])
            ->update(['status' => $validated['status']]);

        ActivityLogger::log(
            $request,
            'attendance.bulk_updated',
            'attendance',
            'Bulk updated attendance status',
            null,
            ['count' => count($validated['attendance_ids']), 'status' => $validated['status']],
        );

        return back();
    }

    private function attendanceBadge(string $status): array
    {
        return match ($status) {
            'PRESENT' => $this->badge('Present', 'success'),
            'EXCUSED' => $this->badge('Excused', 'info'),
            default => $this->badge('Absent', 'danger'),
        };
    }

    private function isAttendanceLocked(Attendance $attendance): bool
    {
        if ($attendance->session && $attendance->session->session_date && $attendance->session->end_time) {
            $deadline = Carbon::parse(
                $this->formatDateYmd($attendance->session->session_date).' '.substr((string) $attendance->session->end_time, 0, 5)
            );

            return now()->greaterThan($deadline);
        }

        $date = $this->formatDateYmd($attendance->date);
        if (! $date) {
            return false;
        }

        return now()->greaterThan(Carbon::parse($date.' 23:59'));
    }

    private function formatDateYmd(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatTimeHm(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('d/m/Y H:i');
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return null;
        }
    }
}

