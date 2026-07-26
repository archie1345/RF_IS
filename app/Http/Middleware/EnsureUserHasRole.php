<?php

namespace App\Http\Middleware;

use App\Services\ActiveRoleContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function __construct(private readonly ActiveRoleContextService $activeRoleContext) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user, 401);

        $allowedRoles = collect($roles)
            ->map(fn (string $role): string => strtolower(trim($role)))
            ->filter()
            ->unique()
            ->values();

        $matchingRole = $allowedRoles->first(
            fn (string $role): bool => $user->hasRole($role),
        );

        abort_unless($matchingRole !== null, 403);

        $activeRole = $this->activeRoleContext->activeRole($request, $user);

        if (! $allowedRoles->contains($activeRole)) {
            $this->activeRoleContext->switchRole($request, $matchingRole);
        }

        return $next($request);
    }
}
