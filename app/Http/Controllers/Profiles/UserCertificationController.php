<?php

namespace App\Http\Controllers\Profiles;

use App\Actions\Profiles\SaveUserCertification;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Profiles\Concerns\AuthorizesProfileAccess;
use App\Http\Requests\Profiles\SaveUserCertificationRequest;
use App\Models\User;
use App\Models\UserCertification;
use App\Support\Profile\ProfileMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserCertificationController extends Controller
{
    use AuthorizesProfileAccess;

    public function __construct(private readonly ProfileMedia $profileMedia) {}

    public function store(
        SaveUserCertificationRequest $request,
        User $user,
        SaveUserCertification $saveUserCertification,
    ): RedirectResponse {
        $this->authorizeProfileAccess($request, $user);
        $saveUserCertification->store($user, $request->validated(), $request);

        return back()->with('status', 'Sertifikasi berhasil ditambahkan.');
    }

    public function update(
        SaveUserCertificationRequest $request,
        User $user,
        UserCertification $certification,
        SaveUserCertification $saveUserCertification,
    ): RedirectResponse {
        $this->authorizeProfileAccess($request, $user);
        abort_unless((int) $certification->user_id === (int) $user->id, 404);
        $saveUserCertification->update($user, $certification, $request->validated(), $request);

        return back()->with('status', 'Sertifikasi berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user, UserCertification $certification): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);
        abort_unless((int) $certification->user_id === (int) $user->id, 404);

        $file = $certification->file;
        $certification->delete();
        $this->profileMedia->deleteUserFile($file);

        return back()->with('status', 'Sertifikasi berhasil dihapus.');
    }
}
