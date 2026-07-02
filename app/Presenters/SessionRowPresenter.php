<?php

namespace App\Presenters;

use App\Models\TrainingSession;
use App\Presenters\Concerns\FormatsPresenterData;
use App\Services\SessionVisibilityService;
use App\Support\Domain\SessionStatus;
use Illuminate\Support\Carbon;

class SessionRowPresenter
{
    use FormatsPresenterData;

    public function __construct(private readonly SessionVisibilityService $sessionVisibility) {}

    public function row(TrainingSession $session, ?string $currentCoachId = null): array
    {
        return [
            'id' => 'SES-'.$session->training_session_id,
            'session_id' => $session->training_session_id,
            'session' => $session->title,
            'branch' => $session->branch?->branch_name ?? 'Unassigned',
            'group' => $session->group?->group_name ?? 'All groups',
            'coach' => $this->coachNames($session),
            'schedule' => $this->formatDateYmd($session->session_date).' '.$this->formatTime24($session->start_time).' - '.$this->formatTime24($session->end_time),
            'status' => $this->sessionBadge((string) $session->status),
            'location' => $session->location,
            'session_date' => $this->formatIsoDate($session->session_date),
            'start_time' => $this->formatTime24($session->start_time),
            'end_time' => $this->formatTime24($session->end_time),
            'branch_id' => $session->branch_id,
            'group_id' => $session->group_id,
            'coach_id' => $session->coach_id,
            'status_value' => $session->status,
            'can_join' => $this->sessionVisibility->coachCanJoinSession($currentCoachId, $session),
        ];
    }

    public function sessionBadge(string $status): array
    {
        return $this->badge(SessionStatus::label($status), SessionStatus::tone($status));
    }

    private function coachNames(TrainingSession $session): string
    {
        $names = collect([$session->primaryCoach?->user?->name]);

        if ($session->relationLoaded('assignedCoaches')) {
            $names = $names->merge($session->assignedCoaches->map(fn ($coach) => $coach->user?->name));
        }

        return $names->filter()->unique()->values()->implode(', ') ?: 'Unassigned';
    }

    private function formatDateYmd(mixed $value): string
    {
        return Carbon::parse((string) $value)->format('Y-m-d');
    }

    private function formatIsoDate(mixed $value): string
    {
        return Carbon::parse((string) $value)->format('Y-m-d');
    }

    private function formatTime24(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return Carbon::parse((string) $value)->format('H:i');
    }
}
