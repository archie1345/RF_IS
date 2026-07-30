<?php

namespace App\Actions\Attendance;

use App\Models\Attendance;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\AttendanceVisibilityService;
use App\Services\ParentChildContextService;
use App\Support\Domain\AttendanceStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAttendanceRecord
{
    public function __construct(
        private readonly ParentChildContextService $childContext,
        private readonly AttendanceVisibilityService $attendanceVisibility,
    ) {}

    public function handle(User $user, Request $request, array $validated): Attendance
    {
        $parentChildAthleteIds = $user->isParent()
            ? $this->childContext->visibleChildAthleteIds($request, false)
            : null;
        $athleteScopedId = $user->isAthlete() ? $user->athleteProfile?->athlete_id : null;
        $athleteId = $athleteScopedId ?? ($validated['athlete_id'] ?? null);

        if ($athleteId === null) {
            throw ValidationException::withMessages(['athlete_id' => 'Athlete is required.']);
        }

        if ($parentChildAthleteIds !== null && ! in_array((string) $athleteId, array_map('strval', $parentChildAthleteIds), true)) {
            throw ValidationException::withMessages(['athlete_id' => 'Selected athlete is not linked to this parent account.']);
        }

        if ($user->isParent() && ! empty($validated['training_session_id'])) {
            $sessionIsVisible = $this->attendanceVisibility
                ->visibleSessionQuery($user)
                ->where('training_session_id', $validated['training_session_id'])
                ->exists();

            if (! $sessionIsVisible) {
                throw ValidationException::withMessages(['training_session_id' => 'Selected session is not available for this child.']);
            }
        }

        if ($user->isCoach() && ! empty($validated['training_session_id'])) {
            $session = TrainingSession::query()->find($validated['training_session_id']);
            if (! $session || ! $this->attendanceVisibility->coachCanAccessSession($user, $session)) {
                abort(403);
            }
        }

        $checkedInAt = null;
        if (! empty($validated['checked_in_time'])) {
            $checkedInAt = Carbon::createFromFormat('Y-m-d H:i', $validated['date'].' '.$validated['checked_in_time']);
        }

        return DB::transaction(function () use ($athleteId, $validated, $checkedInAt): Attendance {
            $attendance = Attendance::withTrashed()
                ->where('athlete_id', $athleteId)
                ->where('training_session_id', $validated['training_session_id'])
                ->lockForUpdate()
                ->first();

            if (! $attendance) {
                $attendance = new Attendance([
                    'athlete_id' => $athleteId,
                    'training_session_id' => $validated['training_session_id'],
                ]);
            }

            if ($attendance->trashed()) {
                $attendance->restore();
            }

            $attendance->fill([
                'date' => $validated['date'],
                'status' => $validated['status'],
                'checked_in_at' => $checkedInAt ?? ($validated['status'] === AttendanceStatus::PRESENT ? now() : null),
                'notes' => $validated['notes'] ?? null,
                'follow_up_owner' => $validated['follow_up_owner'] ?? null,
            ])->save();

            return $attendance->refresh();
        });
    }
}
