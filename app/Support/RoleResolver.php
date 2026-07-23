<?php

namespace App\Support;

use App\Models\User;
use App\Services\ActiveRoleContextService;
use Illuminate\Support\Collection;

class RoleResolver
{
    public const ROLES = ['admin', 'coach', 'parent', 'athlete'];

    public function rolesFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $assignments = $this->assignmentRoles($user);

        if ($assignments->isNotEmpty()) {
            return $assignments->all();
        }

        return $this->normalizeRole($user->role)
            ? [$this->normalizeRole($user->role)]
            : [];
    }

    public function primaryRoleFor(?User $user, string $default = 'athlete'): string
    {
        $roles = $this->rolesFor($user);

        if ($this->requestCanProvideRoleContext($user)) {
            $storedRole = $this->normalizeRole(
                request()->session()->get(ActiveRoleContextService::SESSION_KEY),
            );

            if ($storedRole !== null && in_array($storedRole, $roles, true)) {
                return $storedRole;
            }
        }

        return $roles[0] ?? $default;
    }

    public function hasRole(?User $user, string $role): bool
    {
        $normalized = $this->normalizeRole($role);

        return $normalized !== null && in_array($normalized, $this->rolesFor($user), true);
    }

    private function assignmentRoles(User $user): Collection
    {
        if (! $user->relationLoaded('roleAssignments')) {
            $user->load('roleAssignments');
        }

        return $user->roleAssignments
            ->pluck('role')
            ->map(fn ($role) => $this->normalizeRole($role))
            ->filter()
            ->unique()
            ->values();
    }

    private function normalizeRole(?string $role): ?string
    {
        $normalized = strtolower(trim((string) $role));

        return in_array($normalized, self::ROLES, true) ? $normalized : null;
    }

    private function requestCanProvideRoleContext(?User $user): bool
    {
        if (! $user || app()->runningInConsole() || ! app()->bound('request')) {
            return false;
        }

        $request = request();

        return $request->hasSession() && (int) $request->user()?->id === (int) $user->id;
    }
}
