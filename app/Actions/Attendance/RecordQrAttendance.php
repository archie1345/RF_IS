<?php

namespace App\Actions\Attendance;

use App\Models\Attendance;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\SessionEligibilityService;
use App\Support\Domain\AttendanceStatus;
use App\Support\Domain\SessionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordQrAttendance
{
    public function __construct(private readonly SessionEligibilityService $eligibility) {}

    public function handle(User $user, TrainingSession $session): array
    {
        $athlete = $user->isAthlete() ? $user->athleteProfile : null;

        if (! $athlete) {
            throw ValidationException::withMessages([
                'attendance' => 'Only athlete accounts can record QR attendance.',
            ]);
        }

        $session->refresh();
        $this->validateSession($session);

        $denialReason = $this->eligibility->denialReason($athlete, $session);
        if ($denialReason !== null) {
            throw ValidationException::withMessages(['attendance' => $denialReason]);
        }

        return DB::transaction(function () use ($athlete, $session): array {
            $attendance = Attendance::withTrashed()
                ->where('athlete_id', $athlete->athlete_id)
                ->where('training_session_id', $session->training_session_id)
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                $attendance = Attendance::withTrashed()
                    ->where('athlete_id', $athlete->athlete_id)
                    ->whereNull('training_session_id')
                    ->whereDate('date', $session->session_date)
                    ->lockForUpdate()
                    ->first();
            }

            $alreadyRecorded = $attendance?->status === AttendanceStatus::PRESENT
                && (int) $attendance?->training_session_id === (int) $session->training_session_id;

            if ($attendance && in_array($attendance->status, [AttendanceStatus::EXCUSED, AttendanceStatus::LATE], true)) {
                throw ValidationException::withMessages([
                    'attendance' => 'Attendance has already been corrected by an authorized staff member.',
                ]);
            }

            if (! $attendance) {
                $attendance = new Attendance;
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
}
