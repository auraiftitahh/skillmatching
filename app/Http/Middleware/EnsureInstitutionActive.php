<?php

namespace App\Http\Middleware;

use App\Models\CertificationBody;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstitutionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user->role === User::ROLE_INSTITUTION && $user->certificationBody?->status !== CertificationBody::STATUS_ACTIVE) {
            if ($request->expectsJson()) {
                abort(403, 'Akun lembaga belum memenuhi syarat akses operasional.');
            }

            return redirect()->route('institution.status');
        }

        return $next($request);
    }
}
