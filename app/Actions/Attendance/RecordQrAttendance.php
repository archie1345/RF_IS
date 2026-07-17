<?php

namespace App\Actions\Attendance;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\Domain\AttendanceStatus;
use App\Support\Domain\BeltRank;
use App\Support\Domain\SessionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordQrAttendance
{
    public function handle(User $user, TrainingSession $session): array
    {
        $athlete = $user->athleteProfile;

        if (! $athlete) {
            throw ValidationException::withMessages([
                'attendance' => 'Only athlete accounts can record QR attendance.',
            ]);
        }

        $this->validateSession($session);
        $this->validateEligibility($athlete, $session);

        return DB::transaction(function () use ($athlete, $session): array {
            $attendance = Attendance::withTrashed()
                ->where('athlete_id', $athlete->athlete_id)
                ->where('training_session_id', $session->training_session_id)
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                $attendance = Attendance::withTrashed()
                    ->where('athlete_id', $athlete->athlete_id)
                    ->whereDate('date', $session->session_date)
                    ->lockForUpdate()
                    ->first();
            }

            $alreadyRecorded = $attendance?->status === AttendanceStatus::PRESENT
                && (int) $attendance?->training_session_id === (int) $session->training_session_id;

            if (! $attendance) {
                $attendance = new Attendance();
                $attendance->athlete_id = $athlete->athlete_id;
            }

            if ($attendance->trashed()) {
                $attendance->restore();
                $attendance->refresh();
            }

            if (! $alreadyRecorded) {
                $attendance->training_session_id = $session->training_session_id;
                $attendance->date = $session->session_date;
                $attendance->status = AttendanceStatus::PRESENT;
                $attendance->checked_in_at = now();
                $attendance->notes = trim((string) $attendance->notes) !== ''
                    ? $attendance->notes
                    : 'Recorded from QR attendance.';
                $attendance->save();
            }

            return [$attendance->refresh(), $alreadyRecorded];
        });
    }

    private function validateSession(TrainingSession $session): void
    {
        if ($session->status === SessionStatus::CANCELED) {
            throw ValidationException::withMessages(['attendance' => 'This session has been canceled.']);
        }

        if (! $session->attendance_token_hash || $session->attendance_qr_revoked_at !== null) {
            throw ValidationException::withMessages(['attendance' => 'This QR attendance code is no longer active.']);
        }

        if ($session->attendance_opens_at && now()->lt($session->attendance_opens_at)) {
            throw ValidationException::withMessages(['attendance' => 'Attendance has not opened for this session yet.']);
        }

        if ($session->attendance_closes_at && now()->gt($session->attendance_closes_at)) {
            throw ValidationException::withMessages(['attendance' => 'Attendance is closed for this session.']);
        }
    }

    private function validateEligibility(Athlete $athlete, TrainingSession $session): void
    {
        $session->loadMissing('group.trainingGroup');
        $athlete->loadMissing('group.trainingGroup', 'trainingGroup');

        if ((string) $athlete->branch_id !== (string) $session->branch_id) {
            throw ValidationException::withMessages(['attendance' => 'You are not eligible for this session branch.']);
        }

        if ($session->dedicated_athlete_id !== null && (string) $athlete->athlete_id !== (string) $session->dedicated_athlete_id) {
            throw ValidationException::withMessages(['attendance' => 'You are not the assigned athlete for this private session.']);
        }

        $requiredTrainingGroupId = $session->group?->training_group_id;
        if ($requiredTrainingGroupId !== null) {
            $athleteTrainingGroupId = $athlete->training_group_id ?? $athlete->group?->training_group_id;

            if ((string) $athleteTrainingGroupId !== (string) $requiredTrainingGroupId) {
                throw ValidationException::withMessages(['attendance' => 'You are not in the required group category for this session.']);
            }

            return;
        }

        if ($session->group_id !== null
            && (string) $athlete->group_id !== (string) $session->group_id
            && ! BeltRank::eligible($athlete->geup, $session->group?->min_belt)) {
            throw ValidationException::withMessages(['attendance' => 'Your belt level is not eligible for this session.']);
        }
    }
}
