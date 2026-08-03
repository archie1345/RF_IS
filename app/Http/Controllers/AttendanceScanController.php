<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\RecordQrAttendance;
use App\Http\Requests\Attendance\RecordQrAttendanceRequest;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\TrainingSession;
use App\Services\AttendanceQrTokenService;
use App\Services\ParentChildContextService;
use App\Support\ActivityLogger;
use App\Support\Domain\AttendanceStatus;
use App\Support\Domain\BeltRank;
use App\Support\Domain\SessionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceScanController extends Controller
{
    public function __construct(
        private readonly AttendanceQrTokenService $tokens,
        private readonly RecordQrAttendance $recordQrAttendance,
        private readonly ParentChildContextService $childContext,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $session = $this->tokens->findActiveSessionByToken($token);
        $user = $request->user();
        $children = $user?->isParent() ? $this->childContext->childrenFor($user) : collect();
        $athlete = $this->scanAthleteFor($request, $session);
        $attendance = $this->findAttendance($session, $athlete);
        $deviceAllowed = $this->phoneCheckAllowed($request);
        [$state, $message] = $this->scanState($session, $athlete, $attendance, $deviceAllowed, $user?->isParent() === true, $children->isNotEmpty());
        $scanResult = null;

        return Inertia::render('AttendanceScanPage', [
            'token' => $token,
            'deviceAllowed' => $deviceAllowed,
            'state' => $state,
            'message' => $message,
            'session' => $session ? $this->sessionPayload($session) : null,
            'athlete' => $athlete ? [
                'athlete_id' => $athlete->athlete_id,
                'name' => $athlete->user?->name ?? $user?->name ?? 'Athlete',
                'current_status' => $attendance?->status,
            ] : null,
            'children' => $children
                ->map(fn (Athlete $child) => [
                    'value' => $child->athlete_id,
                    'label' => $child->user?->name ?? 'Unknown athlete',
                    'current_status' => $this->findAttendance($session, $child)?->status,
                ])
                ->values(),
            'currentStatus' => $attendance?->status,
            'canSubmit' => $state === 'ready',
            'scanResult' => $scanResult,
        ]);
    }

    public function store(RecordQrAttendanceRequest $request, string $token): RedirectResponse
    {
        $session = $this->tokens->findActiveSessionByToken($token);
        abort_unless($session instanceof TrainingSession, 404);

        if (! $this->phoneCheckAllowed($request)) {
            return back()->withErrors(['attendance' => 'QR attendance is only available on phones and tablets.']);
        }

        $selectedAthlete = $this->scanAthleteFor($request, $session, true);
        [$attendance, $alreadyRecorded] = $this->recordQrAttendance->handle($request->user(), $session, $selectedAthlete);

        ActivityLogger::log(
            $request,
            $alreadyRecorded ? 'attendance_qr.duplicate' : 'attendance_qr.recorded',
            'attendance',
            $alreadyRecorded ? 'QR attendance was already recorded' : 'Recorded QR attendance check-in',
            $attendance,
            ['session_id' => $session->training_session_id, 'athlete_id' => $attendance->athlete_id],
        );

        return back()->with('attendanceScan', [
            'status' => $alreadyRecorded ? 'already_recorded' : 'recorded',
            'message' => $alreadyRecorded ? 'Attendance was already recorded.' : 'Attendance recorded successfully.',
        ]);
    }

    private function scanAthleteFor(Request $request, ?TrainingSession $session, bool $requireParentSelection = false): ?Athlete
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        if ($user->isAthlete()) {
            return $user->athleteProfile;
        }

        if (! $user->isParent()) {
            return null;
        }

        $children = $this->childContext->childrenFor($user);
        if ($children->isEmpty()) {
            return null;
        }

        $requestedAthleteId = (string) ($request->input('athlete_id') ?: $request->query('athlete_id', ''));
        if ($requestedAthleteId !== '') {
            return $children->first(fn (Athlete $child) => (string) $child->athlete_id === $requestedAthleteId);
        }

        if ($requireParentSelection && $children->count() > 1) {
            return null;
        }

        if ($session) {
            return $children->first(fn (Athlete $child) => $this->athleteEligibleForSession($child, $session)
                && $this->findAttendance($session, $child)?->status !== AttendanceStatus::PRESENT
            ) ?? $children->first(fn (Athlete $child) => $this->athleteEligibleForSession($child, $session)) ?? $children->first();
        }

        return $children->first();
    }

    private function findAttendance(?TrainingSession $session, ?Athlete $athlete): ?Attendance
    {
        if (! $session || ! $athlete) {
            return null;
        }

        $exactAttendance = Attendance::query()
            ->where('athlete_id', $athlete->athlete_id)
            ->where('training_session_id', $session->training_session_id)
            ->first();

        if ($exactAttendance) {
            return $exactAttendance;
        }

        return Attendance::query()
            ->where('athlete_id', $athlete->athlete_id)
            ->whereNull('training_session_id')
            ->whereDate('date', $session->session_date)
            ->first();
    }

    private function sessionPayload(TrainingSession $session): array
    {
        $session->loadMissing('group.trainingGroup', 'group.privateAthletes.user');

        return [
            'id' => $session->training_session_id,
            'title' => $session->title,
            'date' => Carbon::parse((string) $session->session_date)->format('Y-m-d'),
            'time' => Carbon::parse((string) $session->start_time)->format('H:i').' - '.Carbon::parse((string) $session->end_time)->format('H:i'),
            'start_time' => Carbon::parse((string) $session->start_time)->format('H:i'),
            'end_time' => Carbon::parse((string) $session->end_time)->format('H:i'),
            'location' => $session->branch?->location,
            'branch' => $session->branch?->branch_name ?? 'Unassigned',
            'group' => $session->group?->group_name ?? 'All groups',
            'training_group' => $session->group?->trainingGroup?->name,
            'private_athletes' => $session->group && ($session->group->class_type ?? null) === 'private'
                ? $session->group->privateAthletes->map(fn (Athlete $athlete) => $athlete->user?->name)->filter()->values()
                : [],
            'minimum_belt' => BeltRank::label($session->group?->min_belt),
            'status' => $session->status,
            'attendance_status' => AttendanceStatus::PRESENT,
            'opens_at' => $session->attendance_opens_at?->format('Y-m-d H:i'),
            'closes_at' => $session->attendance_closes_at?->format('Y-m-d H:i'),
            'attendance_opens_at' => $session->attendance_opens_at?->toIso8601String(),
            'attendance_closes_at' => $session->attendance_closes_at?->toIso8601String(),
        ];
    }

    private function scanState(?TrainingSession $session, ?Athlete $athlete, ?Attendance $attendance, bool $deviceAllowed, bool $isParent = false, bool $parentHasChildren = false): array
    {
        if (! $session) {
            return ['invalid', 'This QR attendance code is invalid or has been closed.'];
        }

        $session->loadMissing('group.trainingGroup', 'group.privateAthletes');

        if (! $deviceAllowed) {
            return ['desktop_blocked', 'QR attendance is only available on phones and tablets.'];
        }

        if ($session->status === SessionStatus::CANCELED) {
            return ['invalid', 'This session has been canceled.'];
        }

        if ($session->attendance_opens_at && now()->lt($session->attendance_opens_at)) {
            return ['not_open', 'Attendance has not opened for this session yet.'];
        }

        if ($session->attendance_closes_at && now()->gt($session->attendance_closes_at)) {
            return ['closed', 'Attendance is closed for this session.'];
        }

        if (! $athlete) {
            return $isParent && ! $parentHasChildren
                ? ['athlete_required', 'No child athlete is linked to this parent account.']
                : ['athlete_required', $isParent ? 'Select a child before checking in.' : 'Please log in using an athlete account before checking in.'];
        }

        $athlete->loadMissing('group.trainingGroup', 'trainingGroup');

        if (! $this->athleteEligibleForSession($athlete, $session)) {
            return ['not_eligible', $isParent ? 'This child is not eligible for this session.' : 'You are not eligible for this session.'];
        }

        if ($attendance?->status === AttendanceStatus::PRESENT) {
            return ['already_present', $isParent ? 'This child is already checked in for this session.' : 'You are already checked in for this session.'];
        }

        return ['ready', $isParent ? 'Attendance is ready to be saved for the selected child.' : 'Attendance is being saved from this QR.'];
    }

    private function athleteEligibleForSession(Athlete $athlete, TrainingSession $session): bool
    {
        $session->loadMissing('group.trainingGroup', 'group.privateAthletes');
        $athlete->loadMissing('group.trainingGroup', 'trainingGroup');

        if ((string) $athlete->branch_id !== (string) $session->branch_id) {
            return false;
        }

        if (($session->group?->class_type ?? null) === 'private') {
            return $session->group
                ->privateAthletes
                ->pluck('athlete_id')
                ->map(fn ($id) => (string) $id)
                ->contains((string) $athlete->athlete_id);
        }

        if ($session->dedicated_athlete_id !== null && (string) $athlete->athlete_id !== (string) $session->dedicated_athlete_id) {
            return false;
        }

        $requiredTrainingGroupId = $session->group?->training_group_id;
        if ($requiredTrainingGroupId !== null) {
            $athleteTrainingGroupId = $athlete->training_group_id ?? $athlete->group?->training_group_id;

            return (string) $athleteTrainingGroupId === (string) $requiredTrainingGroupId;
        }

        if ($session->group_id !== null && (string) $athlete->group_id !== (string) $session->group_id) {
            return BeltRank::eligible($athlete->geup, $session->group?->min_belt);
        }

        return true;
    }

    private function phoneCheckAllowed(Request $request): bool
    {
        return app()->environment('testing') || $this->isPhoneOrTablet($request);
    }

    private function isPhoneOrTablet(Request $request): bool
    {
        $agent = strtolower((string) $request->userAgent());

        if ($agent === '') {
            return false;
        }

        if (str_contains($agent, 'ipad') || str_contains($agent, 'tablet')) {
            return true;
        }

        if (str_contains($agent, 'android')) {
            return true;
        }

        foreach (['iphone', 'ipod', 'windows phone', 'iemobile', 'blackberry', 'bb10', 'opera mini', 'mobile safari', 'mobile'] as $signal) {
            if (str_contains($agent, $signal)) {
                return true;
            }
        }

        return false;
    }
}
