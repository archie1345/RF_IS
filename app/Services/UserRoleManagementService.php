<?php

namespace App\Services;

use App\Models\Coach;
use App\Models\ParentProfile;
use App\Models\User;
use App\Models\UserRoleAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

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
                'role' => $this->defaultRole($roles),
                ...(Schema::hasColumn('users', 'account_status') ? ['account_status' => $data['status']] : []),
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
                'role' => $this->defaultRole($roles),
                ...(! empty($data['password']) ? ['password' => Hash::make($data['password'])] : []),
                ...(Schema::hasColumn('users', 'account_status') ? ['account_status' => $data['status']] : []),
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

        $this->syncParentProfile($user, in_array('parent', $roles, true));
        $this->syncCoachProfile($user, in_array('coach', $roles, true));

        $user->unsetRelation('roleAssignments');
        $user->unsetRelation('parentProfile');
        $user->unsetRelation('coachProfile');
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
        return collect($roles)
            ->map(fn ($role) => strtolower(trim((string) $role)))
            ->filter(fn (string $role) => in_array($role, ['admin', 'coach', 'parent', 'athlete'], true))
            ->unique()
            ->values()
            ->all();
    }

    private function defaultRole(array $roles): string
    {
        foreach (['admin', 'coach', 'parent', 'athlete'] as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return 'athlete';
    }
}
