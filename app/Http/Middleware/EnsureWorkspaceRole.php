<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceRole
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $expectedRole = $role ?? $request->route('role');
        abort_unless($request->user()->role === $expectedRole, 403);

        return $next($request);
    }
}
