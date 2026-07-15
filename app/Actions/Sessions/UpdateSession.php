<?php

namespace App\Actions\Sessions;

use App\Models\TrainingSession;
use App\Models\User;
use App\Services\SessionVisibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSession
{
    public function __construct(private readonly SessionVisibilityService $sessionVisibility) {}

    public function handle(User $user, TrainingSession $session, array $validated): TrainingSession
    {
        $validated['coach_id'] = $this->sessionVisibility->resolveSessionCoachId($user, $session->coach_id);

        if (empty($validated['coach_id'])) {
            throw ValidationException::withMessages(['coach_id' => 'Coach is required for attendance session.']);
        }

        return DB::transaction(function () use ($session, $validated): TrainingSession {
            $session->update($validated);

            if ($this->sessionVisibility->hasCoachPivotTable()) {
                $session->assignedCoaches()->syncWithoutDetaching([$validated['coach_id']]);
            }

            return $session->refresh();
        });
    }
}
