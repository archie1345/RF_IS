<?php

namespace App\Support\Profile;

use App\Models\Branch;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ProfilePageData
{
    public function relations(): array
    {
        return [
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
        ];
    }

    public function loadUser(User $user): User
    {
        return $user->load($this->relations());
    }

    public function branchOptions()
    {
        return Branch::query()
            ->orderBy('branch_name')
            ->get(['branch_id as value', 'branch_name as label']);
    }

    public function groupOptions()
    {
        return Group::query()
            ->orderBy('group_name')
            ->get(['group_id as value', 'group_name as label']);
    }

    public function user(User $user): array
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
                'is_auto_recorded' => (bool) $achievement->is_auto_recorded,
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
