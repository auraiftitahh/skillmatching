<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetAccountPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordRecoveryController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:190']], [
            'email.required' => 'Isi alamat email akun Anda.',
            'email.email' => 'Gunakan format email yang valid.',
            'email.max' => 'Email maksimal 190 karakter.',
        ]);
        $user = User::whereRaw('LOWER(email) = ?', [$data['email']])->first();
        try {
            Password::sendResetLink(['email' => $user?->email ?? $data['email']]);
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Pengiriman email pemulihan belum berhasil.');
        }

        return redirect()->route('password.request')->withInput($request->only('email'))
            ->with('status', 'Jika email terdaftar, tautan pemulihan akan dikirim. Periksa folder spam dan tunggu setidaknya 60 detik sebelum meminta ulang.');
    }

    public function resetForm(Request $request, string $token): Response
    {
        $email = $request->query('email');
        if (! is_string($email) || strlen($email) > 190 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($token) > 255) {
            return $this->invalidLink(410);
        }
        $user = User::whereRaw('LOWER(email) = ?', [Str::lower(trim($email))])->first();
        if (! $user || ! Password::broker()->tokenExists($user, $token)) {
            return $this->invalidLink(410);
        }

        return response()->view('auth.reset-password', ['token' => $token, 'email' => $user->email])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function reset(ResetAccountPasswordRequest $request): RedirectResponse|Response
    {
        $data = $request->validated();
        $storedEmail = User::whereRaw('LOWER(email) = ?', [$data['email']])->value('email');
        $data['email'] = $storedEmail ?? $data['email'];
        $status = DB::transaction(function () use ($data): string {
            $table = config('auth.passwords.'.config('auth.defaults.passwords').'.table');
            DB::table($table)->where('email', $data['email'])->lockForUpdate()->first();

            return Password::reset($data, function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            });
        });
        if ($status !== Password::PASSWORD_RESET) {
            return $this->invalidLink(422);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Kata sandi berhasil diperbarui. Silakan masuk dengan kata sandi baru.');
    }

    private function invalidLink(int $status): Response
    {
        return response()->view('auth.reset-password-invalid', [], $status)
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }
}
