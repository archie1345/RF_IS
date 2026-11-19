<?php

namespace App\Http\Controllers;

use App\Actions\Profiles\UpdateAccountProfile;
use App\Actions\Profiles\UpdateAthleteProfile;
use App\Actions\Profiles\UpdateUserAccount;
use App\Http\Controllers\Profiles\Concerns\AuthorizesProfileAccess;
use App\Http\Requests\Profiles\UpdateAccountProfileRequest;
use App\Http\Requests\Profiles\UpdateAthleteProfileRequest;
use App\Http\Requests\Profiles\UpdateUserAccountRequest;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Profile\ProfilePageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileAccessController extends Controller
{
    use AuthorizesProfileAccess;

    public function __construct(
        private readonly ProfilePageData $profilePageData,
    ) {}

    public function show(Request $request, User $user): Response
    {
        $this->authorizeProfileAccess($request, $user);
        $this->profilePageData->loadUser($user);

        return Inertia::render('profiles/ProfileDetailsPage', [
            'user' => $this->profilePageData->user($user),
            'context' => 'admin',
            'canEditAccount' => true,
            'canEditRoleProfiles' => true,
            'accountUpdateUrl' => '/users/'.$user->uuid.'/account',
            'profileUpdateUrl' => '/users/'.$user->uuid.'/profile',
            'certificationStoreUrl' => '/users/'.$user->uuid.'/certifications',
            'achievementStoreUrl' => '/users/'.$user->uuid.'/achievements',
            'passwordUpdateUrl' => null,
            'branches' => $this->profilePageData->branchOptions(),
            'groups' => $this->profilePageData->groupOptions(),
        ]);
    }

    public function updateAccount(UpdateUserAccountRequest $request, User $user, UpdateUserAccount $updateUserAccount): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);

        $updateUserAccount->handle($user, $request->validated());

        ActivityLogger::log($request, 'profile.account.updated', 'profile', 'Updated accessible user account', $user, ['user_id' => $user->uuid]);

        return back();
    }

    public function updateAccountProfile(UpdateAccountProfileRequest $request, User $user, UpdateAccountProfile $updateAccountProfile): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);

        $updateAccountProfile->handle($user, $request->validated(), $request);

        ActivityLogger::log($request, 'profile.details.updated', 'profile', 'Updated accessible user profile details', $user, ['user_id' => $user->uuid]);

        return back();
    }

    public function updateAthleteProfile(UpdateAthleteProfileRequest $request, User $user, UpdateAthleteProfile $updateAthleteProfile): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);
        abort_unless($user->hasRole('athlete'), 404);

        $updateAthleteProfile->handle($user, $request->validated());

        ActivityLogger::log($request, 'profile.athlete.updated', 'profile', 'Updated accessible athlete profile', $user, ['user_id' => $user->uuid]);

        return back();
    }
}
