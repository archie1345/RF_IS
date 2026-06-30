<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Http\Requests\Attendance\BulkUpdateAttendanceRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Models\Coach;
use App\Models\Group;
use App\Models\Session;
use App\Support\ActivityLogger;
use App\Services\ParentChildContextService;
use App\Services\AttendanceVisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceManagementController extends Controller
{
    use FormatsMvpData;

    public function __construct(
        private readonly ParentChildContextService $childContext,
        private readonly AttendanceVisibilityService $attendanceVisibility,
    ) {
    }

    public function index(): Response
    {
        $request = request();
        $user = $request->user();
        $role = $user?->primaryRole() ?? 'athlete';
        $parentScopedAthleteIds = $user?->isParent() ? collect($this->childContext->visibleChildAthleteIds($request)) : null;
        $athleteScopedId = $user?->isAthlete() ? $user->athleteProfile?->athlete_id : null;

        $attendanceQuery = $this->attendanceVisibility->scopedAttendanceQuery($request)
            ->with(['athlete.user:id,name', 'session.coach.user:id,name'])
            ->latest('date')
            ->latest('atid');

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
            'rows' => $attendance->map(function (Attendance $record) use ($user) {
                $isLocked = $this->isAttendanceLocked($record);

                return [
                    'id' => 'ATT-'.$record->atid,
                    'athlete_id' => $record->athlete_id,
                    'date' => $this->formatDateYmd($record->date),
                    'athlete' => $record->athlete?->user?->name ?? 'Unknown athlete',
                    'session' => $record->session?->title ?? 'General attendance',
                    'session_href' => $record->session ? route('sessions.attendance', $record->session->csid) : '',
                    'is_locked' => $isLocked,
                    'can_update' => ! $isLocked && $this->attendanceVisibility->userCanUpdate($user, $record),
                    'coach' => $record->session?->coach?->user?->name ?? 'Unassigned',
                    'checkin' => $this->formatTimeHm($record->checked_in_at) ?? '-',
                    'status' => $this->attendanceBadge((string) $record->status),
                ];
            })->values(),
            'athletes' => Athlete::query()
                ->with('user:id,name')
                ->when($parentScopedAthleteIds !== null, fn ($query) => $query->whereIn('athlete_id', $parentScopedAthleteIds))
                ->when($athleteScopedId !== null, fn ($query) => $query->where('athlete_id', $athleteScopedId))
                ->get()
                ->map(fn (Athlete $athlete) => ['value' => $athlete->athlete_id, 'label' => $athlete->user?->name ?? 'Unknown athlete'])
                ->sortBy('label')
                ->values(),
            'sessions' => $this->attendanceVisibility->visibleSessionQuery($user)
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

    public function store(StoreAttendanceRequest $request): RedirectResponse
    {
        $user = $request->user();
        $parentChildAthleteIds = $user && $user->isParent()
            ? $this->childContext->visibleChildAthleteIds($request, false)
            : null;
        $athleteScopedId = $user && $user->isAthlete()
            ? $user->athleteProfile?->athlete_id
            : null;

        $validated = $request->validated();

        $athleteId = $athleteScopedId
            ?? ($validated['athlete_id'] ?? null);

        if ($athleteId === null) {
            return back()->withErrors(['athlete_id' => 'Athlete is required.']);
        }

        if ($parentChildAthleteIds !== null && ! in_array((string) $athleteId, array_map('strval', $parentChildAthleteIds), true)) {
            return back()->withErrors(['athlete_id' => 'Selected athlete is not linked to this parent account.']);
        }

        if ($user?->isCoach() && ! empty($validated['coach_session_id'])) {
            $session = Session::query()->find($validated['coach_session_id']);
            if (! $session || ! $this->attendanceVisibility->coachCanAccessSession($user, $session)) {
                abort(403);
            }
        }

        $checkedInAt = null;

        if (! empty($validated['checked_in_time'])) {
            $checkedInAt = Carbon::createFromFormat('Y-m-d H:i', $validated['date'].' '.$validated['checked_in_time']);
        }

        $attendance = Attendance::query()->updateOrCreate(
            [
                'athlete_id' => $athleteId,
                'coach_session_id' => $validated['coach_session_id'] ?? null,
                'date' => $validated['date'],
            ],
            [
                'status' => $validated['status'],
                'checked_in_at' => $checkedInAt ?? ($validated['status'] === 'PRESENT' ? now() : null),
                'notes' => $validated['notes'] ?? null,
                'follow_up_owner' => $validated['follow_up_owner'] ?? null,
            ],
        );

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

    public function update(UpdateAttendanceRequest $request, Attendance $attendance): RedirectResponse
    {
        $attendance->loadMissing(['athlete', 'session']);

        abort_unless($this->attendanceVisibility->userCanUpdate($request->user(), $attendance), 403);

        if ($this->isAttendanceLocked($attendance)) {
            return back()->withErrors([
                'status' => 'Attendance cannot be changed because the session time has passed.',
            ]);
        }

        $validated = $request->validated();

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

    public function bulkUpdate(BulkUpdateAttendanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $attendanceRows = Attendance::query()
            ->with(['athlete', 'session'])
            ->whereIn('atid', $validated['attendance_ids'])
            ->get();

        abort_unless(
            $attendanceRows->every(fn (Attendance $attendance) => $this->attendanceVisibility->userCanUpdate($request->user(), $attendance) && ! $this->isAttendanceLocked($attendance)),
            403,
        );

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

