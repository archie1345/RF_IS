<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NormalizeChampionshipRegistrationInput;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['web', 'auth', 'account.active', 'verified', 'role:admin'])
                ->prefix('admin')
                ->name('admin.')
                ->group(function (): void {
                    require base_path('routes/legacy-billing.php');
                    require base_path('routes/admin-message-templates.php');
                    require base_path('routes/admin-data-export.php');
                });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'account.active' => EnsureAccountIsActive::class,
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->web(append: [
            NormalizeChampionshipRegistrationInput::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO,
        );
    })->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status < 400 || $status > 599) {
                return $response;
            }

            // Keep Laravel's detailed exception page for local server-side debugging.
            if (config('app.debug') && $status >= 500) {
                return $response;
            }

            $errorResponse = Inertia::render('ErrorPage', [
                'status' => $status,
                'statusText' => Response::$statusTexts[$status] ?? null,
            ])->toResponse($request)->setStatusCode($status);

            // Preserve protocol headers that are meaningful for specific error responses.
            foreach (['Allow', 'Retry-After', 'WWW-Authenticate'] as $header) {
                if ($response->headers->has($header)) {
                    $errorResponse->headers->set($header, $response->headers->get($header));
                }
            }

            return $errorResponse;
        });
    })->create();
