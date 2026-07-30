<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeChampionshipRegistrationInput
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->routeIs(
                'championships.registrations.store',
                'championships.registrations.update',
            )
            && ! $request->exists('team_contingent')
        ) {
            $request->merge(['team_contingent' => null]);
        }

        return $next($request);
    }
}
