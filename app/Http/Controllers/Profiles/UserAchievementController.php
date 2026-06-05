<?php

namespace App\Http\Controllers\Profiles;

use App\Actions\Profiles\SaveUserAchievement;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Profiles\Concerns\AuthorizesProfileAccess;
use App\Http\Requests\Profiles\SaveUserAchievementRequest;
use App\Models\User;
use App\Models\UserAchievement;
use Illuminate\Http\RedirectResponse;

class UserAchievementController extends Controller
{
    use AuthorizesProfileAccess;

    public function store(SaveUserAchievementRequest $request, User $user, SaveUserAchievement $saveUserAchievement): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);

        $saveUserAchievement->store($user, $request->validated(), $request);

        return back();
    }

    public function update(SaveUserAchievementRequest $request, User $user, UserAchievement $achievement, SaveUserAchievement $saveUserAchievement): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);
        abort_unless((int) $achievement->user_id === (int) $user->id, 404);

        $saveUserAchievement->update($user, $achievement, $request->validated(), $request);

        return back();
    }
}
