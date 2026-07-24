<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isActiveAccount()) {
            return $next($request);
        }

        $message = $user->isSuspended()
            ? 'This account has been suspended. Please contact an administrator.'
            : 'Please accept your invitation before signing in.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return new JsonResponse(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        $this->invalidateWebSession($request);

        return redirect()
            ->route('login')
            ->withErrors(['email' => $message]);
    }

    private function invalidateWebSession(Request $request): void
    {
        Auth::logout();

        if (! $request->hasSession()) {
            return;
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
