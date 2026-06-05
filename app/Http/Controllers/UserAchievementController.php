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
        $user->load(['certifications', 'achievements.file']);

        return Inertia::render('AchievementsPage', [
            'achievements' => $user->achievements->map(fn (UserAchievement $ach) => [
                'id' => $ach->id,
                'championship_name' => $ach->championship_name,
                'medal' => $ach->medal,
                'location' => $ach->location ?? '-',
                'event_date' => $ach->event_date?->format('Y-m-d') ?? '-',
                'class_name' => $ach->class_name ?? '-',
                'division' => $ach->division ?? '-',
                'category' => $ach->category ?? '-',
                'is_auto_recorded' => $ach->is_auto_recorded,
                'file_name' => $ach->file?->original_name ?? '-',
                'file_url' => $ach->file?->file_path ? Storage::url($ach->file->file_path) : '',
            ])->values(),
        ]);
    }

    public function storeAchievement(Request $request, ProfileFormRules $profileFormRules): RedirectResponse
    {
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
