<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\TrainingGroup;
use App\Models\TrainingSession;
use App\Models\WeeklyTrainingSchedule;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TrainingGroupController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $groups = TrainingGroup::query()
            ->withCount(['classes', 'athletes'])
            ->orderBy('name')
            ->get()
            ->map(fn (TrainingGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'is_active' => (bool) $group->is_active,
                'classes_count' => $group->classes_count,
                'athletes_count' => $group->athletes_count,
            ])
            ->values();

        return Inertia::render('AdminGroupsPage', [
            'title' => 'Manajemen Grup',
            'subtitle' => 'Kelola kategori grup atlet. Kelas wajib memilih grup agar presensi hanya diikuti atlet dari kategori yang sama.',
            'groups' => $groups,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validated($request);
        $group = TrainingGroup::query()->create($validated);

        ActivityLogger::log($request, 'admin.training_group.created', 'admin', 'Created training group', $group, [
            'name' => $group->name,
        ]);

        return back()->with('status', 'Grup berhasil dibuat.');
    }

    public function update(Request $request, TrainingGroup $trainingGroup): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validated($request, $trainingGroup);
        $wasActive = (bool) $trainingGroup->is_active;

        $result = DB::transaction(function () use ($trainingGroup, $validated, $wasActive): array {
            $trainingGroup->update($validated);

            return $wasActive && ! $trainingGroup->is_active
                ? $this->deactivateDependants($trainingGroup)
                : $this->emptyDeactivationResult();
        });

        ActivityLogger::log($request, 'admin.training_group.updated', 'admin', 'Updated training group', $trainingGroup, [
            'name' => $trainingGroup->name,
            'is_active' => $trainingGroup->is_active,
            ...$result,
        ]);

        return back()->with(
            'status',
            ! $trainingGroup->is_active && $result['future_sessions_removed'] > 0
                ? "Grup diperbarui dan dinonaktifkan. {$result['classes_deactivated']} kelas, {$result['schedules_deactivated']} jadwal, dan {$result['future_sessions_removed']} sesi mendatang dihentikan."
                : 'Grup berhasil diperbarui.',
        );
    }

    public function destroy(Request $request, TrainingGroup $trainingGroup): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        [$deactivated, $result] = DB::transaction(function () use ($trainingGroup): array {
            $locked = TrainingGroup::query()->lockForUpdate()->findOrFail($trainingGroup->id);
            $hasHistory = $locked->classes()->withTrashed()->exists() || $locked->athletes()->withTrashed()->exists();

            if ($hasHistory) {
                $locked->update(['is_active' => false]);

                return [true, $this->deactivateDependants($locked)];
            }

            $locked->delete();

            return [false, $this->emptyDeactivationResult()];
        });

        ActivityLogger::log(
            $request,
            $deactivated ? 'admin.training_group.deactivated' : 'admin.training_group.deleted',
            'admin',
            $deactivated ? 'Deactivated training group with linked history' : 'Deleted unused training group',
            $trainingGroup,
            ['name' => $trainingGroup->name, ...$result],
        );

        if ($deactivated) {
            return back()->with(
                'status',
                "Grup masih memiliki data terkait, sehingga dinonaktifkan. {$result['classes_deactivated']} kelas, {$result['schedules_deactivated']} jadwal, dan {$result['future_sessions_removed']} sesi mendatang dihentikan; riwayat tetap tersimpan.",
            );
        }

        return back()->with('status', 'Grup berhasil dihapus.');
    }

    private function validated(Request $request, ?TrainingGroup $group = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('training_groups', 'name')->ignore($group?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function deactivateDependants(TrainingGroup $trainingGroup): array
    {
        $groupIds = Group::withTrashed()
            ->where('training_group_id', $trainingGroup->id)
            ->pluck('group_id');

        if ($groupIds->isEmpty()) {
            return $this->emptyDeactivationResult();
        }

        $classesDeactivated = Group::query()
            ->whereIn('group_id', $groupIds)
            ->where('is_active', true)
            ->update(['is_active' => false]);
        $schedulesDeactivated = WeeklyTrainingSchedule::query()
            ->whereIn('group_id', $groupIds)
            ->where('is_active', true)
            ->update(['is_active' => false]);
        $futureSessions = TrainingSession::query()
            ->whereIn('group_id', $groupIds)
            ->whereDate('session_date', '>=', today()->toDateString());
        $futureSessionsRemoved = (clone $futureSessions)->count();

        $futureSessions->update([
            'attendance_token_hash' => null,
            'attendance_qr_token' => null,
            'attendance_qr_revoked_at' => now(),
        ]);
        $futureSessions->delete();

        return [
            'classes_deactivated' => $classesDeactivated,
            'schedules_deactivated' => $schedulesDeactivated,
            'future_sessions_removed' => $futureSessionsRemoved,
        ];
    }

    private function emptyDeactivationResult(): array
    {
        return [
            'classes_deactivated' => 0,
            'schedules_deactivated' => 0,
            'future_sessions_removed' => 0,
        ];
    }
}
