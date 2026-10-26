<?php

namespace App\Support;

use App\Models\Skill;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class TrainingSubmissionPayload
{
    public static function validate(array $input, bool $complete = false): array
    {
        $required = $complete ? 'required' : 'nullable';
        $rules = ['title' => 'required|string|max:190', 'description' => $required.'|string|max:5000', 'mode' => [$required, Rule::in(['online', 'offline', 'hybrid'])],
            'duration_hours' => $required.'|integer|min:1|max:10000', 'weekly_hours' => $required.'|integer|min:1|max:168', 'cost' => $required.'|numeric|min:0|max:999999999',
            'source_url' => $required.'|url:http,https|max:1000', 'outcomes' => $required.'|array|'.($complete ? 'min:1|' : '').'max:20',
            'outcomes.*' => 'array:skill_id,new_name,category,description,target_level',
            'outcomes.*.skill_id' => [$required, Rule::in(array_merge(['new'], Skill::where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->all()))],
            'outcomes.*.new_name' => 'nullable|string|max:150', 'outcomes.*.category' => 'nullable|string|max:150', 'outcomes.*.description' => 'nullable|string|max:1500',
            'outcomes.*.target_level' => $required.'|integer|between:1,4'];
        $validator = Validator::make($input, $rules, ['required' => ':attribute wajib diisi.', 'in' => 'Pilihan :attribute tidak tersedia.', 'url' => 'Gunakan tautan http atau https yang valid.'], [
            'title' => 'Judul pelatihan', 'description' => 'Deskripsi', 'mode' => 'Metode', 'duration_hours' => 'Durasi', 'weekly_hours' => 'Waktu belajar per pekan', 'cost' => 'Biaya', 'source_url' => 'Tautan pelatihan', 'outcomes' => 'Capaian keterampilan', 'outcomes.*.skill_id' => 'Keterampilan', 'outcomes.*.target_level' => 'Level capaian']);
        $validator->after(function ($validator) use ($input, $complete): void {
            if (! $complete || $validator->errors()->isNotEmpty()) {
                return;
            }
            $seen = [];
            foreach ($input['outcomes'] ?? [] as $i => $row) {
                $isNew = ($row['skill_id'] ?? '') === 'new';
                if ($isNew) {
                    foreach (['new_name' => 'Nama keterampilan', 'category' => 'Kategori', 'description' => 'Penjelasan kompetensi'] as $key => $label) {
                        if (trim($row[$key] ?? '') === '') {
                            $validator->errors()->add("outcomes.$i.$key", "$label wajib diisi untuk usulan baru.");
                        }
                    }
                }
                $key = $isNew ? 'name:'.Skill::normalize($row['new_name'] ?? '') : 'id:'.$row['skill_id'];
                if (isset($seen[$key])) {
                    $validator->errors()->add('outcomes', 'Keterampilan yang sama cukup ditulis satu kali.');
                }
                $seen[$key] = true;
            }
        });
        $data = $validator->validate();
        $data['title'] = Str::squish($data['title']);
        $data['outcomes'] = $data['outcomes'] ?? [];
        foreach ($data['outcomes'] as &$row) {
            if (($row['skill_id'] ?? '') !== 'new') {
                unset($row['new_name'],$row['category'],$row['description']);
            } elseif (isset($row['new_name'])) {
                $row['new_name'] = Str::squish($row['new_name']);
            }
        }
        unset($row);

        return $data;
    }
}
