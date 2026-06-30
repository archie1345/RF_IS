<?php

namespace App\Actions\Attendance;

use App\Models\Session;
use Illuminate\Support\Facades\DB;

class RevokeSessionAttendanceQr
{
    public function handle(Session $session): Session
    {
        return DB::transaction(function () use ($session): Session {
            $session->update([
                'attendance_token_hash' => null,
                'attendance_qr_revoked_at' => now(),
            ]);

            return $session->refresh();
        });
    }
}
