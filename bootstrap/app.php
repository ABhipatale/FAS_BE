<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CORS is handled by the framework's own Illuminate\Http\Middleware\HandleCors,
        // which Laravel 11+ already includes in the global stack and which reads
        // config/cors.php.
        //
        // The old App\Http\Middleware\CorsMiddleware used to be appended here. It
        // overwrote every response with `Access-Control-Allow-Origin: *` together
        // with `Access-Control-Allow-Credentials: true` — a combination browsers
        // reject outright — and it shadowed config/cors.php entirely, so the
        // allowed_origins list could never take effect.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Anything under /api must answer with JSON, never an HTML error page or
        // a redirect to the `login` route. Without this an expired token made
        // Laravel redirect to GET /api/login -> 405 -> HTML, which every fetch
        // in the SPA then failed to parse ("Unexpected token '<'").
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();
