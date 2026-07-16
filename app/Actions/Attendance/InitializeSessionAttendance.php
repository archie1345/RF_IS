<?php

namespace App\Actions\Attendance;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\TrainingSession;
use App\Support\Domain\AttendanceStatus;
use Illuminate\Support\Facades\DB;

class InitializeSessionAttendance
{
    public function handle(TrainingSession $session): int
    {
        $athleteIds = Athlete::query()
            ->where('branch_id', $session->branch_id)
            ->when(
                $session->dedicated_athlete_id !== null,
                fn ($query) => $query->where('athlete_id', $session->dedicated_athlete_id),
                fn ($query) => $query->when(
                    $session->group_id !== null,
                    fn ($query) => $query->where('group_id', $session->group_id),
                ),
            )
            ->pluck('athlete_id');

        return DB::transaction(function () use ($session, $athleteIds): int {
            $created = 0;

            foreach ($athleteIds as $athleteId) {
                $attendance = Attendance::withTrashed()->firstOrCreate(
                    [
                        'athlete_id' => $athleteId,
                        'training_session_id' => $session->training_session_id,
                    ],
                    [
                        'date' => $session->session_date,
                        'status' => AttendanceStatus::ABSENT,
                        'checked_in_at' => null,
                    ],
                );

                if ($attendance->trashed()) {
                    $attendance->restore();
                }

                if ($attendance->wasRecentlyCreated) {
                    $created++;
                }
            }

            return $created;
        });
    }
}
