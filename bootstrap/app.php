<?php

use App\Http\Middleware\SetLocaleFromRequest;
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
        // TLS is terminated at the host's edge and forwarded on as plain HTTP.
        // Without trusting the proxy, every asset() and url() emits http:// on
        // an https:// page and the browser blocks the lot as mixed content —
        // which strips the dashboard of its stylesheets and breaks image URLs.
        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            SetLocaleFromRequest::class,
        ]);

        // The only login in this app is Filament's. Without this, any guarded web
        // route throws "Route [login] not defined" instead of redirecting.
        $middleware->redirectGuestsTo(fn () => '/admin/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
