<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user, 401);

        $authorized = collect($roles)
            ->map(fn (string $role): string => strtolower(trim($role)))
            ->filter()
            ->contains(fn (string $role): bool => $user->isActingAs($role));

        abort_unless($authorized, 403);

        return $next($request);
    }
}
