<?php

namespace App\Services;

use App\Models\Athlete;
use App\Models\Coach;
use App\Models\ParentProfile;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Support\RoleResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserRoleManagementService
{
    public function createAccount(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $roles = $this->normalizeRoles($data['roles']);
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password'] ?? str()->random(40)),
                'gender' => $data['gender'] ?? 'MALE',
                'role' => $roles[0],
                'account_status' => $data['status'],
            ]);

            $this->syncRoles($user, $roles);

            return $user->fresh(['roleAssignments', 'athleteProfile', 'coachProfile', 'parentProfile']);
        });
    }

    public function updateAccount(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $roles = $this->normalizeRoles($data['roles']);
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $roles[0],
                ...(! empty($data['password']) ? ['password' => Hash::make($data['password'])] : []),
                'account_status' => $data['status'],
            ]);

            $this->syncRoles($user, $roles);

            return $user->fresh(['roleAssignments', 'athleteProfile', 'coachProfile', 'parentProfile']);
        });
    }

    private function syncRoles(User $user, array $roles): void
    {
        UserRoleAssignment::query()
            ->where('user_id', $user->id)
            ->whereNotIn('role', $roles)
            ->delete();

        foreach ($roles as $role) {
            UserRoleAssignment::query()->firstOrCreate([
                'user_id' => $user->id,
                'role' => $role,
            ]);
        }

        $this->syncAthleteProfile($user, in_array('athlete', $roles, true));
        $this->syncParentProfile($user, in_array('parent', $roles, true));
        $this->syncCoachProfile($user, in_array('coach', $roles, true));

        $user->unsetRelation('roleAssignments');
        $user->unsetRelation('athleteProfile');
        $user->unsetRelation('parentProfile');
        $user->unsetRelation('coachProfile');
    }

    private function syncAthleteProfile(User $user, bool $enabled): void
    {
        $profile = Athlete::withTrashed()
            ->where('id', $user->id)
            ->first();

        if ($enabled) {
            if ($profile?->trashed()) {
                $profile->restore();
            }

            return;
        }

        if ($profile && ! $profile->trashed()) {
            $profile->delete();
        }
    }

    private function syncParentProfile(User $user, bool $enabled): void
    {
        $profile = ParentProfile::withTrashed()
            ->where('id', $user->id)
            ->first();

        if ($enabled) {
            if (! $profile) {
                ParentProfile::query()->create([
                    'id' => $user->id,
                    'relation' => 'guardian',
                ]);

                return;
            }

            if ($profile->trashed()) {
                $profile->restore();
            }

            return;
        }

        if ($profile && ! $profile->trashed()) {
            $profile->delete();
        }
    }

    private function syncCoachProfile(User $user, bool $enabled): void
    {
        $profile = Coach::withTrashed()
            ->where('id', $user->id)
            ->first();

        if ($enabled) {
            if (! $profile) {
                Coach::query()->create([
                    'id' => $user->id,
                    'status' => 'active',
                ]);

                return;
            }

            if ($profile->trashed()) {
                $profile->restore();
            }

            return;
        }

        if ($profile && ! $profile->trashed()) {
            $profile->delete();
        }
    }

    private function normalizeRoles(array $roles): array
    {
        $normalized = collect($roles)
            ->map(fn ($role) => strtolower(trim((string) $role)))
            ->filter(fn (string $role) => in_array($role, RoleResolver::ROLES, true))
            ->unique()
            ->sortBy(fn (string $role): int => array_search($role, RoleResolver::ROLES, true))
            ->values()
            ->all();

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'roles' => 'Assign at least one supported role.',
            ]);
        }

        return $normalized;
    }
}
