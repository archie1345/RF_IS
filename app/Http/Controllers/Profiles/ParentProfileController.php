<?php

namespace App\Http\Controllers\Profiles;

use App\Actions\Profiles\UpdateParentProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profiles\UpdateParentProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ParentProfileController extends Controller
{
    public function update(UpdateParentProfileRequest $request, User $user, UpdateParentProfile $updateParentProfile): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->hasRole('parent'), 404);

        $updateParentProfile->handle($user, $request->validated());

        return back();
    }
}
