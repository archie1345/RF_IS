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
use App\Services\ParentChildContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileAccessController extends Controller
{
    use FormatsMvpData;

    public function __construct(
        private readonly ProfilePageData $profilePageData,
        private readonly ParentChildContextService $childContext,
    ) {
    }

    public function usersIndex(Request $request): Response
    {
        $viewer = $request->user();
        $isParent = (bool) $viewer?->isParent();
        $canViewSensitiveIdentifiers = (bool) ($viewer?->isAdmin() || $isParent);
        $parentScopedAthleteIds = null;

        if ($isParent) {
            $parentScopedAthleteIds = collect($this->childContext->visibleChildAthleteIds($request));
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
                    && $parentScopedAthleteIds->contains(fn ($id) => (string) $id === (string) $user->athleteProfile->athlete_id);
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

        $saveUserCertification->store($user, $request->validated(), $request);

        return back();
    }

    public function updateUserCertification(SaveUserCertificationRequest $request, User $user, UserCertification $certification, SaveUserCertification $saveUserCertification): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);
        abort_unless((int) $certification->user_id === (int) $user->id, 404);

        $saveUserCertification->update($user, $certification, $request->validated(), $request);

        return back();
    }

    public function storeUserAchievement(SaveUserAchievementRequest $request, User $user, SaveUserAchievement $saveUserAchievement): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);

        $saveUserAchievement->store($user, $request->validated(), $request);

        return back();
    }

    public function updateUserAchievement(SaveUserAchievementRequest $request, User $user, UserAchievement $achievement, SaveUserAchievement $saveUserAchievement): RedirectResponse
    {
        $this->authorizeProfileAccess($request, $user);
        abort_unless((int) $achievement->user_id === (int) $user->id, 404);

        $saveUserAchievement->update($user, $achievement, $request->validated(), $request);

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

}
