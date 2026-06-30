<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\GenerateSessionAttendanceQr;
use App\Actions\Attendance\RevokeSessionAttendanceQr;
use App\Http\Requests\Attendance\GenerateSessionAttendanceQrRequest;
use App\Models\Session;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;

class SessionAttendanceQrController extends Controller
{
    public function __construct(
        private readonly GenerateSessionAttendanceQr $generateQr,
        private readonly RevokeSessionAttendanceQr $revokeQr,
    ) {}

    public function store(GenerateSessionAttendanceQrRequest $request, Session $session): RedirectResponse
    {
        [$session, $token] = $this->generateQr->handle($session, $request->validated());

        ActivityLogger::log(
            $request,
            'attendance_qr.generated',
            'attendance',
            'Generated session attendance QR code',
            $session,
            ['session_id' => $session->csid],
        );

        return back()->with('attendanceQr', [
            'token' => $token,
            'scan_url' => route('attendance.scan.show', $token),
            'opens_at' => $session->attendance_opens_at?->toIso8601String(),
            'closes_at' => $session->attendance_closes_at?->toIso8601String(),
            'generated_at' => $session->attendance_qr_generated_at?->toIso8601String(),
        ]);
    }

    public function destroy(Session $session): RedirectResponse
    {
        $this->authorize('manageAttendanceQr', $session);

        $session = $this->revokeQr->handle($session);

        ActivityLogger::log(
            request(),
            'attendance_qr.revoked',
            'attendance',
            'Revoked session attendance QR code',
            $session,
            ['session_id' => $session->csid],
        );

        return back()->with('attendanceQrStatus', 'Attendance QR code closed.');
    }
}
