<?php

namespace App\Services;

use App\Models\User;
use App\Support\RoleResolver;
use Illuminate\Http\Request;

class ActiveRoleContextService
{
    public const SESSION_KEY = 'active_role';

    public function __construct(private readonly RoleResolver $roleResolver) {}

    public function availableRoles(?User $user): array
    {
        return $this->roleResolver->rolesFor($user);
    }

    public function activeRole(Request $request, ?User $user = null): string
    {
        $user ??= $request->user();
        $availableRoles = $this->availableRoles($user);

        if ($availableRoles === []) {
            return 'athlete';
        }

        $storedRole = strtolower(trim((string) $request->session()->get(self::SESSION_KEY, '')));
        if (in_array($storedRole, $availableRoles, true)) {
            return $storedRole;
        }

        $primaryRole = $this->roleResolver->primaryRoleFor($user, $availableRoles[0]);
        $activeRole = in_array($primaryRole, $availableRoles, true)
            ? $primaryRole
            : $availableRoles[0];

        $request->session()->put(self::SESSION_KEY, $activeRole);

        return $activeRole;
    }

    public function switchRole(Request $request, string $role): string
    {
        $normalizedRole = strtolower(trim($role));
        $availableRoles = $this->availableRoles($request->user());

        abort_unless(in_array($normalizedRole, $availableRoles, true), 403);

        $request->session()->put(self::SESSION_KEY, $normalizedRole);

        return $normalizedRole;
    }

    public function isActive(Request $request, string $role): bool
    {
        return $this->activeRole($request) === strtolower(trim($role));
    }
}
