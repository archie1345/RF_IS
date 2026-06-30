<?php

namespace App\Actions\Sessions;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Session;
use App\Models\User;
use App\Services\SessionVisibilityService;
use App\Support\Domain\AttendanceStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSession
{
    public function __construct(private readonly SessionVisibilityService $sessionVisibility)
    {
    }

    public function handle(User $user, array $validated): array
    {
        $validated['coach_id'] = $this->sessionVisibility->resolveSessionCoachId($user, null);

        if (empty($validated['coach_id'])) {
            throw ValidationException::withMessages(['coach_id' => 'Coach is required for attendance session.']);
        }

        return DB::transaction(function () use ($validated): array {
            $session = Session::query()->create($validated);

            if ($this->sessionVisibility->hasCoachPivotTable()) {
                $session->coaches()->syncWithoutDetaching([$validated['coach_id']]);
            }

            $athleteIds = Athlete::query()
                ->where('branch_id', $session->branch_id)
                ->when($session->group_id, fn ($query) => $query->where('group_id', $session->group_id))
                ->pluck('athlete_id');

            foreach ($athleteIds as $athleteId) {
                Attendance::query()->create([
                    'athlete_id' => $athleteId,
                    'coach_session_id' => $session->csid,
                    'date' => $session->session_date,
                    'status' => AttendanceStatus::ABSENT,
                ]);
            }

            return [$session, $athleteIds->count()];
        });
    }
}
