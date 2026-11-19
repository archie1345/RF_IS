<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\RecordQrAttendance;
use App\Http\Requests\Attendance\RecordQrAttendanceRequest;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\TrainingSession;
use App\Services\AttendanceQrTokenService;
use App\Services\SessionEligibilityService;
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
        private readonly SessionEligibilityService $eligibility,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $session = $this->tokens->findActiveSessionByToken($token);
        $user = $request->user();
        $athlete = $user?->isAthlete() ? $user->athleteProfile : null;
        $attendance = $this->findAttendance($session, $athlete);
        [$state, $message] = $this->scanState($session, $athlete, $attendance);

        return Inertia::render('AttendanceScanPage', [
            'token' => $token,
            'state' => $state,
            'message' => $message,
            'session' => $session ? $this->sessionPayload($session) : null,
            'athlete' => $athlete ? [
                'athlete_id' => $athlete->athlete_id,
                'name' => $athlete->user?->name ?? $user?->name ?? 'Athlete',
                'current_status' => $attendance?->status,
            ] : null,
            'currentStatus' => $attendance?->status,
            'canSubmit' => $state === 'ready',
            'scanResult' => null,
        ]);
    }

    public function store(RecordQrAttendanceRequest $request, string $token): RedirectResponse
    {
        $session = $this->tokens->findActiveSessionByToken($token);
        abort_unless($session instanceof TrainingSession, 404);

        [$attendance, $alreadyRecorded] = $this->recordQrAttendance->handle($request->user(), $session);

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

    private function scanState(?TrainingSession $session, ?Athlete $athlete, ?Attendance $attendance): array
    {
        if (! $session) {
            return ['invalid', 'This QR attendance code is invalid or has been closed.'];
        }

        $session->loadMissing('group.trainingGroup', 'group.privateAthletes');

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
            return ['athlete_required', 'Please log in using an athlete account before checking in.'];
        }

        if (! $this->eligibility->isEligible($athlete, $session)) {
            return ['not_eligible', 'You are not eligible for this session.'];
        }

        if ($attendance?->status === AttendanceStatus::PRESENT) {
            return ['already_present', 'You are already checked in for this session.'];
        }

        return ['ready', 'Attendance is being saved from this QR.'];
    }
}
