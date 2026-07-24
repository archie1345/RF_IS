<?php

namespace App\Http\Controllers;

use App\Actions\Profiles\SaveUserAchievement;
use App\Models\UserAchievement;
use App\Support\Profile\ProfileFormRules;
use App\Support\Profile\ProfileMedia;
use App\Services\ActiveRoleContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserAchievementController extends Controller
{
    public function __construct(
        private readonly ActiveRoleContextService $activeRoleContext,
        private readonly SaveUserAchievement $saveUserAchievement,
        private readonly ProfileMedia $profileMedia,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $this->activeRoleContext->activeRole($request, $user);
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
                    && (int) $achievement->user_id === (int) $user->id
                    && ! $achievement->is_auto_recorded,
                'file_name' => $achievement->file?->original_name ?? '-',
                'file_url' => $achievement->file
                    ? route('user-files.download', $achievement->file)
                    : '',
            ])->values(),
        ]);
    }

    public function storeAchievement(Request $request, ProfileFormRules $profileFormRules): RedirectResponse
    {
        abort_if($this->activeRoleContext->isActive($request, 'parent'), 403);

        $this->saveUserAchievement->store(
            $request->user(),
            $request->validate($profileFormRules->achievement()),
            $request,
        );

        return redirect()->route('achievements.index')->with('status', 'Prestasi berhasil ditambahkan.');
    }

    public function updateAchievement(
        Request $request,
        UserAchievement $achievement,
        ProfileFormRules $profileFormRules,
    ): RedirectResponse {
        $this->authorizeManualAchievement($request, $achievement);
        $this->saveUserAchievement->update(
            $request->user(),
            $achievement,
            $request->validate($profileFormRules->achievement()),
            $request,
        );

        return back()->with('status', 'Prestasi berhasil diperbarui.');
    }

    public function destroyAchievement(Request $request, UserAchievement $achievement): RedirectResponse
    {
        $this->authorizeManualAchievement($request, $achievement);
        $file = $achievement->file;
        $achievement->delete();
        $this->profileMedia->deleteUserFile($file);

        return back()->with('status', 'Prestasi berhasil dihapus.');
    }

    private function authorizeManualAchievement(Request $request, UserAchievement $achievement): void
    {
        abort_if($this->activeRoleContext->isActive($request, 'parent'), 403);
        abort_unless((int) $achievement->user_id === (int) $request->user()?->id, 403);
        abort_if($achievement->is_auto_recorded, 403, 'Automatic achievements must be managed from championship results.');
    }
}
