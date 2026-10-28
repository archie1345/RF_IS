<?php

namespace App\Http\Controllers;

use App\Actions\Profiles\SaveUserAchievement;
use App\Models\UserAchievement;
use App\Support\Profile\ProfileFormRules;
use App\Support\Profile\ProfileMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserAchievementController extends Controller
{
    public function __construct(
        private readonly SaveUserAchievement $saveUserAchievement,
        private readonly ProfileMedia $profileMedia,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $achievements = UserAchievement::query()
            ->with(['user:id,name', 'file'])
            ->where('user_id', $user->id)
            ->latest('event_date')
            ->latest('id')
            ->get();

        return Inertia::render('AchievementsPage', [
            'canCreate' => true,
            'pageTitle' => 'Prestasi saya',
            'pageDescription' => 'Kelola prestasi yang dicatat secara manual. Hasil otomatis dikelola dari halaman kejuaraan.',
            'achievements' => $achievements->map(fn (UserAchievement $achievement) => [
                'id' => $achievement->id,
                'achievement_id' => $achievement->id,
                'route_id' => $achievement->uuid,
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
                'can_manage' => (int) $achievement->user_id === (int) $user->id
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
        abort_unless((int) $achievement->user_id === (int) $request->user()?->id, 403);
        abort_if($achievement->is_auto_recorded, 403, 'Automatic achievements must be managed from championship results.');
    }
}
