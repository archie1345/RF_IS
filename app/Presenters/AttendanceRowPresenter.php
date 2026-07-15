<?php

namespace App\Presenters;

use App\Models\Attendance;
use App\Presenters\Concerns\FormatsPresenterData;
use App\Services\AttendanceVisibilityService;
use App\Support\Domain\AttendanceStatus;
use Illuminate\Support\Carbon;

class AttendanceRowPresenter
{
    use FormatsPresenterData;

    public function __construct(private readonly AttendanceVisibilityService $attendanceVisibility) {}

    public function row(Attendance $record, mixed $user): array
    {
        $isLocked = $this->isLocked($record);

        return [
            'id' => 'ATT-'.$record->athlete_attendance_id,
            'athlete_id' => $record->athlete_id,
            'date' => $this->formatDateYmd($record->date),
            'status_value' => $record->status,
            'athlete' => $record->athlete?->user?->name ?? 'Unknown athlete',
            'session' => $record->trainingSession?->title ?? 'General attendance',
            'session_href' => $record->trainingSession ? route('sessions.attendance', $record->trainingSession->training_session_id) : '',
            'is_locked' => $isLocked,
            'can_update' => ! $isLocked && $this->attendanceVisibility->userCanUpdate($user, $record),
            'coach' => $record->trainingSession?->primaryCoach?->user?->name ?? 'Unassigned',
            'checkin' => $this->formatTimeHm($record->checked_in_at) ?? '-',
            'status' => $this->attendanceBadge((string) $record->status),
        ];
    }

    public function attendanceBadge(string $status): array
    {
        return $this->badge(AttendanceStatus::label($status), AttendanceStatus::tone($status));
    }

    public function isLocked(Attendance $attendance): bool
    {
        if ($attendance->trainingSession && $attendance->trainingSession->session_date && $attendance->trainingSession->end_time) {
            $deadline = Carbon::parse(
                $this->formatDateYmd($attendance->trainingSession->session_date).' '.substr((string) $attendance->trainingSession->end_time, 0, 5)
            );

            return now()->greaterThan($deadline);
        }

        $date = $this->formatDateYmd($attendance->date);
        if (! $date) {
            return false;
        }

        return now()->greaterThan(Carbon::parse($date.' 23:59'));
    }

    public function formatDateYmd(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatTimeHm(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }
}
