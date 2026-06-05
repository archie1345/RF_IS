<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FormatsMvpData;
use App\Actions\Profiles\SaveUserAchievement;
use App\Actions\Profiles\SaveUserCertification;
use App\Actions\Profiles\UpdateAccountProfile;
use App\Actions\Profiles\UpdateAthleteProfile;
use App\Actions\Profiles\UpdateUserAccount;
use App\Http\Requests\Profiles\SaveUserAchievementRequest;
use App\Http\Requests\Profiles\SaveUserCertificationRequest;
use App\Http\Requests\Profiles\UpdateAccountProfileRequest;
use App\Http\Requests\Profiles\UpdateAthleteProfileRequest;
use App\Http\Requests\Profiles\UpdateUserAccountRequest;
use App\Models\Athlete;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Parents;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserCertification;
use App\Support\ActivityLogger;
use App\Support\Profile\ProfilePageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileAccessController extends Controller
{
    use FormatsMvpData;

    public function __construct(
        private readonly ProfilePageData $profilePageData,
    ) {
    }

    public function usersIndex(Request $request): Response
    {
        $viewer = $request->user();
        $isParent = (bool) $viewer?->isParent();
        $canViewSensitiveIdentifiers = (bool) ($viewer?->isAdmin() || $isParent);
        $parentScopedAthleteIds = null;

        if ($isParent) {
            $children = $viewer->children()->pluck('athletes.athlete_id');
            $activeChildId = $request->session()->get('active_child_id');

            $parentScopedAthleteIds = $activeChildId
                ? $children->filter(fn ($id) => (int) $id === (int) $activeChildId)->values()
                : $children->values();
        }

        $athletes = Athlete::query()
            ->with(['user:id,name,email', 'branch:branch_id,branch_name', 'group:group_id,group_name', 'parent.user:id,name'])
            ->when($parentScopedAthleteIds !== null, fn ($query) => $query->whereIn('athlete_id', $parentScopedAthleteIds))
            ->latest('athlete_id')
            ->get();

        $athleteUsers = User::query()
            ->with(['roleAssignments', 'athleteProfile.branch:branch_id,branch_name', 'athleteProfile.group:group_id,group_name', 'athleteProfile.parent.user:id,name'])
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn (User $user) => $user->hasRole('athlete'))
            ->when($parentScopedAthleteIds !== null, fn ($users) => $users->filter(function (User $user) use ($parentScopedAthleteIds) {
                return $user->athleteProfile
                    && $parentScopedAthleteIds->contains(fn ($id) => (int) $id === (int) $user->athleteProfile->athlete_id);
            }))
            ->values();

        return Inertia::render('profiles/ProfileRosterPage', [
            'metrics' => [
                [
                    'label' => $isParent ? 'Linked child records' : 'Active athlete records',
                    'value' => (string) $athletes->count(),
                    'detail' => $isParent ? 'Athlete profiles linked to your parent account' : $athletes->whereNull('deleted_at')->count().' active profiles in the roster',
                    'tone' => 'success',
                ],
                [
                    'label' => 'Athletes without parent links',
                    'value' => (string) $athletes->whereNull('parent_id')->count(),
                    'detail' => 'Optional parent connections not set',
                    'tone' => 'info',
                ],
                [
                    'label' => 'Branches represented',
                    'value' => (string) $athletes->pluck('branch_id')->filter()->unique()->count(),
                    'detail' => 'Current roster spread across active branches',
                    'tone' => 'info',
                ],
            ],
            'rows' => $athleteUsers->map(function (User $user) use ($canViewSensitiveIdentifiers) {
                $athlete = $user->athleteProfile;

                return [
                    'id' => 'USR-'.$user->id,
                    'user_id' => $user->id,
                    'athlete_id' => $athlete?->athlete_id,
                    'athlete' => $user->name ?? 'Unknown athlete',
                    'account_email' => $user->email ?? '-',
                    'parent' => $athlete?->parent?->user?->name ?? 'Not linked',
                    'branch' => $athlete?->branch?->branch_name ?? 'Unassigned',
                    'group' => $athlete?->group?->group_name ?? 'Unassigned',
                    'height_cm' => $athlete?->height_cm !== null ? number_format((float) $athlete->height_cm, 1).' cm' : '-',
                    'weight_kg' => $athlete?->weight_kg !== null ? number_format((float) $athlete->weight_kg, 1).' kg' : '-',
                    'nik' => $canViewSensitiveIdentifiers ? ($athlete?->displayValue('nik') ?? 'Not stored') : null,
                    'bpjs' => $canViewSensitiveIdentifiers ? ($athlete?->displayValue('bpjs') ?? 'Not stored') : null,
                    'geup' => str_replace('_', ' ', $athlete?->geup ?? 'GEUP_10'),
                    'status' => $athlete ? $this->badge('Active', 'success') : $this->badge('Profile incomplete', 'warning'),
                ];
            })->values(),
            'coachRows' => $isParent ? [] : User::query()
                ->with(['coachProfile'])
                ->whereNull('deleted_at')
                ->get()
                ->filter(fn (User $user) => $user->hasRole('coach'))
                ->map(function (User $user) {
                    $coach = $user->coachProfile;

                    return [
                        'id' => $user->id,
                        'user_id' => $user->id,
                        'coach_id' => $coach?->coach_id,
                        'name' => $user->name ?? 'Unknown coach',
                        'email' => $user->email ?? '-',
                        'role' => 'Coach',
                        'status' => $this->badge('Active', 'success'),
                        'specialization' => $coach?->specialization ?? $coach?->license_type ?? '-',
                    ];
                })
                ->values(),
            'parentRows' => $isParent ? [] : User::query()
                ->with(['parentProfile.athletes.user:id,name'])
                ->whereNull('deleted_at')
                ->get()
                ->filter(fn (User $user) => $user->hasRole('parent'))
                ->map(function (User $user) {
                    $parent = $user->parentProfile;

                    return [
                        'id' => $user->id,
                        'user_id' => $user->id,
                        'parent_id' => $parent?->parent_id,
                        'name' => $user->name ?? 'Unknown parent',
                        'email' => $user->email ?? '-',
                        'role' => 'Parent',
                        'relation' => $parent?->relation ?? 'Guardian',
                        'occupation' => $parent?->occupation ?? '-',
                        'children' => $parent?->athletes?->map(fn (Athlete $athlete) => $athlete->user?->name ?? 'Unknown athlete')->filter()->implode(', ') ?: '-',
                        'child_ids' => $parent?->athletes?->pluck('athlete_id')->map(fn ($id) => (string) $id)->implode(',') ?? '',
                    ];
                })
                ->values(),
            'branches' => Branch::query()->orderBy('branch_name')->get(['branch_id as value', 'branch_name as label']),
            'groups' => Group::query()->orderBy('group_name')->get(['group_id as value', 'group_name as label']),
            'athletes' => $athletes
                ->map(fn (Athlete $athlete) => [
                    'value' => $athlete->athlete_id,
                    'label' => $athlete->user?->name ?? 'Unknown athlete',
                ])
                ->values(),
            'parents' => $isParent ? [] : Parents::query()
                ->with('user:id,name')
                ->orderBy('parent_id')
                ->get()
                ->map(fn (Parents $parent) => [
                    'value' => $parent->parent_id,
                    'label' => $parent->user?->name ?? 'Unknown parent',
                ])
                ->values(),
            'canViewSensitiveIdentifiers' => $canViewSensitiveIdentifiers,
        ]);
    }

    private function documentFileRules(): array
        {
            return ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'];
        }

    public function show(Request $request, User $user): Response
    {
        $this->authorizeProfileAccess($request, $user);

        $viewer = $request->user();
        $isLinkedParent = (bool) ($viewer?->isParent() && $this->parentOwnsAthleteUser($viewer, $user));

        $this->profilePageData->loadUser($user);

        return Inertia::render('profiles/ProfileDetailsPage', [
            'user' => $this->profilePageData->user($user),
            'context' => 'admin',
            'canEditAccount' => true,
            'canEditRoleProfiles' => true,
            'accountUpdateUrl' => '/users/'.$user->id.'/account',
            'profileUpdateUrl' => '/users/'.$user->id.'/profile',
            'certificationStoreUrl' => '/users/'.$user->id.'/certifications',
            'achievementStoreUrl' => '/users/'.$user->id.'/achievements',
            'passwordUpdateUrl' => $isLinkedParent ? '/users/'.$user->id.'/password' : null,
            'branches' => $this->profilePageData->branchOptions(),
            'groups' => $this->profilePageData->groupOptions(),
        ]);
    }

    public function updateAccount(UpdateUserAccountRequest $request, User $user, UpdateUserAccount $updateUserAccount): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);

        $updateUserAccount->handle($user, $request->validated());

        ActivityLogger::log($request, 'profile.account.updated', 'profile', 'Updated accessible user account', $user, ['user_id' => $user->id]);

        return back();
    }

    public function updateAccountProfile(UpdateAccountProfileRequest $request, User $user, UpdateAccountProfile $updateAccountProfile): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);

        $updateAccountProfile->handle($user, $request->validated(), $request);

        ActivityLogger::log($request, 'profile.details.updated', 'profile', 'Updated accessible user profile details', $user, ['user_id' => $user->id]);

        return back();
    }

    public function updateAthleteProfile(UpdateAthleteProfileRequest $request, User $user, UpdateAthleteProfile $updateAthleteProfile): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);
        abort_unless($user->hasRole('athlete'), 404);

        $updateAthleteProfile->handle($user, $request->validated());

        ActivityLogger::log($request, 'profile.athlete.updated', 'profile', 'Updated accessible athlete profile', $user, ['user_id' => $user->id]);

        return back();
    }

    public function storeUserCertification(SaveUserCertificationRequest $request, User $user, SaveUserCertification $saveUserCertification): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);

        $validated = $request->validate([
            'cert_type' => ['required', Rule::in(['BELT', 'REFEREE', 'TRAINER'])],
            'title' => ['required', 'string', 'max:120'],
            'issuer' => ['nullable', 'string', 'max:120'],
            'certified_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'file' => $this->documentFileRules(),
        ]);

        $userFile = $this->storeUserFileFromRequest($request, $user, 'CERTIFICATE');
        $payload = collect($validated)->except('file')->all();

        if (Schema::hasColumn('user_certifications', 'user_file_id')) {
            $payload['user_file_id'] = $userFile?->id;
        }

        $user->certifications()->create($payload);

        return back();
    }

    public function updateUserCertification(SaveUserCertificationRequest $request, User $user, UserCertification $certification, SaveUserCertification $saveUserCertification): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);
        abort_unless((int) $certification->user_id === (int) $user->id, 404);

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
            $payload['user_file_id'] = $this->storeUserFileFromRequest($request, $user, 'CERTIFICATE')?->id;
        }

        $certification->update($payload);

        return back();
    }

    public function storeUserAchievement(SaveUserAchievementRequest $request, User $user, SaveUserAchievement $saveUserAchievement): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);

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

        $userFile = $this->storeUserFileFromRequest($request, $user, 'EVENT_DOCUMENT');
        $payload = collect($validated)->except('file')->all() + ['is_auto_recorded' => false];

        if (Schema::hasColumn('user_achievements', 'user_file_id')) {
            $payload['user_file_id'] = $userFile?->id;
        }

        $user->achievements()->create($payload);

        return back();
    }

    public function updateUserAchievement(SaveUserAchievementRequest $request, User $user, UserAchievement $achievement, SaveUserAchievement $saveUserAchievement): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);
        abort_unless((int) $achievement->user_id === (int) $user->id, 404);

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
            $payload['user_file_id'] = $this->storeUserFileFromRequest($request, $user, 'EVENT_DOCUMENT')?->id;
        }

        $achievement->update($payload);

        return back();
    }

    private function authorizeProfileAccess(Request $request, User $user): void
    {
        $viewer = $request->user();

        if ($viewer?->isAdmin()) {
            return;
        }

        if ($viewer?->isParent() && $this->parentOwnsAthleteUser($viewer, $user)) {
            return;
        }

        abort(403);
    }

    private function parentOwnsAthleteUser(User $parentUser, User $childUser): bool
    {
        $athlete = $childUser->athleteProfile;

        if (! $athlete) {
            return false;
        }

        return $parentUser->children()
            ->where('athletes.athlete_id', $athlete->athlete_id)
            ->exists();
    }

    private function storeUserFileFromRequest(Request $request, User $user, string $fileType): ?UserFile
    {
        if (! $request->hasFile('file')) {
            return null;
        }

        $file = $request->file('file');
        $path = $file->store('user-files', 'public');

        return UserFile::query()->create([
            'user_id' => $user->id,
            'file_type' => $fileType,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
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
