<?php

namespace App\Actions\Attendance;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Session;
use App\Models\User;
use App\Presenters\AttendanceRowPresenter;
use App\Support\Domain\AttendanceStatus;
use App\Support\Domain\SessionStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordQrAttendance
{
    public function __construct(private readonly AttendanceRowPresenter $attendanceRows) {}

    public function handle(User $user, Session $session): array
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
            $attendance = Attendance::query()
                ->where('athlete_id', $athlete->athlete_id)
                ->where('coach_session_id', $session->csid)
                ->lockForUpdate()
                ->first();

            if ($attendance && $attendance->status === AttendanceStatus::PRESENT) {
                return [$attendance->refresh(), true];
            }

            if ($attendance && $this->attendanceRows->isLocked($attendance->loadMissing('session'))) {
                throw ValidationException::withMessages([
                    'attendance' => 'Attendance cannot be changed because the session time has passed.',
                ]);
            }

            $attendance = Attendance::query()->updateOrCreate(
                [
                    'athlete_id' => $athlete->athlete_id,
                    'coach_session_id' => $session->csid,
                ],
                [
                    'date' => $session->session_date,
                    'status' => AttendanceStatus::PRESENT,
                    'checked_in_at' => now(),
                ],
            );

            return [$attendance->refresh(), false];
        });
    }

    private function validateSession(Session $session): void
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

    private function validateEligibility(Athlete $athlete, Session $session): void
    {
        if ((int) $athlete->branch_id !== (int) $session->branch_id) {
            throw ValidationException::withMessages(['attendance' => 'You are not eligible for this session branch.']);
        }

        if ($session->group_id !== null && (int) $athlete->group_id !== (int) $session->group_id) {
            throw ValidationException::withMessages(['attendance' => 'You are not eligible for this session group.']);
        }
    }
}
