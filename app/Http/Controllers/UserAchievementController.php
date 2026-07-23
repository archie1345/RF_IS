<?php

namespace App\Http\Controllers;

use App\Models\UserAchievement;
use App\Models\UserFile;
use App\Support\Profile\ProfileFormRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserAchievementController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user?->primaryRole() ?? 'athlete';
        $isParent = $role === 'parent';
        $childUserIds = $isParent
            ? $user->children()->pluck('athletes.id')->map(fn ($id) => (int) $id)->all()
            : [];

        $achievements = UserAchievement::query()
            ->with(['user:id,name', 'file'])
            ->when(
                $isParent,
                fn ($query) => $query->whereIn('user_id', $childUserIds),
                fn ($query) => $query->where('user_id', $user->id),
            )
            ->latest('event_date')
            ->latest('id')
            ->get();

        return Inertia::render('AchievementsPage', [
            'canCreate' => ! $isParent,
            'pageTitle' => $isParent ? 'Prestasi anak' : 'Prestasi saya',
            'pageDescription' => $isParent
                ? 'Lihat prestasi dari semua anak yang terhubung. Mode orang tua bersifat hanya-baca.'
                : 'Kelola prestasi yang dicatat secara manual. Hasil otomatis dikelola dari halaman kejuaraan.',
            'achievements' => $achievements->map(fn (UserAchievement $achievement) => [
                'id' => $achievement->id,
                'achievement_id' => $achievement->id,
                'subject' => $achievement->user?->name ?? 'Unknown user',
                'championship_name' => $achievement->championship_name,
                'medal' => $achievement->medal,
                'location' => $achievement->location ?? '-',
                'event_date' => $achievement->event_date?->format('Y-m-d') ?? '-',
                'class_name' => $achievement->class_name ?? '-',
                'division' => $achievement->division ?? '-',
                'category' => $achievement->category ?? '-',
                'notes' => $achievement->notes ?? '',
                'is_auto_recorded' => $achievement->is_auto_recorded,
                'can_manage' => ! $isParent
                    && (int) $achievement->user_id === (int) $user?->id
                    && ! $achievement->is_auto_recorded,
                'file_name' => $achievement->file?->original_name ?? '-',
                'file_url' => $achievement->file?->file_path
                    ? Storage::url($achievement->file->file_path)
                    : '',
            ])->values(),
        ]);
    }

    public function storeAchievement(Request $request, ProfileFormRules $profileFormRules): RedirectResponse
    {
        abort_if($request->user()?->isParent(), 403);
        $validated = $request->validate($profileFormRules->achievement());
        $user = $request->user();
        $userFile = $this->storeFile($request, $user->id);

        $user->achievements()->create(
            collect($validated)->except('file')->all() + [
                'is_auto_recorded' => false,
                'user_file_id' => $userFile?->id,
            ],
        );

        return redirect()->route('achievements.index')->with('status', 'Prestasi berhasil ditambahkan.');
    }

    public function updateAchievement(
        Request $request,
        UserAchievement $achievement,
        ProfileFormRules $profileFormRules,
    ): RedirectResponse {
        $this->authorizeManualAchievement($request, $achievement);
        $validated = $request->validate($profileFormRules->achievement());
        $oldFile = $achievement->file;
        $newFile = $this->storeFile($request, $request->user()->id);

        $achievement->update(
            collect($validated)->except('file')->all() + [
                'user_file_id' => $newFile?->id ?? $achievement->user_file_id,
            ],
        );

        if ($newFile && $oldFile) {
            $this->deleteFile($oldFile);
        }

        return back()->with('status', 'Prestasi berhasil diperbarui.');
    }

    public function destroyAchievement(Request $request, UserAchievement $achievement): RedirectResponse
    {
        $this->authorizeManualAchievement($request, $achievement);
        $file = $achievement->file;
        $achievement->delete();

        if ($file) {
            $this->deleteFile($file);
        }

        return back()->with('status', 'Prestasi berhasil dihapus.');
    }

    private function authorizeManualAchievement(Request $request, UserAchievement $achievement): void
    {
        abort_if($request->user()?->isParent(), 403);
        abort_unless((int) $achievement->user_id === (int) $request->user()?->id, 403);
        abort_if($achievement->is_auto_recorded, 403, 'Automatic achievements must be managed from championship results.');
    }

    private function storeFile(Request $request, int $userId): ?UserFile
    {
        if (! $request->hasFile('file')) {
            return null;
        }

        $file = $request->file('file');
        $path = $file->store('user-files', 'public');

        return UserFile::query()->create([
            'user_id' => $userId,
            'file_type' => 'EVENT_DOCUMENT',
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    private function deleteFile(UserFile $file): void
    {
        if ($file->file_path) {
            Storage::disk('public')->delete($file->file_path);
        }

        $file->delete();
    }
}
