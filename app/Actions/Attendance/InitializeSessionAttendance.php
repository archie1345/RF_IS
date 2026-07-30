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
        $session->loadMissing('group.trainingGroup', 'group.privateAthletes');

        $athleteIds = Athlete::query()
            ->where('branch_id', $session->branch_id)
            ->with(['group.trainingGroup', 'trainingGroup'])
            ->when(
                $session->dedicated_athlete_id !== null && ($session->group?->class_type ?? null) !== 'private',
                fn ($query) => $query->where('athlete_id', $session->dedicated_athlete_id),
            )
            ->get(['athlete_id', 'group_id', 'training_group_id', 'geup'])
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
        if (($session->group?->class_type ?? null) === 'private') {
            return $session->group
                ->privateAthletes
                ->pluck('athlete_id')
                ->map(fn ($id) => (string) $id)
                ->contains((string) $athlete->athlete_id);
        }

        if ($session->dedicated_athlete_id !== null) {
            return (string) $athlete->athlete_id === (string) $session->dedicated_athlete_id;
        }

        if ($session->group_id === null) {
            return true;
        }

        $requiredTrainingGroupId = $session->group?->training_group_id;
        if ($requiredTrainingGroupId !== null) {
            $athleteTrainingGroupId = $athlete->training_group_id ?? $athlete->group?->training_group_id;

            return (string) $athleteTrainingGroupId === (string) $requiredTrainingGroupId;
        }

        if ((string) $athlete->group_id === (string) $session->group_id) {
            return true;
        }

        $minimumBelt = $session->group?->min_belt;

        return filled($minimumBelt) && BeltRank::eligible($athlete->geup, $minimumBelt);
    }
}
