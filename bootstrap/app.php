<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Sesi/CSRF kedaluwarsa pada request Inertia → arahkan bersih ke login,
        // hindari error "PATCH /login (405)" saat sesi habis lalu menyimpan.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->header('X-Inertia')) {
                return $response;
            }

            if ($e instanceof AuthenticationException || $response->getStatusCode() === 401) {
                return Inertia::location(route('login'));
            }

            if ($response->getStatusCode() === 419) {
                return back()->with('error', 'Sesi kedaluwarsa, halaman dimuat ulang. Silakan coba simpan lagi.');
            }

            return $response;
        });
    })->create();
