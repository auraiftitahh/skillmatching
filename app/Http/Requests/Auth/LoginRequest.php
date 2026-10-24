<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Isi alamat email Anda.',
            'email.email' => 'Gunakan format email yang valid.',
            'email.max' => 'Email maksimal 190 karakter.',
            'password.required' => 'Isi kata sandi Anda.',
            'password.max' => 'Kata sandi terlalu panjang.',
        ];
    }
}
