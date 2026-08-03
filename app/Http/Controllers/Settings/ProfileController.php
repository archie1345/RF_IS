<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Profiles\SaveUserAchievement;
use App\Actions\Profiles\SaveUserCertification;
use App\Actions\Profiles\UpdateAccountProfile;
use App\Actions\Profiles\UpdateAthleteProfile;
use App\Actions\Profiles\UpdateCoachProfile;
use App\Actions\Profiles\UpdateParentProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profiles\SaveUserAchievementRequest;
use App\Http\Requests\Profiles\SaveUserCertificationRequest;
use App\Http\Requests\Profiles\UpdateAccountProfileRequest;
use App\Http\Requests\Profiles\UpdateAthleteProfileRequest;
use App\Http\Requests\Profiles\UpdateCoachProfileRequest;
use App\Http\Requests\Profiles\UpdateParentProfileRequest;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\UserAchievement;
use App\Models\UserCertification;
use App\Models\UserFile;
use App\Support\Profile\ProfilePageData;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfilePageData $profilePageData,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $this->profilePageData->loadUser($user);

        return Inertia::render('settings/ProfileWorkspace', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'accountUpdateUrl' => '/settings/profile',
            'profileUpdateUrl' => '/settings/profile/details',
            'certificationStoreUrl' => '/settings/profile/certifications',
            'achievementStoreUrl' => '/settings/profile/achievements',
            'branches' => $this->profilePageData->branchOptions(),
            'groups' => $this->profilePageData->groupOptions(),
            'user' => $this->profilePageData->user($user),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit')->with('status', 'Profil berhasil diperbarui.');
    }

    public function updateAccountProfile(
        UpdateAccountProfileRequest $request,
        UpdateAccountProfile $updateAccountProfile,
    ): RedirectResponse {
        $updateAccountProfile->handle($request->user(), $request->validated(), $request);

        return to_route('profile.edit')->with('status', 'Detail profil berhasil diperbarui.');
    }

    public function updateAthleteProfile(
        UpdateAthleteProfileRequest $request,
        UpdateAthleteProfile $updateAthleteProfile,
    ): RedirectResponse {
        abort_unless($request->user()->hasRole('athlete'), 404);
        $updateAthleteProfile->handle($request->user(), $request->validated());

        return to_route('profile.edit')->with('status', 'Data atlet berhasil diperbarui.');
    }

    public function updateCoachProfile(
        UpdateCoachProfileRequest $request,
        UpdateCoachProfile $updateCoachProfile,
    ): RedirectResponse {
        abort_unless($request->user()->hasRole('coach'), 404);
        $updateCoachProfile->handle($request->user(), $request->validated());

        return to_route('profile.edit')->with('status', 'Data pelatih berhasil diperbarui.');
    }

    public function updateParentProfile(
        UpdateParentProfileRequest $request,
        UpdateParentProfile $updateParentProfile,
    ): RedirectResponse {
        abort_unless($request->user()->hasRole('parent'), 404);
        $updateParentProfile->handle($request->user(), $request->validated());

        return to_route('profile.edit')->with('status', 'Data orang tua berhasil diperbarui.');
    }

    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();
        Auth::logout();
        $user->forceDelete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function storeCertification(
        SaveUserCertificationRequest $request,
        SaveUserCertification $saveUserCertification,
    ): RedirectResponse {
        $saveUserCertification->store($request->user(), $request->validated(), $request);

        return to_route('profile.edit')->with('status', 'Sertifikasi berhasil ditambahkan.');
    }

    public function updateCertification(
        SaveUserCertificationRequest $request,
        UserCertification $certification,
        SaveUserCertification $saveUserCertification,
    ): RedirectResponse {
        abort_unless((int) $certification->user_id === (int) $request->user()->id, 404);
        $saveUserCertification->update($request->user(), $certification, $request->validated(), $request);

        return to_route('profile.edit')->with('status', 'Sertifikasi berhasil diperbarui.');
    }

    public function destroyCertification(Request $request, UserCertification $certification): RedirectResponse
    {
        abort_unless((int) $certification->user_id === (int) $request->user()->id, 404);
        $file = $certification->file;
        $certification->delete();
        $this->deleteAttachedFile($file);

        return to_route('profile.edit')->with('status', 'Sertifikasi berhasil dihapus.');
    }

    public function storeAchievement(
        SaveUserAchievementRequest $request,
        SaveUserAchievement $saveUserAchievement,
    ): RedirectResponse {
        $saveUserAchievement->store($request->user(), $request->validated(), $request);

        return to_route('profile.edit')->with('status', 'Prestasi berhasil ditambahkan.');
    }

    public function updateAchievement(
        SaveUserAchievementRequest $request,
        UserAchievement $achievement,
        SaveUserAchievement $saveUserAchievement,
    ): RedirectResponse {
        abort_unless((int) $achievement->user_id === (int) $request->user()->id, 404);
        abort_if($achievement->is_auto_recorded, 403, 'Automatic achievements are managed from championship results.');
        $saveUserAchievement->update($request->user(), $achievement, $request->validated(), $request);

        return to_route('profile.edit')->with('status', 'Prestasi berhasil diperbarui.');
    }

    public function destroyAchievement(Request $request, UserAchievement $achievement): RedirectResponse
    {
        abort_unless((int) $achievement->user_id === (int) $request->user()->id, 404);
        abort_if($achievement->is_auto_recorded, 403, 'Automatic achievements are managed from championship results.');
        $file = $achievement->file;
        $achievement->delete();
        $this->deleteAttachedFile($file);

        return to_route('profile.edit')->with('status', 'Prestasi berhasil dihapus.');
    }

    private function deleteAttachedFile(?UserFile $file): void
    {
        if (! $file) {
            return;
        }

        if ($file->file_path) {
            Storage::disk($file->storageDisk())->delete($file->file_path);
        }

        $file->delete();
    }
}
