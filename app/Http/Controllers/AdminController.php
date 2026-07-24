<?php

namespace App\Http\Controllers;

use App\Actions\Profiles\UpdateAccountProfile;
use App\Http\Requests\Profiles\UpdateAccountProfileRequest;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Profile\ProfilePageData;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function show(User $user, ProfilePageData $profilePageData): Response
    {
        abort_unless(request()->user()?->isAdmin(), 403);

        $profilePageData->loadUser($user);

        return Inertia::render('profiles/ProfileDetailsPage', [
            'user' => $profilePageData->user($user),
            'context' => 'admin',
            'canEditAccount' => false,
            'canEditRoleProfiles' => true,
            'branches' => $profilePageData->branchOptions(),
            'groups' => $profilePageData->groupOptions(),
        ]);
    }

    public function updateAccountProfile(
        UpdateAccountProfileRequest $request,
        User $user,
        UpdateAccountProfile $updateAccountProfile,
    ): RedirectResponse {
        abort_unless($request->user()?->isAdmin(), 403);

        $updateAccountProfile->handle($user, $request->validated(), $request);
        ActivityLogger::log(
            $request,
            'admin.account.profile.updated',
            'admin',
            'Updated account roster profile',
            $user,
            ['user_id' => $user->id],
        );

        return back()->with('status', 'Profil akun berhasil diperbarui.');
    }
}
