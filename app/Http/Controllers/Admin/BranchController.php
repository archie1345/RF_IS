<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Group;
use App\Models\TrainingSession;
use App\Models\WeeklyTrainingSchedule;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validated($request);
        $branch = Branch::query()->create($this->payload($validated));

        ActivityLogger::log($request, 'admin.branch.created', 'admin', 'Created training location', $branch, [
            'branch_name' => $branch->branch_name,
            'is_active' => $branch->is_active,
        ]);

        return back()->with('status', 'Lokasi latihan berhasil dibuat.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $this->validated($request, $branch);
        $wasActive = (bool) $branch->is_active;

        $result = DB::transaction(function () use ($branch, $validated, $wasActive): array {
            $branch->update($this->payload($validated));

            return $wasActive && ! $branch->is_active
                ? $this->deactivateDependants($branch)
                : $this->emptyDeactivationResult();
        });

        ActivityLogger::log($request, 'admin.branch.updated', 'admin', 'Updated training location', $branch, [
            'branch_name' => $branch->branch_name,
            'is_active' => $branch->is_active,
            ...$result,
        ]);

        return back()->with(
            'status',
            ! $branch->is_active && $result['future_sessions_removed'] > 0
                ? "Lokasi diperbarui dan dinonaktifkan. {$result['classes_deactivated']} kelas, {$result['schedules_deactivated']} jadwal, dan {$result['future_sessions_removed']} sesi mendatang dihentikan."
                : 'Lokasi latihan berhasil diperbarui.',
        );
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        [$deactivated, $result] = DB::transaction(function () use ($branch): array {
            $locked = Branch::query()->lockForUpdate()->findOrFail($branch->branch_id);
            $hasHistory = $locked->athletes()->exists()
                || $locked->groups()->withTrashed()->exists()
                || WeeklyTrainingSchedule::withTrashed()->where('branch_id', $locked->branch_id)->exists()
                || TrainingSession::withTrashed()->where('branch_id', $locked->branch_id)->exists();

            if ($hasHistory) {
                $locked->update(['is_active' => false]);

                return [true, $this->deactivateDependants($locked)];
            }

            $locked->delete();

            return [false, $this->emptyDeactivationResult()];
        });

        ActivityLogger::log(
            $request,
            $deactivated ? 'admin.branch.deactivated' : 'admin.branch.deleted',
            'admin',
            $deactivated ? 'Deactivated training location with linked history' : 'Deleted unused training location',
            $branch,
            ['branch_name' => $branch->branch_name, ...$result],
        );

        if ($deactivated) {
            return back()->with(
                'status',
                "Lokasi masih memiliki data terkait, sehingga dinonaktifkan. {$result['classes_deactivated']} kelas, {$result['schedules_deactivated']} jadwal, dan {$result['future_sessions_removed']} sesi mendatang dihentikan; riwayat tetap tersimpan.",
            );
        }

        return back()->with('status', 'Lokasi latihan berhasil dihapus.');
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('branches', 'branch_name')
                    ->whereNull('deleted_at')
                    ->ignore($branch?->branch_id, 'branch_id'),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'attendance_radius_meters' => ['nullable', 'integer', 'between:20,5000'],
            'timezone' => ['nullable', 'timezone:all'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (($validated['is_active'] ?? false) && ! $this->canBeActive($validated)) {
            throw ValidationException::withMessages([
                'is_active' => 'Lengkapi alamat, kota, provinsi, latitude, dan longitude sebelum mengaktifkan lokasi.',
            ]);
        }

        return $validated;
    }

    private function payload(array $validated): array
    {
        return [
            'branch_name' => trim($validated['name']),
            'location' => filled($validated['location'] ?? null)
                ? trim($validated['location'])
                : (filled($validated['address'] ?? null) ? trim($validated['address']) : null),
            'address' => filled($validated['address'] ?? null) ? trim($validated['address']) : null,
            'city' => filled($validated['city'] ?? null) ? trim($validated['city']) : null,
            'province' => filled($validated['province'] ?? null) ? trim($validated['province']) : null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'attendance_radius_meters' => $validated['attendance_radius_meters'] ?? 100,
            'timezone' => $validated['timezone'] ?? 'Asia/Jakarta',
            'is_active' => (bool) ($validated['is_active'] ?? false) && $this->canBeActive($validated),
        ];
    }

    private function canBeActive(array $validated): bool
    {
        return filled($validated['name'] ?? null)
            && filled($validated['address'] ?? null)
            && filled($validated['city'] ?? null)
            && filled($validated['province'] ?? null)
            && $this->validCoordinate($validated['latitude'] ?? null, -90, 90)
            && $this->validCoordinate($validated['longitude'] ?? null, -180, 180);
    }

    private function validCoordinate(mixed $value, float $min, float $max): bool
    {
        return is_numeric($value) && (float) $value >= $min && (float) $value <= $max;
    }

    private function deactivateDependants(Branch $branch): array
    {
        $classesDeactivated = Group::query()
            ->where('branch_id', $branch->branch_id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
        $schedulesDeactivated = WeeklyTrainingSchedule::query()
            ->where('branch_id', $branch->branch_id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
        $futureSessions = TrainingSession::query()
            ->where('branch_id', $branch->branch_id)
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
