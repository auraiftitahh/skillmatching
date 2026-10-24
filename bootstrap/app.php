<?php

use App\Http\Middleware\EnsureAccountEligible;
use App\Http\Middleware\EnsureInstitutionActive;
use App\Http\Middleware\EnsureWorkspaceRole;
use App\Support\AccountDestination;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => AccountDestination::forUser($request->user()));
        $middleware->alias([
            'account.eligible' => EnsureAccountEligible::class,
            'workspace.role' => EnsureWorkspaceRole::class,
            'institution.active' => EnsureInstitutionActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidSignatureException $exception, Request $request) {
            if ($request->routeIs('verification.verify') && ! $request->expectsJson()) {
                return response()->view('auth.verification-invalid', [], 403);
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
