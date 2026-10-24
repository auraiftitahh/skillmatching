<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\WorkspaceNavigation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountEligible
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::find($request->user()->getAuthIdentifier());
        $message = null;
        if (! $user || ! isset(WorkspaceNavigation::PAGES[$user->role])) {
            $message = 'Sesi Anda tidak lagi berlaku. Silakan masuk kembali.';
        } elseif (! $user->is_active) {
            $message = 'Akun Anda tidak aktif. Hubungi Admin untuk informasi lebih lanjut.';
        } elseif (! $user->hasVerifiedEmail()) {
            $message = 'Verifikasi email terlebih dahulu sebelum masuk.';
        }

        if ($message !== null) {
            $needsVerification = $user && $user->is_active && ! $user->hasVerifiedEmail();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($needsVerification) {
                return redirect()->route('verification.notice')->with('registration_email', $user->email)->with('status', $message);
            }

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        Auth::setUser($user);

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
