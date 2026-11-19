<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\TrainingSession;
use App\Support\Domain\BeltRank;

/**
 * Single definition of "may this athlete attend this session?".
 *
 * Used by the QR scan page (to show the right state) and by the QR write path
 * (to enforce it), so the two can never disagree.
 */
class SessionEligibilityService
{
    /** @return string|null human-readable reason the athlete is not eligible, or null when eligible */
    public function denialReason(Athlete $athlete, TrainingSession $session): ?string
    {
        $session->loadMissing('group.trainingGroup', 'group.privateAthletes');
        $athlete->loadMissing('group.trainingGroup', 'trainingGroup');

        if ((string) $athlete->branch_id !== (string) $session->branch_id) {
            return 'This athlete is not eligible for this session branch.';
        }

        if (($session->group?->class_type ?? null) === 'private') {
            $allowedAthleteIds = $session->group
                ->privateAthletes
                ->pluck('athlete_id')
                ->map(fn ($id) => (string) $id);

            return $allowedAthleteIds->contains((string) $athlete->athlete_id)
                ? null
                : 'This athlete is not assigned to this private session.';
        }

        if ($session->dedicated_athlete_id !== null && (string) $athlete->athlete_id !== (string) $session->dedicated_athlete_id) {
            return 'This athlete is not the assigned athlete for this private session.';
        }

        $requiredTrainingGroupId = $session->group?->training_group_id;
        if ($requiredTrainingGroupId !== null) {
            $athleteTrainingGroupId = $athlete->training_group_id ?? $athlete->group?->training_group_id;

            return (string) $athleteTrainingGroupId === (string) $requiredTrainingGroupId
                ? null
                : 'This athlete is not in the required group category for this session.';
        }

        if ($session->group_id !== null && (string) $athlete->group_id !== (string) $session->group_id) {
            $minimumBelt = $session->group?->min_belt;

            if (blank($minimumBelt) || ! BeltRank::eligible($athlete->geup, $minimumBelt)) {
                return 'This athlete is not eligible for this session group.';
            }
        }

        return null;
    }

    public function isEligible(Athlete $athlete, TrainingSession $session): bool
    {
        return $this->denialReason($athlete, $session) === null;
    }
}
