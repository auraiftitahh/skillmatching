<?php

namespace App\Http\Requests\Applicant;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === User::ROLE_APPLICANT;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^(?=.*[0-9])[0-9+() .-]{6,40}$/D'],
            'domicile' => ['nullable', 'string', 'max:150'],
            'employment_status' => ['nullable', Rule::in(array_keys(User::EMPLOYMENT_STATUSES))],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Isi nama lengkap Anda.',
            'name.string' => 'Nama harus berupa teks.',
            'name.max' => 'Nama maksimal 150 karakter.',
            'phone.string' => 'Nomor telepon harus berupa teks.',
            'phone.max' => 'Nomor telepon maksimal 40 karakter.',
            'phone.regex' => 'Gunakan nomor telepon 6–40 karakter berisi angka dan tanda telepon yang sesuai.',
            'domicile.string' => 'Domisili harus berupa teks.',
            'domicile.max' => 'Domisili maksimal 150 karakter.',
            'employment_status.in' => 'Pilih status pekerjaan yang tersedia.',
        ];
    }
}
