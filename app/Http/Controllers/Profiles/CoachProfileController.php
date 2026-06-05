<?php

namespace App\Http\Controllers\Profiles;

use App\Actions\Profiles\UpdateCoachProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profiles\UpdateCoachProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class CoachProfileController extends Controller
{
    public function update(UpdateCoachProfileRequest $request, User $user, UpdateCoachProfile $updateCoachProfile): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($user->hasRole('coach'), 404);

        $updateCoachProfile->handle($user, $request->validated());

        return back();
    }
}
