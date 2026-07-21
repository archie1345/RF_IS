<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\BulkUpdateAttendanceStatus;
use App\Actions\Attendance\CreateAttendanceRecord;
use App\Actions\Attendance\UpdateAttendanceStatus;
use App\Http\Controllers\Concerns\FormatsPresentationData;
use App\Http\Requests\Attendance\BulkUpdateAttendanceRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Coach;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Presenters\AttendanceRowPresenter;
use App\Services\AttendanceVisibilityService;
use App\Services\ParentChildContextService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    use FormatsPresentationData;

    public function __construct(
        private readonly ParentChildContextService $childContext,
        private readonly AttendanceVisibilityService $attendanceVisibility,
        private readonly AttendanceRowPresenter $attendanceRows,
        private readonly CreateAttendanceRecord $createAttendance,
        private readonly UpdateAttendanceStatus $updateAttendanceStatus,
        private readonly BulkUpdateAttendanceStatus $bulkUpdateAttendanceStatus,
    ) {}

    public function index(): Response
    {
        $request = request();
        $user = $request->user();
        $role = $user?->primaryRole() ?? 'athlete';
        $parentScopedAthleteIds = $user?->isParent() ? collect($this->childContext->visibleChildAthleteIds($request, false)) : null;
        $athleteScopedId = $user?->isAthlete() ? $user->athleteProfile?->athlete_id : null;

        $attendanceQuery = $this->attendanceVisibility->scopedAttendanceQuery($request)
            ->with(['athlete.user:id,name', 'trainingSession.primaryCoach.user:id,name'])
            ->latest('date')
            ->latest('athlete_attendance_id');

        $attendance = $attendanceQuery->get();

        $todayRecords = $attendance->where('date', now()->toDateString());
        $presentToday = $todayRecords->where('status', 'PRESENT')->count();
        $attendanceRate = $todayRecords->count() > 0 ? (int) round(($presentToday / $todayRecords->count()) * 100) : 0;

        return Inertia::render('AttendancePage', [
            'metrics' => [
                ['label' => 'Attendance today', 'value' => $attendanceRate.'%', 'detail' => $presentToday.' present records logged today', 'tone' => 'success'],
                ['label' => 'Absent records this week', 'value' => (string) $attendance->whereBetween('date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->where('status', 'ABSENT')->count(), 'detail' => 'Weekly absences that may need follow-up', 'tone' => 'warning'],
                ['label' => 'Sessions tracked', 'value' => (string) $attendance->pluck('training_session_id')->filter()->unique()->count(), 'detail' => 'Distinct sessions referenced in the log', 'tone' => 'info'],
            ],
            'rows' => $attendance->map(fn (Attendance $record) => $this->attendanceRows->row($record, $user))->values(),
            'athletes' => Athlete::query()
                ->with('user:id,name')
                ->when($parentScopedAthleteIds !== null, fn ($query) => $query->whereIn('athlete_id', $parentScopedAthleteIds))
                ->when($athleteScopedId !== null, fn ($query) => $query->where('athlete_id', $athleteScopedId))
                ->get()
                ->map(fn (Athlete $athlete) => ['value' => $athlete->athlete_id, 'label' => $athlete->user?->name ?? 'Unknown athlete'])
                ->sortBy('label')
                ->values(),
            'sessions' => $this->attendanceVisibility->visibleSessionQuery($user)
                ->with(['primaryCoach.user:id,name', 'branch:branch_id,branch_name'])
                ->orderBy('session_date')
                ->get()
                ->map(fn (TrainingSession $session) => [
                    'value' => $session->training_session_id,
                    'title' => $session->title,
                    'label' => $session->title.' - '.($session->branch?->branch_name ?? 'No branch'),
                    'href' => route('sessions.attendance', $session->training_session_id),
                    'date' => $this->attendanceRows->formatDateYmd($session->session_date),
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
        $attendance = $this->createAttendance->handle($request->user(), $request, $request->validated());

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

    public function update(UpdateAttendanceRequest $request, Attendance $attendance): RedirectResponse|JsonResponse
    {
        $attendance->loadMissing(['athlete', 'trainingSession']);

        $this->authorize('update', $attendance);

        $status = $request->validated()['status'];
        $user = $request->user();
        $canCorrectPastAttendance = $user?->isAdmin() || $user?->isCoach();

        if ((string) $attendance->status === $status) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => $attendance->status,
                    'row' => $this->attendanceRows->row($attendance, $request->user()),
                ]);
            }

            return redirect()->route('attendance.index');
        }

        if (! $canCorrectPastAttendance && $this->attendanceRows->isLocked($attendance)) {
            $message = 'Attendance cannot be changed because the session time has passed.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => ['status' => [$message]],
                ], 422);
            }

            return back()->withErrors(['status' => $message]);
        }

        $attendance = $this->updateAttendanceStatus->handle($attendance, $status);

        ActivityLogger::log(
            $request,
            'attendance.updated',
            'attendance',
            'Updated attendance status',
            $attendance,
            [
                'status' => $attendance->status,
                'corrected_after_lock' => $this->attendanceRows->isLocked($attendance),
            ],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $attendance->status,
                'row' => $this->attendanceRows->row($attendance, $request->user()),
            ]);
        }

        return redirect()->route('attendance.index');
    }

    public function bulkUpdate(BulkUpdateAttendanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $canCorrectPastAttendance = $user?->isAdmin() || $user?->isCoach();

        $attendanceRows = Attendance::query()
            ->with(['athlete', 'session'])
            ->whereIn('athlete_attendance_id', $validated['attendance_ids'])
            ->get();

        abort_unless(
            $attendanceRows->every(fn (Attendance $attendance) => $this->attendanceVisibility->userCanUpdate($user, $attendance) && ($canCorrectPastAttendance || ! $this->attendanceRows->isLocked($attendance))),
            403,
        );

        $this->bulkUpdateAttendanceStatus->handle($validated['attendance_ids'], $validated['status']);

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
}
