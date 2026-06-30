<?php

namespace App\Actions\Attendance;

use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class UpdateAttendanceStatus
{
    public function handle(Attendance $attendance, string $status): Attendance
    {
        return DB::transaction(function () use ($attendance, $status): Attendance {
            $attendance->update([
                'status' => $status,
                'checked_in_at' => now(),
            ]);

            return $attendance->refresh();
        });
    }
}
