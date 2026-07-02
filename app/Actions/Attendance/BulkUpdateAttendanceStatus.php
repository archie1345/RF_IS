<?php

namespace App\Actions\Attendance;

use App\Models\Attendance;
use Illuminate\Support\Facades\DB;

class BulkUpdateAttendanceStatus
{
    public function handle(array $attendanceIds, string $status): void
    {
        DB::transaction(function () use ($attendanceIds, $status): void {
            Attendance::query()
                ->whereIn('athlete_attendance_id', $attendanceIds)
                ->update(['status' => $status]);
        });
    }
}
