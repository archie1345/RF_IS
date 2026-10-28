<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Single source of truth for role membership.
 *
 * An account may hold several roles. Authorization is evaluated against the
 * UNION of those roles: there is no per-session "active role", so a request
 * can never change what a user is allowed to do.
 */
class RoleResolver
{
    /** Ordered from highest to lowest privilege. */
    public const ROLES = ['admin', 'coach', 'athlete'];

    /** @return array<int, string> assigned roles, highest privilege first */
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

    /** Highest-privilege assigned role. Deterministic: never depends on the request or session. */
    public function primaryRoleFor(?User $user, string $default = 'athlete'): string
    {
        return $this->rolesFor($user)[0] ?? $default;
    }

    /**
     * The role whose page layout/data a screen should present.
     *
     * Pages that have meaningfully different layouts per role (attendance,
     * payments) let the user pick one with a stateless `?mode=` query value.
     * Anything the user does not hold falls back to the primary role.
     */
    public function viewRoleFor(?User $user, ?string $requested = null): string
    {
        $requested = $this->normalizeRole($requested);

        if ($requested !== null && $this->hasRole($user, $requested)) {
            return $requested;
        }

        return $this->primaryRoleFor($user);
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
            ->sortBy(fn (string $role): int => array_search($role, self::ROLES, true))
            ->values();
    }

    private function normalizeRole(?string $role): ?string
    {
        $normalized = strtolower(trim((string) $role));

        return in_array($normalized, self::ROLES, true) ? $normalized : null;
    }
}
