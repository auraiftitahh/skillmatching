<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'bail', 'required', 'string', 'email', 'max:190',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (User::whereRaw('LOWER(email) = ?', [$value])->exists()) {
                        $fail('Alamat email sudah digunakan. Gunakan email lain.');
                    }
                },
            ],
            'role' => ['required', Rule::in([User::ROLE_APPLICANT, User::ROLE_INSTITUTION])],
            'password' => [
                'required', 'string', 'confirmed', Password::min(12),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && strlen($value) > 72) {
                        $fail('Kata sandi terlalu panjang. Gunakan kata sandi yang lebih singkat.');
                    }
                },
            ],
            'body_name' => [Rule::excludeIf($this->input('role') !== User::ROLE_INSTITUTION), 'required', 'string', 'max:190'],
            'contact' => [Rule::excludeIf($this->input('role') !== User::ROLE_INSTITUTION), 'nullable', 'string', 'max:190'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Isi nama lengkap Anda.',
            'name.max' => 'Nama maksimal 150 karakter.',
            'email.required' => 'Isi alamat email Anda.',
            'email.email' => 'Gunakan format email yang valid.',
            'email.max' => 'Email maksimal 190 karakter.',
            'role.required' => 'Pilih jenis akun.',
            'role.in' => 'Jenis akun yang dipilih tidak tersedia.',
            'password.required' => 'Isi kata sandi Anda.',
            'password.min' => 'Kata sandi minimal 12 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi belum sama.',
            'body_name.required' => 'Isi nama Badan Sertifikasi.',
            'body_name.max' => 'Nama lembaga maksimal 190 karakter.',
            'contact.max' => 'Kontak maksimal 190 karakter.',
        ];
    }
}
