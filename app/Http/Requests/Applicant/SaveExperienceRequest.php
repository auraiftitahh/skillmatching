<?php

namespace App\Http\Requests\Applicant;

use App\Models\User;
use App\Models\UserExperience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === User::ROLE_APPLICANT;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('is_current')) {
            $this->merge(['is_current' => false]);
        }
    }

    public function rules(): array
    {
        $today = now('Asia/Jakarta')->toDateString();

        return [
            'type' => ['required', Rule::in(array_keys(UserExperience::TYPES))],
            'organization' => ['required', 'string', 'max:200'],
            'title' => ['required', 'string', 'max:200'],
            'start_date' => [Rule::requiredIf(! $this->boolean('is_current') && $this->filled('end_date')), 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'end_date' => [Rule::excludeIf($this->boolean('is_current')), 'nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:'.$today],
            'is_current' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Pilih jenis riwayat.', 'type.in' => 'Jenis riwayat tidak tersedia.',
            'organization.required' => 'Isi nama perusahaan, institusi, atau organisasi.',
            'organization.string' => 'Nama instansi harus berupa teks.', 'organization.max' => 'Nama instansi maksimal 200 karakter.',
            'title.required' => 'Isi posisi, program studi, atau peran Anda.', 'title.string' => 'Posisi atau peran harus berupa teks.', 'title.max' => 'Posisi atau peran maksimal 200 karakter.',
            'start_date.required' => 'Isi tanggal mulai jika tanggal selesai sudah diisi.',
            'start_date.date_format' => 'Gunakan tanggal mulai yang valid.', 'start_date.before_or_equal' => 'Tanggal mulai tidak boleh di masa depan.',
            'end_date.date_format' => 'Gunakan tanggal selesai yang valid.', 'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.', 'end_date.before_or_equal' => 'Tanggal selesai tidak boleh di masa depan.',
            'is_current.boolean' => 'Pilihan masih berlangsung tidak valid.',
            'description.string' => 'Uraian harus berupa teks.', 'description.max' => 'Uraian maksimal 2.000 karakter.',
        ];
    }
}
