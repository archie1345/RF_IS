<?php

namespace App\Http\Controllers\Profiles;

use App\Actions\Profiles\UpdateAccountProfile;
use App\Actions\Profiles\UpdateUserAccount;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Profiles\Concerns\AuthorizesProfileAccess;
use App\Http\Requests\Profiles\UpdateAccountProfileRequest;
use App\Http\Requests\Profiles\UpdateUserAccountRequest;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;

class UserAccountController extends Controller
{
    use AuthorizesProfileAccess;

    public function update(UpdateUserAccountRequest $request, User $user, UpdateUserAccount $updateUserAccount): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);

        $updateUserAccount->handle($user, $request->validated());

        ActivityLogger::log($request, 'profile.account.updated', 'profile', 'Updated accessible user account', $user, ['user_id' => $user->id]);

        return back();
    }

    public function updateProfile(UpdateAccountProfileRequest $request, User $user, UpdateAccountProfile $updateAccountProfile): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);

        $updateAccountProfile->handle($user, $request->validated(), $request);

        ActivityLogger::log($request, 'profile.details.updated', 'profile', 'Updated accessible user profile details', $user, ['user_id' => $user->id]);

        return back();
    }
}
