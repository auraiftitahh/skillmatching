<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\AccountDestination;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class SessionController extends Controller
{
    public function entry(Request $request): RedirectResponse
    {
        return redirect()->to($request->user() ? AccountDestination::forUser($request->user()) : route('login'));
    }

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse|Response
    {
        $credentials = $request->validated();
        $key = 'login:'.hash('sha256', $credentials['email'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->view('auth.login', [
                'emailValue' => $credentials['email'],
                'throttleMessage' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.$seconds.' detik.',
            ], 429)->header('Retry-After', (string) $seconds);
        }

        $storedEmail = User::whereRaw('LOWER(email) = ?', [$credentials['email']])->value('email');
        if (! Auth::attempt(['email' => $storedEmail ?? $credentials['email'], 'password' => $credentials['password']])) {
            RateLimiter::hit($key, 60);

            return redirect()->route('login')->withErrors(['email' => 'Email atau kata sandi tidak sesuai.'])->withInput($request->only('email'));
        }

        RateLimiter::clear($key);
        $user = $request->user();
        if (! $user->is_active) {
            $this->invalidateSession($request);

            return redirect()->route('login')->withErrors(['email' => 'Akun Anda tidak aktif. Hubungi Admin untuk informasi lebih lanjut.']);
        }
        if (! $user->hasVerifiedEmail()) {
            $email = $user->email;
            $this->invalidateSession($request);

            return redirect()->route('verification.notice')->with('registration_email', $email)->with('status', 'Verifikasi email terlebih dahulu sebelum masuk.');
        }

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->to(AccountDestination::forUser($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->invalidateSession($request);

        return redirect()->route('login')->with('status', 'Anda berhasil keluar dari akun.');
    }

    private function invalidateSession(Request $request): void
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
