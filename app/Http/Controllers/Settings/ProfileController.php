<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserCertification;
use App\Models\UserFile;
use App\Models\UserProfile;
use App\Models\Branch;
use App\Models\Group;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $user->load([
            'profile',
            'athleteProfile.branch',
            'athleteProfile.group',
            'coachProfile',
            'parentProfile.athletes.branch',
            'parentProfile.athletes.group',
            'parentProfile.athletes.user',
            'achievements.file',
            'certifications.file',
            'roleAssignments',
        ]);

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
            'branches' => Branch::query()
                ->orderBy('branch_name')
                ->get(['branch_id as value', 'branch_name as label']),
            'groups' => Group::query()
                ->orderBy('group_name')
                ->get(['group_id as value', 'group_name as label']),
            'user' => $this->profilePageUser($user),
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

    public function updateAccountProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'bio' => ['nullable', 'string'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $payload = ['bio' => $validated['bio'] ?? null];

        if ($request->hasFile('profile_picture')) {
            if ($user->profile?->profile_picture_path) {
                Storage::disk('public')->delete($user->profile->profile_picture_path);
            }

            $image = Image::decodePath($request->file('profile_picture')->getRealPath())
                ->cover(600, 800)
                ->encodeUsingMediaType('image/jpeg', quality: 90);

            $path = 'profiles/'.uniqid('profile_', true).'.jpg';
            Storage::disk('public')->put($path, (string) $image);

            $payload['profile_picture_path'] = $path;
        }

        UserProfile::query()->updateOrCreate(['user_id' => $user->id], $payload);

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

    public function storeCertification(Request $request): RedirectResponse
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

    public function updateCertification(Request $request, UserCertification $certification): RedirectResponse
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

    public function storeAchievement(Request $request): RedirectResponse
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

    public function updateAchievement(Request $request, UserAchievement $achievement): RedirectResponse
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
