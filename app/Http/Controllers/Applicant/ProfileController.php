<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\UpdateProfileRequest;
use App\Support\WorkspaceNavigation;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('applicant.profile', [
            'user' => $request->user(), 'pageTitle' => 'Profil Saya', 'roleLabel' => 'Pelamar',
            'navigation' => WorkspaceNavigation::items('pelamar', 'profil'),
            'homeUrl' => route('workspace', ['role' => 'pelamar', 'page' => 'dashboard']),
            'userName' => $request->user()->name,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        foreach (['phone', 'domicile', 'employment_status'] as $field) {
            $data[$field] = $data[$field] ?? null;
        }
        $request->user()->update($data);

        return redirect()->route('applicant.profile.edit')->with('success', 'Profil Anda berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', PasswordRule::min(12),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('Kata sandi terlalu panjang.');
                    }
                },
            ],
        ], [
            'current_password.required' => 'Isi kata sandi saat ini.',
            'password.required' => 'Isi kata sandi baru.',
            'password.confirmed' => 'Konfirmasi kata sandi belum sama.',
            'password.different' => 'Gunakan kata sandi yang berbeda.',
            'password.min' => 'Kata sandi minimal 12 karakter.',
        ]);

        $user = $request->user();
        DB::transaction(function () use ($data, $user): void {
            $account = $user->newQuery()->lockForUpdate()->findOrFail($user->getKey());
            if (! Hash::check($data['current_password'], $account->password)) {
                throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini salah.']);
            }

            $account->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
            Password::broker()->deleteToken($account);
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Kata sandi berhasil diubah. Masuk kembali dengan kata sandi baru.');
    }
}
