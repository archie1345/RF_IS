<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pure authorization gate: the request passes when the account holds at least
 * one of the listed roles. It never reads or mutates session state.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user, 401);

        $allowed = collect($roles)
            ->map(fn (string $role): string => strtolower(trim($role)))
            ->filter()
            ->contains(fn (string $role): bool => $user->hasRole($role));

        abort_unless($allowed, 403);

        return $next($request);
    }
}
