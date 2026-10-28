<?php

namespace App\Support\Profile;

use App\Models\Branch;
use App\Models\Group;
use App\Models\User;
use App\Models\UserFile;
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
            ->where('is_active', true)
            ->orderBy('branch_name')
            ->get(['branch_id as value', 'branch_name as label']);
    }

    public function groupOptions()
    {
        return Group::query()
            ->where('is_active', true)
            ->orderBy('group_name')
            ->get(['group_id as value', 'group_name as label', 'branch_id']);
    }

    public function user(User $user): array
    {
        $athlete = $user->athleteProfile;
        $coach = $user->coachProfile;

        return [
            'id' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'gender' => $user->gender,
            'bday' => $user->bday?->format('Y-m-d'),
            'phone' => $user->phone,
            'roles' => $user->assignedRoles(),
            'bio' => $user->profile?->bio,
            'profilePictureUrl' => $user->profile?->profile_picture_path
                ? Storage::url($user->profile->profile_picture_path)
                : null,
            'athleteProfile' => $athlete ? [
                'height_cm' => $athlete->height_cm !== null ? (string) $athlete->height_cm : null,
                'weight_kg' => $athlete->weight_kg !== null ? (string) $athlete->weight_kg : null,
                'geup' => $athlete->geup,
                'nik' => $this->editableSensitiveValue($user->displayNik()),
                'bpjs' => $this->editableSensitiveValue($user->displayBpjs()),
                'phone' => $user->phone,
                'bday' => $user->bday?->format('Y-m-d'),
                'gender' => $user->gender,
                'alamat' => $user->profile?->address ?? $athlete->alamat,
                'branch_id' => $athlete->branch_id !== null ? (string) $athlete->branch_id : null,
                'group_id' => $athlete->group_id !== null ? (string) $athlete->group_id : null,
                'guardian_name' => $athlete->guardian_name,
                'guardian_phone' => $athlete->guardian_phone,
                'guardian_relation' => $athlete->guardian_relation,
                'branch' => $athlete->branch ? [
                    'branch_id' => $athlete->branch->branch_id,
                    'branch_name' => $athlete->branch->branch_name,
                ] : null,
                'group' => $athlete->group ? [
                    'group_id' => $athlete->group->group_id,
                    'group_name' => $athlete->group->group_name,
                ] : null,
            ] : null,
            'coachProfile' => $coach ? [
                'status' => $coach->status,
                'specialization' => $coach->specialization,
                'bio' => $coach->bio,
                'nik' => $this->editableSensitiveValue($coach->displayValue('nik')),
            ] : null,
            'achievements' => $user->achievements->map(fn ($achievement) => [
                'id' => $achievement->id,
                'route_id' => $achievement->uuid,
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
                'fileUrl' => $this->fileUrl($achievement->file),
            ])->values(),
            'certifications' => $user->certifications->map(fn ($certification) => [
                'id' => $certification->id,
                'route_id' => $certification->uuid,
                'cert_type' => $certification->cert_type,
                'title' => $certification->title,
                'issuer' => $certification->issuer,
                'certified_at' => $certification->certified_at?->format('Y-m-d'),
                'expires_at' => $certification->expires_at?->format('Y-m-d'),
                'notes' => $certification->notes,
                'fileName' => $certification->file?->original_name,
                'fileUrl' => $this->fileUrl($certification->file),
            ])->values(),
        ];
    }

    private function editableSensitiveValue(string $value): string
    {
        return in_array($value, ['Not stored', 'Stored as hash only', 'Stored, cannot decrypt'], true)
            ? ''
            : $value;
    }

    private function fileUrl(?UserFile $file): ?string
    {
        return $file ? route('user-files.download', $file) : null;
    }
}
