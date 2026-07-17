<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrainingGroup;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $trainingGroup->update($this->validated($request, $trainingGroup));

        ActivityLogger::log($request, 'admin.training_group.updated', 'admin', 'Updated training group', $trainingGroup, [
            'name' => $trainingGroup->name,
        ]);

        return back()->with('status', 'Grup berhasil diperbarui.');
    }

    public function destroy(Request $request, TrainingGroup $trainingGroup): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($trainingGroup->classes()->exists() || $trainingGroup->athletes()->exists()) {
            $trainingGroup->update(['is_active' => false]);

            return back()->with('status', 'Grup masih dipakai, jadi dinonaktifkan agar data lama tetap aman.');
        }

        $trainingGroup->delete();

        return back()->with('status', 'Grup berhasil dihapus.');
    }

    private function validated(Request $request, ?TrainingGroup $group = null): array
    {
        $ignoreId = $group?->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:training_groups,name'.($ignoreId ? ','.$ignoreId : '')],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);
    }
}
