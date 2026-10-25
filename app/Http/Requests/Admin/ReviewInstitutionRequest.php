<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\InstitutionReviewActions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewInstitutionRequest extends FormRequest
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
            'action' => ['required', Rule::in(array_keys(InstitutionReviewActions::ACTION_LABELS))],
            'expected_status' => ['required', Rule::in(array_keys(InstitutionReviewActions::LABELS))],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Pilih keputusan untuk lembaga.',
            'action.in' => 'Keputusan yang diminta tidak tersedia.',
            'expected_status.required' => 'Muat ulang halaman sebelum mengambil keputusan.',
            'expected_status.in' => 'Status awal tidak valid. Muat ulang halaman.',
            'reason.required' => 'Isi alasan keputusan.',
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.min' => 'Alasan minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}
