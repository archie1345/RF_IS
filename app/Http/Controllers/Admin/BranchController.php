<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\TrainingSession;
use App\Models\WeeklyTrainingSchedule;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validatedBranch($request);
        $branch = Branch::create($this->payload($validated));

        ActivityLogger::log($request, 'admin.branch.created', 'admin', 'Created branch', $branch, ['branch_name' => $branch->branch_name]);

        return back()->with('status', 'Location saved.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validatedBranch($request);
        $branch->update($this->payload($validated));

        ActivityLogger::log($request, 'admin.branch.updated', 'admin', 'Updated branch', $branch, ['branch_name' => $branch->branch_name]);

        return back()->with('status', 'Location updated.');
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $hasGroups = $branch->groups()->exists();
        $hasSchedules = WeeklyTrainingSchedule::query()->where('branch_id', $branch->branch_id)->exists();
        $hasSessions = TrainingSession::query()->where('branch_id', $branch->branch_id)->exists();

        if ($hasGroups || $hasSchedules || $hasSessions) {
            $branch->update(['is_active' => false]);

            return back()->with('status', 'Location has linked classes, schedules, or sessions, so it was deactivated instead of deleted.');
        }

        ActivityLogger::log($request, 'admin.branch.deleted', 'admin', 'Deleted branch', $branch, ['branch_name' => $branch->branch_name]);
        $branch->delete();

        return back()->with('status', 'Location deleted.');
    }

    private function validatedBranch(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'attendance_radius_meters' => ['required', 'integer', 'min:10', 'max:5000'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ]);
    }

    private function payload(array $validated): array
    {
        return [
            'branch_name' => $validated['name'],
            'location' => $validated['location'] ?? $validated['address'],
            'address' => $validated['address'],
            'city' => $validated['city'],
            'province' => $validated['province'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'attendance_radius_meters' => $validated['attendance_radius_meters'],
            'timezone' => $validated['timezone'] ?? 'Asia/Jakarta',
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }
}
