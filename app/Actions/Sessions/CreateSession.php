<?php

namespace App\Actions\Sessions;

use App\Actions\Attendance\InitializeSessionAttendance;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\SessionVisibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSession
{
    public function __construct(
        private readonly SessionVisibilityService $sessionVisibility,
        private readonly InitializeSessionAttendance $initializeAttendance,
    ) {}

    public function handle(User $user, array $validated): array
    {
        $validated['coach_id'] = $this->sessionVisibility->resolveSessionCoachId($user, null);

        if (empty($validated['coach_id'])) {
            throw ValidationException::withMessages(['coach_id' => 'Coach is required for attendance session.']);
        }

        return DB::transaction(function () use ($validated): array {
            $session = TrainingSession::query()->create($validated);

            if ($this->sessionVisibility->hasCoachPivotTable()) {
                $session->assignedCoaches()->syncWithoutDetaching([$validated['coach_id']]);
            }

            return [$session, $this->initializeAttendance->handle($session)];
        });
    }
}
