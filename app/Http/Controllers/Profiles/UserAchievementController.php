<?php

namespace App\Http\Controllers\Profiles;

use App\Actions\Profiles\SaveUserAchievement;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Profiles\Concerns\AuthorizesProfileAccess;
use App\Http\Requests\Profiles\SaveUserAchievementRequest;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserAchievementController extends Controller
{
    use AuthorizesProfileAccess;

    public function store(
        SaveUserAchievementRequest $request,
        User $user,
        SaveUserAchievement $saveUserAchievement,
    ): RedirectResponse {
        $this->authorizeProfileAccess($request, $user);
        $saveUserAchievement->store($user, $request->validated(), $request);

        return back()->with('status', 'Prestasi berhasil ditambahkan.');
    }

    public function update(
        SaveUserAchievementRequest $request,
        User $user,
        UserAchievement $achievement,
        SaveUserAchievement $saveUserAchievement,
    ): RedirectResponse {
        $this->authorizeProfileAccess($request, $user);
        abort_unless((int) $achievement->user_id === (int) $user->id, 404);
        abort_if($achievement->is_auto_recorded, 403, 'Automatic achievements are managed from championship results.');
        $saveUserAchievement->update($user, $achievement, $request->validated(), $request);

        return back()->with('status', 'Prestasi berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user, UserAchievement $achievement): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);
        abort_unless((int) $achievement->user_id === (int) $user->id, 404);
        abort_if($achievement->is_auto_recorded, 403, 'Automatic achievements are managed from championship results.');
        $file = $achievement->file;
        $achievement->delete();

        if ($file) {
            if ($file->file_path) {
                Storage::disk('public')->delete($file->file_path);
            }
            $file->delete();
        }

        return back()->with('status', 'Prestasi berhasil dihapus.');
    }
}
