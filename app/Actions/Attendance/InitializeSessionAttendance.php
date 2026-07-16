<?php

namespace App\Actions\Attendance;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\TrainingSession;
use App\Support\Domain\AttendanceStatus;
use App\Support\Domain\BeltRank;
use Illuminate\Support\Facades\DB;

class InitializeSessionAttendance
{
    public function handle(TrainingSession $session): int
    {
        $session->loadMissing('group');

        $athleteIds = Athlete::query()
            ->where('branch_id', $session->branch_id)
            ->when(
                $session->dedicated_athlete_id !== null,
                fn ($query) => $query->where('athlete_id', $session->dedicated_athlete_id),
            )
            ->get(['athlete_id', 'group_id', 'geup'])
            ->filter(fn (Athlete $athlete) => $this->athleteCanJoinSession($athlete, $session))
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

    private function athleteCanJoinSession(Athlete $athlete, TrainingSession $session): bool
    {
        if ($session->dedicated_athlete_id !== null) {
            return (string) $athlete->athlete_id === (string) $session->dedicated_athlete_id;
        }

        if ($session->group_id === null) {
            return true;
        }

        if ((string) $athlete->group_id === (string) $session->group_id) {
            return true;
        }

        return BeltRank::eligible($athlete->geup, $session->group?->min_belt);
    }
}
