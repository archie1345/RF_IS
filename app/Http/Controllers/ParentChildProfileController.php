<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Group;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ParentChildProfileController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        $viewer = $request->user();

        if ($viewer?->isAdmin()) {
            return app(ProfileAccessController::class)->show($request, $user);
        }

        abort_unless($viewer?->isParent() && $this->parentOwnsAthleteUser($viewer, $user), 403);

        $user->load([
            'profile',
            'athleteProfile.branch',
            'athleteProfile.group',
            'achievements.file',
            'certifications.file',
            'roleAssignments',
        ]);

        return Inertia::render('parents/ChildProfilePage', [
            'child' => [
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
                    'nik' => $user->athleteProfile->nik_ciphertext,
                    'bpjs' => $user->athleteProfile->bpjs_ciphertext,
                    'phone' => $user->phone,
                    'bday' => $user->bday?->format('Y-m-d'),
                    'gender' => $user->gender,
                    'alamat' => $user->athleteProfile->alamat,
                    'branch_id' => $user->athleteProfile->branch_id,
                    'group_id' => $user->athleteProfile->group_id,
                    'branch' => $user->athleteProfile->branch?->branch_name,
                    'group' => $user->athleteProfile->group?->group_name,
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
                'certifications' => $user->certifications->map(fn ($certification) => [
                    'id' => $certification->id,
                    'cert_type' => $certification->cert_type,
                    'title' => $certification->title,
                    'issuer' => $certification->issuer,
                    'certified_at' => $certification->certified_at?->format('Y-m-d'),
                    'expires_at' => $certification->expires_at?->format('Y-m-d'),
                    'notes' => $certification->notes,
                    'fileName' => $certification->file?->original_name,
                    'fileUrl' => $certification->file?->file_path ? Storage::url($certification->file->file_path) : null,
                ]),
            ],
            'branches' => Branch::query()
                ->orderBy('branch_name')
                ->get(['branch_id as value', 'branch_name as label']),
            'groups' => Group::query()
                ->orderBy('group_name')
                ->get(['group_id as value', 'group_name as label']),
        ]);
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $viewer = $request->user();

        abort_unless($viewer?->isParent() && $this->parentOwnsAthleteUser($viewer, $user), 403);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        ActivityLogger::log($request, 'parent.child.password.updated', 'parent', 'Parent updated linked child password', $user, [
            'child_user_id' => $user->id,
        ]);

        return back();
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
