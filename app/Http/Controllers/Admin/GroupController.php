<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\WeeklyTrainingSchedule;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validatedGroup($request);
        $group = Group::create($this->payload($validated));
        $this->syncWeeklySchedule($group->refresh());

        ActivityLogger::log($request, 'admin.group.created', 'admin', 'Created group', $group, ['group_name' => $group->group_name]);

        return back()->with('status', 'Class saved and linked to weekly schedule.');
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validatedGroup($request);
        $group->update($this->payload($validated, $group));
        $this->syncWeeklySchedule($group->refresh());

        ActivityLogger::log($request, 'admin.group.updated', 'admin', 'Updated group', $group, ['group_name' => $group->group_name]);

        return back()->with('status', 'Class updated and weekly schedule synced.');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $hasAthletes = $group->athletes()->exists();
        $hasSessions = TrainingSession::query()->where('group_id', $group->group_id)->exists();
        $schedule = WeeklyTrainingSchedule::query()->where('group_id', $group->group_id)->first();

        if ($hasAthletes || $hasSessions) {
            $group->update(['is_active' => false]);
            $schedule?->update(['is_active' => false]);

            return back()->with('status', 'Class has linked athletes or sessions, so it was deactivated instead of deleted.');
        }

        $schedule?->delete();
        ActivityLogger::log($request, 'admin.group.deleted', 'admin', 'Deleted group', $group, ['group_name' => $group->group_name]);
        $group->delete();

        return back()->with('status', 'Class deleted.');
    }

    private function validatedGroup(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'class_type' => ['required', 'string', 'max:50'],
            'coach_id' => ['nullable', 'exists:coaches,coach_id'],
            'branch_id' => ['nullable', 'exists:branches,branch_id'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'min_belt' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);
    }

    private function payload(array $validated, ?Group $existing = null): array
    {
        return [
            'group_name' => $validated['name'],
            'class_type' => $validated['class_type'],
            'coach_id' => $validated['coach_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'day_of_week' => $validated['day_of_week'],
            'capacity' => $validated['capacity'] ?? $existing?->capacity ?? 0,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'min_belt' => $validated['min_belt'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }

    private function syncWeeklySchedule(Group $group): void
    {
        $existing = WeeklyTrainingSchedule::query()->where('group_id', $group->group_id)->first();
        $isSchedulable = (bool) ($group->is_active && $group->branch_id && $group->day_of_week && $group->start_time && $group->end_time);

        if (! $isSchedulable) {
            $existing?->update(['is_active' => false]);
            return;
        }

        WeeklyTrainingSchedule::query()->updateOrCreate(
            ['group_id' => $group->group_id],
            [
                'title' => $group->group_name,
                'branch_id' => $group->branch_id,
                'group_id' => $group->group_id,
                'coach_id' => $group->coach_id,
                'day_of_week' => $group->day_of_week,
                'start_time' => $group->start_time,
                'end_time' => $group->end_time,
                'location' => $group->branch?->location ?? $group->branch?->branch_name,
                'is_active' => true,
            ],
        );
    }
}
