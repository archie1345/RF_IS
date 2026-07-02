<?php

namespace App\Actions\Attendance;

use App\Models\TrainingSession;
use App\Services\AttendanceQrTokenService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateSessionAttendanceQr
{
    public function __construct(private readonly AttendanceQrTokenService $tokens) {}

    public function handle(TrainingSession $session, array $validated): array
    {
        $token = $this->tokens->generateToken();
        $opensAt = Carbon::parse($validated['attendance_opens_at'] ?? now());
        $closesAt = Carbon::parse($validated['attendance_closes_at'] ?? $this->defaultClosesAt($session));

        $session = DB::transaction(function () use ($session, $token, $opensAt, $closesAt): TrainingSession {
            $session->update([
                'attendance_token_hash' => $this->tokens->hashToken($token),
                'attendance_opens_at' => $opensAt,
                'attendance_closes_at' => $closesAt,
                'attendance_qr_generated_at' => now(),
                'attendance_qr_revoked_at' => null,
            ]);

            return $session->refresh();
        });

        return [$session, $token];
    }

    private function defaultClosesAt(TrainingSession $session): Carbon
    {
        return Carbon::parse($session->session_date.' '.substr((string) $session->end_time, 0, 8));
    }
}
