<?php

namespace App\Actions\Attendance;

use App\Models\TrainingSession;
use App\Services\AttendanceQrTokenService;
use Illuminate\Support\Facades\DB;

class GenerateSessionAttendanceQr
{
    public function __construct(private readonly AttendanceQrTokenService $tokens) {}

    public function handle(TrainingSession $session, array $validated = []): array
    {
        $token = $this->tokens->generateToken();

        $session = DB::transaction(function () use ($session, $token): TrainingSession {
            $session->update([
                'attendance_token_hash' => $this->tokens->hashToken($token),
                'attendance_opens_at' => now(),
                'attendance_closes_at' => null,
                'attendance_qr_generated_at' => now(),
                'attendance_qr_revoked_at' => null,
            ]);

            return $session->refresh();
        });

        return [$session, $token];
    }
}
