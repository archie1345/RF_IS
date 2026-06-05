<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Actions\Profiles\SaveUserAchievement;
use App\Actions\Profiles\SaveUserCertification;
use App\Actions\Profiles\UpdateAccountProfile;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Requests\Profiles\SaveUserAchievementRequest;
use App\Http\Requests\Profiles\SaveUserCertificationRequest;
use App\Http\Requests\Profiles\UpdateAccountProfileRequest;
use App\Models\UserAchievement;
use App\Models\UserCertification;
use App\Support\Profile\ProfilePageData;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfilePageData $profilePageData,
    ) {
    }

    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $this->profilePageData->loadUser($user);

        return Inertia::render('profiles/ProfileDetailsPage', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'context' => 'settings',
            'canEditAccount' => true,
            'canEditRoleProfiles' => false,
            'accountUpdateUrl' => '/settings/profile',
            'profileUpdateUrl' => '/settings/profile/details',
            'certificationStoreUrl' => '/settings/profile/certifications',
            'achievementStoreUrl' => '/settings/profile/achievements',
            'branches' => $this->profilePageData->branchOptions(),
            'groups' => $this->profilePageData->groupOptions(),
            'user' => $this->profilePageData->user($user),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    public function updateAccountProfile(UpdateAccountProfileRequest $request, UpdateAccountProfile $updateAccountProfile): RedirectResponse
    {
        $user = $request->user();

        $updateAccountProfile->handle($user, $request->validated(), $request);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->forceDelete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function storeCertification(SaveUserCertificationRequest $request, SaveUserCertification $saveUserCertification): RedirectResponse
    {
        $validated = $request->validate([
            'cert_type' => ['required', Rule::in(['BELT', 'REFEREE', 'TRAINER'])],
            'title' => ['required', 'string', 'max:120'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'certified_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'file' => $this->documentFileRules(),
        ]);

        $user = $request->user();
        $userFile = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('user-files', 'public');

            $userFile = UserFile::query()->create([
                'user_id' => $user->id,
                'file_type' => 'CERTIFICATE',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }

        $payload = collect($validated)->except('file')->all();

        if (Schema::hasColumn('user_certifications', 'user_file_id')) {
            $payload['user_file_id'] = $userFile?->id;
        }

        $user->certifications()->create($payload);

        return to_route('profile.edit');
    }

    public function updateCertification(SaveUserCertificationRequest $request, UserCertification $certification, SaveUserCertification $saveUserCertification): RedirectResponse
    {
        abort_unless((int) $certification->user_id === (int) $request->user()->id, 404);

        $validated = $request->validate([
            'cert_type' => ['required', Rule::in(['BELT', 'REFEREE', 'TRAINER'])],
            'title' => ['required', 'string', 'max:120'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'certified_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'file' => $this->documentFileRules(),
        ]);

        $payload = collect($validated)->except('file')->all();

        if ($request->hasFile('file') && Schema::hasColumn('user_certifications', 'user_file_id')) {
            $file = $request->file('file');
            $path = $file->store('user-files', 'public');

            $userFile = UserFile::query()->create([
                'user_id' => $request->user()->id,
                'file_type' => 'CERTIFICATE',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);

            $payload['user_file_id'] = $userFile->id;
        }

        $certification->update($payload);

        return to_route('profile.edit');
    }

    public function storeAchievement(SaveUserAchievementRequest $request, SaveUserAchievement $saveUserAchievement): RedirectResponse
    {
        $validated = $request->validate([
            'championship_name' => ['required', 'string', 'max:120'],
            'medal' => ['required', Rule::in(['GOLD', 'SILVER', 'BRONZE', 'NONE'])],
            'location' => ['nullable', 'string', 'max:160'],
            'event_date' => ['nullable', 'date'],
            'class_name' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'file' => $this->documentFileRules(),
        ]);

        $user = $request->user();
        $userFile = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('user-files', 'public');

            $userFile = UserFile::query()->create([
                'user_id' => $user->id,
                'file_type' => 'EVENT_DOCUMENT',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }

        $payload = collect($validated)->except('file')->all() + ['is_auto_recorded' => false];

        if (Schema::hasColumn('user_achievements', 'user_file_id')) {
            $payload['user_file_id'] = $userFile?->id;
        }

        $user->achievements()->create($payload);

        return to_route('profile.edit');
    }

    public function updateAchievement(SaveUserAchievementRequest $request, UserAchievement $achievement, SaveUserAchievement $saveUserAchievement): RedirectResponse
    {
        abort_unless((int) $achievement->user_id === (int) $request->user()->id, 404);

        $validated = $request->validate([
            'championship_name' => ['required', 'string', 'max:120'],
            'medal' => ['required', Rule::in(['GOLD', 'SILVER', 'BRONZE', 'NONE'])],
            'location' => ['nullable', 'string', 'max:160'],
            'event_date' => ['nullable', 'date'],
            'class_name' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'file' => $this->documentFileRules(),
        ]);

        $payload = collect($validated)->except('file')->all();

        if ($request->hasFile('file') && Schema::hasColumn('user_achievements', 'user_file_id')) {
            $file = $request->file('file');
            $path = $file->store('user-files', 'public');

            $userFile = UserFile::query()->create([
                'user_id' => $request->user()->id,
                'file_type' => 'EVENT_DOCUMENT',
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);

            $payload['user_file_id'] = $userFile->id;
        }

        $achievement->update($payload);

        return to_route('profile.edit');
    }

    private function profilePageUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'gender' => $user->gender,
            'bday' => $user->bday?->format('Y-m-d'),
            'phone' => $user->phone,
            'roles' => $user->assignedRoles(),
            'bio' => $user->profile?->bio,
            'profilePictureUrl' => $user->profile?->profile_picture_path ? Storage::url($user->profile->profile_picture_path) : null,
            'athleteProfile' => $user->athleteProfile ? [
                'height_cm' => $user->athleteProfile->height_cm,
                'weight_kg' => $user->athleteProfile->weight_kg,
                'geup' => $user->athleteProfile->geup,
                'nik' => $user->athleteProfile->displayValue('nik'),
                'bpjs' => $user->athleteProfile->displayValue('bpjs'),
                'nikHash' => $user->athleteProfile->nik_hash,
                'bpjsHash' => $user->athleteProfile->bpjs_hash,
                'phone' => $user->phone,
                'bday' => $user->bday?->format('Y-m-d'),
                'gender' => $user->gender,
                'alamat' => $user->athleteProfile->alamat,
                'branch_id' => $user->athleteProfile->branch_id,
                'group_id' => $user->athleteProfile->group_id,
                'branch' => $user->athleteProfile->branch,
                'group' => $user->athleteProfile->group,
            ] : null,
            'coachProfile' => $user->coachProfile ? [
                'status' => $user->coachProfile->status,
                'specialization' => $user->coachProfile->specialization,
                'bio' => $user->coachProfile->bio,
            ] : null,
            'parentProfile' => $user->parentProfile ? [
                'phone' => $user->phone,
                'relation' => $user->parentProfile->relation,
                'occupation' => $user->parentProfile->occupation,
                'notes' => $user->parentProfile->notes,
                'athletes' => $user->parentProfile->athletes->map(fn ($athlete) => [
                    'id' => $athlete->athlete_id,
                    'name' => $athlete->user?->name ?? 'Unknown athlete',
                    'branch' => $athlete->branch,
                    'group' => $athlete->group,
                ]),
            ] : null,
            'achievements' => $user->achievements->map(fn ($achievement) => [
                'id' => $achievement->id,
                'championship_name' => $achievement->championship_name,
                'medal' => $achievement->medal,
                'location' => $achievement->location,
                'event_date' => $achievement->event_date?->format('Y-m-d'),
                'class_name' => $achievement->class_name,
                'division' => $achievement->division,
                'category' => $achievement->category,
                'notes' => $achievement->notes,
                'fileName' => $achievement->file?->original_name,
                'fileUrl' => $achievement->file?->file_path ? Storage::url($achievement->file->file_path) : null,
            ]),
            'certifications' => $user->certifications->map(fn ($cert) => [
                'id' => $cert->id,
                'cert_type' => $cert->cert_type,
                'title' => $cert->title,
                'issuer' => $cert->issuer,
                'certified_at' => $cert->certified_at?->format('Y-m-d'),
                'expires_at' => $cert->expires_at?->format('Y-m-d'),
                'notes' => $cert->notes,
                'fileName' => $cert->file?->original_name,
                'fileUrl' => $cert->file?->file_path ? Storage::url($cert->file->file_path) : null,
            ]),
        ];
    }
}
