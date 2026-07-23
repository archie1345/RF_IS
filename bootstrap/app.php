<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'account.active' => EnsureAccountIsActive::class,
        ]);

        $middleware->web(append: [
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
