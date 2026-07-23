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
            'pageTitle' => $isParent ? 'Children achievements' : 'My achievements',
            'pageDescription' => $isParent
                ? 'View achievements recorded for every linked child. Parent mode is read-only.'
                : 'Manage event, medal, and professional achievement records for the current account role.',
            'achievements' => $achievements->map(fn (UserAchievement $achievement) => [
                'id' => $achievement->id,
                'subject' => $achievement->user?->name ?? 'Unknown user',
                'championship_name' => $achievement->championship_name,
                'medal' => $achievement->medal,
                'location' => $achievement->location ?? '-',
                'event_date' => $achievement->event_date?->format('Y-m-d') ?? '-',
                'class_name' => $achievement->class_name ?? '-',
                'division' => $achievement->division ?? '-',
                'category' => $achievement->category ?? '-',
                'is_auto_recorded' => $achievement->is_auto_recorded,
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
        $userFile = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('user-files', 'public');

            $userFile = UserFile::query()->create([
                'user_id' => $user->id,
                'file_type' => 'EVENT_DOCUMENT',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }

        $user->achievements()->create(
            collect($validated)->except('file')->all() + [
                'is_auto_recorded' => false,
                'user_file_id' => $userFile?->id,
            ],
        );

        return redirect()->route('achievements.index');
    }
}
