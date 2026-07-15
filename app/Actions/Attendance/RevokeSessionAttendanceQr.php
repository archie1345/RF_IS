<?php

namespace App\Actions\Attendance;

use App\Models\TrainingSession;
use Illuminate\Support\Facades\DB;

class RevokeSessionAttendanceQr
{
    public function handle(TrainingSession $session): TrainingSession
    {
        return DB::transaction(function () use ($session): TrainingSession {
            $session->update([
                'attendance_token_hash' => null,
                'attendance_qr_revoked_at' => now(),
            ]);

            return $session->refresh();
        });
    }
}
