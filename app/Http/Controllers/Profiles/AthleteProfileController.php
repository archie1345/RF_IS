<?php

namespace App\Http\Controllers\Profiles;

use App\Actions\Profiles\UpdateAthleteProfile;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Profiles\Concerns\AuthorizesProfileAccess;
use App\Http\Requests\Profiles\UpdateAthleteProfileRequest;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;

class AthleteProfileController extends Controller
{
    use AuthorizesProfileAccess;

    public function update(UpdateAthleteProfileRequest $request, User $user, UpdateAthleteProfile $updateAthleteProfile): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);
        abort_unless($user->hasRole('athlete'), 404);

        $updateAthleteProfile->handle($user, $request->validated());

        ActivityLogger::log($request, 'profile.athlete.updated', 'profile', 'Updated accessible athlete profile', $user, ['user_id' => $user->id]);

        return back();
    }
}
