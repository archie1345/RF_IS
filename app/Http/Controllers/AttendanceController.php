<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\BulkUpdateAttendanceStatus;
use App\Actions\Attendance\CreateAttendanceRecord;
use App\Actions\Attendance\UpdateAttendanceStatus;
use App\Http\Controllers\Concerns\FormatsPresentationData;
use App\Http\Requests\Attendance\BulkUpdateAttendanceRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\CoachAttendance;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\User;
use App\Presenters\AttendanceRowPresenter;
use App\Services\ActiveRoleContextService;
use App\Services\AttendanceVisibilityService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    use FormatsPresentationData;

    public function __construct(
        private readonly ActiveRoleContextService $activeRoleContext,
        private readonly AttendanceVisibilityService $attendanceVisibility,
        private readonly AttendanceRowPresenter $attendanceRows,
        private readonly CreateAttendanceRecord $createAttendance,
        private readonly UpdateAttendanceStatus $updateAttendanceStatus,
        private readonly BulkUpdateAttendanceStatus $bulkUpdateAttendanceStatus,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $availableModes = $this->availableModes($user);
        $mode = $this->selectedMode($request, $user, $availableModes);
        $attendance = collect();
        $coachSessions = collect();

        if ($mode === 'coach' && $user) {
            $coachSessions = $this->coachSessionRows($user);
            $metrics = $this->coachMetrics($coachSessions);
        } else {
            $attendance = $this->attendanceVisibility
                ->scopedAttendanceQuery($request, $mode)
                ->with(['athlete.user:id,name', 'trainingSession.primaryCoach.user:id,name'])
                ->latest('date')
                ->latest('athlete_attendance_id')
                ->get();
            $metrics = $this->athleteAttendanceMetrics($attendance);
        }

        return Inertia::render('AttendancePage', [
            'metrics' => $metrics,
            'rows' => $attendance->map(fn (Attendance $record) => $this->attendanceRows->row($record, $user))->values(),
            'coachSessions' => $coachSessions,
            'athletes' => [],
            'sessions' => [],
            'branches' => $mode === 'admin'
                ? Branch::query()->where('is_active', true)->orderBy('branch_name')->get(['branch_id as value', 'branch_name as label'])
                : [],
            'groups' => $mode === 'admin'
                ? Group::query()->where('is_active', true)->orderBy('group_name')->get(['group_id as value', 'group_name as label'])
                : [],
            'coaches' => [],
            'role' => $mode,
            'availableModes' => $availableModes,
            'activeAthleteId' => null,
        ]);
    }

    public function attendAsCoach(Request $request, TrainingSession $session): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isCoach(), 403);
        abort_unless($this->attendanceVisibility->coachCanAccessSession($user, $session), 403);

        $coachId = $user->coachProfile?->coach_id;
        abort_unless($coachId, 403, 'Coach profile not found.');

        if (! $this->coachSessionIsOpen($session)) {
            return back()->withErrors([
                'session' => 'Coach attendance is only available for confirmed sessions scheduled today.',
            ]);
        }

        [$attendance, $changed] = DB::transaction(function () use ($session, $coachId): array {
            $attendance = CoachAttendance::withTrashed()
                ->where('training_session_id', $session->training_session_id)
                ->where('coach_id', $coachId)
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                $attendance = new CoachAttendance([
                    'training_session_id' => $session->training_session_id,
                    'coach_id' => $coachId,
                ]);
            } elseif ($attendance->trashed()) {
                $attendance->restore();
                $attendance->refresh();
            }

            $changed = $attendance->status !== 'TEACH' || ! $attendance->checked_at;
            if ($changed) {
                $attendance->fill([
                    'status' => 'TEACH',
                    'checked_at' => now(),
                ])->save();
            }

            return [$attendance->refresh(), $changed];
        });

        if ($changed) {
            ActivityLogger::log(
                $request,
                'coach_attendance.checked_in',
                'coach_attendance',
                'Coach checked in to assigned session',
                $attendance,
                [
                    'coach_id' => $coachId,
                    'training_session_id' => $session->training_session_id,
                ],
            );
        }

        return redirect()->route('attendance.index', ['mode' => 'coach']);
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
            ->with(['athlete', 'trainingSession'])
            ->whereIn('athlete_attendance_id', $validated['attendance_ids'])
            ->get();

        abort_unless($attendanceRows->count() === count(array_unique($validated['attendance_ids'])), 404);
        abort_unless(
            $attendanceRows->every(fn (Attendance $attendance) => $this->attendanceVisibility->userCanUpdate($user, $attendance)
                && ($canCorrectPastAttendance || ! $this->attendanceRows->isLocked($attendance))),
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

    private function availableModes(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return collect(['admin', 'coach', 'parent', 'athlete'])
            ->filter(fn (string $role): bool => $user->hasRole($role))
            ->values()
            ->all();
    }

    private function selectedMode(Request $request, ?User $user, array $availableModes): string
    {
        $activeRole = $this->activeRoleContext->activeRole($request, $user);

        return in_array($activeRole, $availableModes, true)
            ? $activeRole
            : ($availableModes[0] ?? 'athlete');
    }

    private function athleteAttendanceMetrics(Collection $attendance): array
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        $todayRecords = $attendance->filter(
            fn (Attendance $record): bool => $record->date?->isToday() === true,
        );
        $presentToday = $todayRecords->where('status', 'PRESENT')->count();
        $attendanceRate = $todayRecords->count() > 0
            ? (int) round(($presentToday / $todayRecords->count()) * 100)
            : 0;
        $weeklyAbsences = $attendance->filter(
            fn (Attendance $record): bool => $record->status === 'ABSENT'
                && $record->date?->betweenIncluded($weekStart, $weekEnd) === true,
        )->count();

        return [
            ['label' => 'Attendance today', 'value' => $attendanceRate.'%', 'detail' => $presentToday.' present records logged today', 'tone' => 'success'],
            ['label' => 'Absent records this week', 'value' => (string) $weeklyAbsences, 'detail' => 'Weekly absences that may need follow-up', 'tone' => 'warning'],
            ['label' => 'Sessions tracked', 'value' => (string) $attendance->pluck('training_session_id')->filter()->unique()->count(), 'detail' => 'Distinct sessions referenced in the log', 'tone' => 'info'],
        ];
    }

    private function coachMetrics(Collection $sessions): array
    {
        $currentMonth = now()->format('Y-m');

        return [
            ['label' => 'Assigned sessions', 'value' => (string) $sessions->count(), 'detail' => 'Sessions where you are the primary or assigned coach', 'tone' => 'info'],
            ['label' => 'Available today', 'value' => (string) $sessions->where('can_attend', true)->count(), 'detail' => 'Confirmed sessions accepting coach attendance today', 'tone' => 'warning'],
            ['label' => 'Attended this month', 'value' => (string) $sessions->filter(fn (array $row): bool => $row['has_attended'] && str_starts_with($row['session_date_iso'], $currentMonth))->count(), 'detail' => 'Sessions checked in as teaching this month', 'tone' => 'success'],
        ];
    }

    private function coachSessionRows(User $user): Collection
    {
        $coachId = $user->coachProfile?->coach_id;
        if (! $coachId) {
            return collect();
        }

        $sessions = $this->attendanceVisibility
            ->visibleSessionQuery($user, 'coach')
            ->with(['branch:branch_id,branch_name', 'group:group_id,group_name'])
            ->whereDate('session_date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->limit(120)
            ->get();
        $attendanceBySession = CoachAttendance::query()
            ->where('coach_id', $coachId)
            ->whereIn('training_session_id', $sessions->pluck('training_session_id'))
            ->get()
            ->keyBy(fn (CoachAttendance $attendance) => (string) $attendance->training_session_id);

        return $sessions
            ->map(function (TrainingSession $session) use ($attendanceBySession): array {
                $attendance = $attendanceBySession->get((string) $session->training_session_id);
                $hasAttended = $attendance?->status === 'TEACH' && $attendance?->checked_at !== null;
                $canAttend = ! $hasAttended && $this->coachSessionIsOpen($session);

                return [
                    'id' => 'SES-'.$session->training_session_id,
                    'session_id' => $session->training_session_id,
                    'session' => $session->title,
                    'branch' => $session->branch?->branch_name ?? 'No branch',
                    'group' => $session->group?->group_name ?? 'All groups',
                    'schedule' => $this->sessionScheduleLabel($session),
                    'session_date_iso' => optional($session->session_date)->toDateString() ?? '',
                    'session_status' => $this->sessionStatusBadge((string) $session->status),
                    'attendance_status' => $this->coachAttendanceBadge($session, $attendance, $canAttend, $hasAttended),
                    'checked_at' => $attendance?->checked_at?->format('d/m/Y H:i') ?? '-',
                    'can_attend' => $canAttend,
                    'has_attended' => $hasAttended,
                ];
            })
            ->values();
    }

    private function coachSessionIsOpen(TrainingSession $session): bool
    {
        return in_array((string) $session->status, ['CONFIRMED', 'NEEDS_ASSISTANT'], true)
            && $session->session_date?->isToday() === true;
    }

    private function coachAttendanceBadge(
        TrainingSession $session,
        ?CoachAttendance $attendance,
        bool $canAttend,
        bool $hasAttended,
    ): array {
        if ($hasAttended) {
            return $this->badge('Attended', 'success');
        }

        if ($attendance?->status === 'NOT_TEACH' && $attendance?->checked_at) {
            return $this->badge('Not teaching', 'danger');
        }

        if ($canAttend) {
            return $this->badge('Available today', 'info');
        }

        if ((string) $session->status === 'CANCELED') {
            return $this->badge('Canceled', 'danger');
        }

        if ($session->session_date?->isFuture()) {
            return $this->badge('Upcoming', 'neutral');
        }

        return $this->badge('Closed', 'neutral');
    }

    private function sessionStatusBadge(string $status): array
    {
        return match ($status) {
            'CONFIRMED' => $this->badge('Confirmed', 'success'),
            'NEEDS_ASSISTANT' => $this->badge('Needs assistant', 'warning'),
            'CANCELED' => $this->badge('Canceled', 'danger'),
            default => $this->badge('Draft', 'neutral'),
        };
    }

    private function sessionScheduleLabel(TrainingSession $session): string
    {
        $date = optional($session->session_date)->format('d/m/Y') ?? '-';
        $start = $session->start_time ? Carbon::parse((string) $session->start_time)->format('H:i') : '--:--';
        $end = $session->end_time ? Carbon::parse((string) $session->end_time)->format('H:i') : '--:--';

        return $date.' · '.$start.' - '.$end;
    }
}
