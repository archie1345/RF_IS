<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\GenerateSessionAttendanceQr;
use App\Actions\Attendance\RevokeSessionAttendanceQr;
use App\Http\Requests\Attendance\GenerateSessionAttendanceQrRequest;
use App\Models\TrainingSession;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use App\Actions\Attendance\InitializeSessionAttendance;

class SessionAttendanceQrController extends Controller
{
    public function __construct(
        private readonly GenerateSessionAttendanceQr $generateQr,
        private readonly RevokeSessionAttendanceQr $revokeQr,
        private readonly InitializeSessionAttendance $initializeAttendance,
    ) {}

    public function store(GenerateSessionAttendanceQrRequest $request, TrainingSession $session): RedirectResponse
    {
        $this->initializeAttendance->handle($session);

        [$session, $token] = $this->generateQr->handle($session, $request->validated());

        ActivityLogger::log(
            $request,
            'attendance_qr.generated',
            'attendance',
            'Generated session attendance QR code',
            $session,
            ['session_id' => $session->training_session_id],
        );

        return back()->with('attendanceQr', [
            'token' => $token,
            'scan_url' => route('attendance.scan.show', $token),
            'opens_at' => $session->attendance_opens_at?->toIso8601String(),
            'closes_at' => $session->attendance_closes_at?->toIso8601String(),
            'generated_at' => $session->attendance_qr_generated_at?->toIso8601String(),
        ]);
    }

    public function destroy(TrainingSession $session): RedirectResponse
    {
        $this->authorize('manageAttendanceQr', $session);

        $session = $this->revokeQr->handle($session);

        ActivityLogger::log(
            request(),
            'attendance_qr.revoked',
            'attendance',
            'Revoked session attendance QR code',
            $session,
            ['session_id' => $session->training_session_id],
        );

        return back()->with('attendanceQrStatus', 'Attendance QR code closed.');
    }
}
