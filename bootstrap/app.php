<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 🌐 Global CORS - must be first
        $middleware->prepend(\App\Http\Middleware\GlobalCorsMiddleware::class);

        // Built-in Laravel CORS (handles preflight)
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        // Disable CSRF for API routes (development)
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Sanctum for SPA authentication
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Return 401 JSON instead of redirecting to login
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return null;
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();