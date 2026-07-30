<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\GenerateSessionAttendanceQr;
use App\Actions\Attendance\InitializeSessionAttendance;
use App\Actions\Attendance\RevokeSessionAttendanceQr;
use App\Http\Requests\Attendance\GenerateSessionAttendanceQrRequest;
use App\Models\TrainingSession;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;

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

        [$session, $token] = $this->generateQr->handle($session);

        ActivityLogger::log(
            $request,
            'attendance_qr.opened',
            'attendance',
            'Opened session attendance QR until manually closed',
            $session,
            ['session_id' => $session->training_session_id],
        );

        return back()
            ->with('attendanceQr', [
                'token' => $token,
                'scan_url' => $this->scanUrl($token),
                'opens_at' => $session->attendance_opens_at?->toIso8601String(),
                'closes_at' => null,
                'generated_at' => $session->attendance_qr_generated_at?->toIso8601String(),
            ])
            ->with('attendanceQrStatus', 'QR attendance dibuka dan akan tetap aktif sampai ditutup oleh admin atau pelatih.');
    }

    public function destroy(TrainingSession $session): RedirectResponse
    {
        $this->authorize('manageAttendanceQr', $session);

        $session = $this->revokeQr->handle($session);

        ActivityLogger::log(
            request(),
            'attendance_qr.closed',
            'attendance',
            'Closed session attendance QR manually',
            $session,
            ['session_id' => $session->training_session_id],
        );

        return back()->with('attendanceQrStatus', 'QR attendance telah ditutup. Kode sebelumnya tidak dapat digunakan lagi.');
    }

    private function scanUrl(string $token): string
    {
        $relativeUrl = route('attendance.scan.show', $token, false);
        $host = request()->getHost();

        if (app()->environment('local') || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return url($relativeUrl);
        }

        return secure_url($relativeUrl);
    }
}
