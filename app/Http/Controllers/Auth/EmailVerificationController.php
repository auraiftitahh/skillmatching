<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailVerificationController extends Controller
{
    public function notice(): View
    {
        return view('auth.verify-email');
    }

    public function resend(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:190']], [
            'email.required' => 'Isi alamat email akun Anda.',
            'email.email' => 'Gunakan format email yang valid.',
        ]);
        $user = User::whereRaw('LOWER(email) = ?', [$data['email']])->first();
        $key = self::rateLimitKey($data['email']);
        if ($user && ! $user->hasVerifiedEmail() && ! RateLimiter::tooManyAttempts($key, 1)) {
            RateLimiter::hit($key, 60);
            try {
                $user->sendEmailVerificationNotification();
            } catch (TransportExceptionInterface $exception) {
                Log::warning('Pengiriman ulang email verifikasi belum berhasil.', ['user_id' => $user->id]);
            }
        }

        return redirect()->route('verification.notice')
            ->with('registration_email', $data['email'])
            ->with('status', 'Jika akun tersebut masih memerlukan verifikasi, tautan akan dikirim ke email Anda. Periksa folder spam dan tunggu setidaknya 60 detik sebelum mencoba kembali.');
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $request->session()->put('verified_account_id', $user->id);

        return redirect()->route('verification.complete');
    }

    public function complete(Request $request): View|RedirectResponse
    {
        $user = User::find($request->session()->get('verified_account_id'));
        if (! $user || ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return view('auth.verification-complete', ['isInstitution' => $user->role === User::ROLE_INSTITUTION]);
    }

    public static function rateLimitKey(string $email): string
    {
        return 'verification-email:'.hash('sha256', Str::lower($email));
    }
}
