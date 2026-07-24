<?php

namespace App\Actions\Sessions;

use App\Models\CoachAttendance;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\SessionVisibilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSession
{
    public function __construct(private readonly SessionVisibilityService $sessionVisibility) {}

    public function handle(User $user, TrainingSession $session, array $validated): TrainingSession
    {
        return DB::transaction(function () use ($user, $session, $validated): TrainingSession {
            $lockedSession = TrainingSession::query()
                ->lockForUpdate()
                ->findOrFail($session->training_session_id);

            abort_unless(
                $user->isAdmin() || $this->sessionVisibility->coachCanAccessSession($user, $lockedSession),
                403,
            );

            $this->assertHistoricalIdentityIsUnchanged($lockedSession, $validated);
            $validated['coach_id'] = $lockedSession->coach_id
                ?: $this->sessionVisibility->coachProfileIdFor($user);

            if (blank($validated['coach_id'])) {
                throw ValidationException::withMessages([
                    'coach_id' => 'Coach is required for attendance session.',
                ]);
            }

            $lockedSession->update($validated);

            if ($this->sessionVisibility->hasCoachPivotTable()) {
                $lockedSession->assignedCoaches()->syncWithoutDetaching([$validated['coach_id']]);
            }

            return $lockedSession->refresh();
        });
    }

    private function assertHistoricalIdentityIsUnchanged(TrainingSession $session, array $validated): void
    {
        $hasHistory = $session->attendances()->exists()
            || CoachAttendance::query()
                ->where('training_session_id', $session->training_session_id)
                ->exists();

        if (! $hasHistory) {
            return;
        }

        $comparisons = [
            'branch_id' => [
                (string) $session->branch_id,
                (string) ($validated['branch_id'] ?? $session->branch_id),
            ],
            'group_id' => [
                (string) ($session->group_id ?? ''),
                (string) ($validated['group_id'] ?? ''),
            ],
            'session_date' => [
                optional($session->session_date)->toDateString(),
                Carbon::parse($validated['session_date'] ?? $session->session_date)->toDateString(),
            ],
            'start_time' => [
                $this->clockValue($session->start_time),
                $this->clockValue($validated['start_time'] ?? $session->start_time),
            ],
            'end_time' => [
                $this->clockValue($session->end_time),
                $this->clockValue($validated['end_time'] ?? $session->end_time),
            ],
        ];

        $changedFields = collect($comparisons)
            ->filter(fn (array $values): bool => $values[0] !== $values[1])
            ->keys()
            ->values();

        if ($changedFields->isNotEmpty()) {
            throw ValidationException::withMessages([
                'session' => 'Branch, class, date, and time cannot be changed after attendance history exists. Create a new session instead.',
                ...$changedFields->mapWithKeys(fn (string $field): array => [
                    $field => 'This field is locked because attendance history already exists.',
                ])->all(),
            ]);
        }
    }

    private function clockValue(mixed $value): string
    {
        return Carbon::parse((string) $value)->format('H:i:s');
    }
}
