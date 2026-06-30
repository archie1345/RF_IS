<?php

namespace App\Services;

use App\Models\Session;
use Illuminate\Support\Str;

class AttendanceQrTokenService
{
    public function generateToken(): string
    {
        return Str::random(96);
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function findActiveSessionByToken(string $token): ?Session
    {
        return Session::query()
            ->with(['branch:branch_id,branch_name', 'group:group_id,group_name', 'coach.user:id,name'])
            ->where('attendance_token_hash', $this->hashToken($token))
            ->whereNull('attendance_qr_revoked_at')
            ->first();
    }
}
