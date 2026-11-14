<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpFoundation\Request;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => \App\Http\Middleware\EnsureRole::class, 'active' => \App\Http\Middleware\EnsureActive::class]);
        // Trusted proxies: ngrok and reverse proxies. Set APP_PROXIES to known proxies in production.
        $middleware->trustProxies(at: env('APP_PROXIES', '*'), headers:
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})
    ->create();
