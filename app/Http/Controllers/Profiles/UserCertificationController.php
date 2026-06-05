<?php

namespace App\Http\Controllers\Profiles;

use App\Actions\Profiles\SaveUserCertification;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Profiles\Concerns\AuthorizesProfileAccess;
use App\Http\Requests\Profiles\SaveUserCertificationRequest;
use App\Models\User;
use App\Models\UserCertification;
use Illuminate\Http\RedirectResponse;

class UserCertificationController extends Controller
{
    use AuthorizesProfileAccess;

    public function store(SaveUserCertificationRequest $request, User $user, SaveUserCertification $saveUserCertification): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);

        $saveUserCertification->store($user, $request->validated(), $request);

        return back();
    }

    public function update(SaveUserCertificationRequest $request, User $user, UserCertification $certification, SaveUserCertification $saveUserCertification): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);
        abort_unless((int) $certification->user_id === (int) $user->id, 404);

        $saveUserCertification->update($user, $certification, $request->validated(), $request);

        return back();
    }
}
