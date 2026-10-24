<?php

namespace App\Http\Requests\Auth;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ResetAccountPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => [
                'required', 'string', 'confirmed', Password::min(12),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('Kata sandi terlalu panjang. Gunakan kata sandi yang lebih singkat.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Isi alamat email akun Anda.',
            'email.email' => 'Gunakan format email yang valid.',
            'email.max' => 'Email maksimal 190 karakter.',
            'token.required' => 'Tautan pemulihan tidak lengkap. Minta tautan baru.',
            'token.max' => 'Tautan pemulihan tidak valid. Minta tautan baru.',
            'password.required' => 'Isi kata sandi baru Anda.',
            'password.min' => 'Kata sandi minimal 12 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi belum sama.',
        ];
    }
}
