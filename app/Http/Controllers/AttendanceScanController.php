<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\RecordQrAttendance;
use App\Http\Requests\Attendance\RecordQrAttendanceRequest;
use App\Models\Attendance;
use App\Models\TrainingSession;
use App\Services\AttendanceQrTokenService;
use App\Support\ActivityLogger;
use App\Support\Domain\AttendanceStatus;
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
    ) {}

    public function show(Request $request, string $token): Response
    {
        $session = $this->tokens->findActiveSessionByToken($token);
        $user = $request->user();
        $athlete = $user?->athleteProfile;
        $attendance = $session && $athlete
            ? Attendance::query()
                ->where('athlete_id', $athlete->athlete_id)
                ->where('training_session_id', $session->training_session_id)
                ->first()
            : null;

        return Inertia::render('AttendanceScanPage', [
            'token' => $token,
            'session' => $session ? $this->sessionPayload($session) : null,
            'athlete' => $athlete ? [
                'athlete_id' => $athlete->athlete_id,
                'name' => $athlete->user?->name ?? $user?->name ?? 'Athlete',
            ] : null,
            'currentStatus' => $attendance?->status,
            'canSubmit' => $session !== null && $athlete !== null,
            'message' => $session ? null : 'This QR attendance code is invalid or has been closed.',
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

    private function sessionPayload(TrainingSession $session): array
    {
        return [
            'id' => $session->training_session_id,
            'title' => $session->title,
            'date' => Carbon::parse((string) $session->session_date)->format('Y-m-d'),
            'start_time' => Carbon::parse((string) $session->start_time)->format('H:i'),
            'end_time' => Carbon::parse((string) $session->end_time)->format('H:i'),
            'branch' => $session->branch?->branch_name ?? 'Unassigned',
            'group' => $session->group?->group_name ?? 'All groups',
            'status' => $session->status,
            'attendance_status' => AttendanceStatus::PRESENT,
            'attendance_opens_at' => $session->attendance_opens_at?->toIso8601String(),
            'attendance_closes_at' => $session->attendance_closes_at?->toIso8601String(),
        ];
    }
}
