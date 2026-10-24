<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === User::ROLE_ADMIN;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['activate', 'deactivate'])],
            'expected_is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Pilih perubahan status akun.',
            'action.in' => 'Perubahan status yang diminta tidak tersedia.',
            'expected_is_active.required' => 'Muat ulang halaman sebelum mengubah status.',
            'expected_is_active.boolean' => 'Status awal tidak valid. Muat ulang halaman.',
            'reason.required' => 'Isi alasan perubahan status.',
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}
