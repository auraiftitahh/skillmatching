<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Support\WorkspaceNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SkillCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(['active', 'inactive'])]]);
        $skills = Skill::query();
        if ($query = trim($filters['q'] ?? '')) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Skill::normalize($query)).'%';
            $skills->whereIn('id', DB::table('skill_names')->whereRaw("normalized_name LIKE ? ESCAPE '!'", [$pattern])->select('skill_id'));
        }
        if (! empty($filters['status'])) {
            $skills->where('is_active', $filters['status'] === 'active');
        }

        return view('admin.skills.index', [...$this->layout($request, 'Katalog Keterampilan'),
            'skills' => $skills->orderBy('name')->paginate(12)->withQueryString(), 'filters' => $filters]);
    }

    public function edit(Request $request, Skill $skill): View
    {
        return view('admin.skills.edit', [...$this->layout($request, 'Rapikan Keterampilan'),
            'skill' => $skill, 'aliases' => DB::table('skill_names')->where('skill_id', $skill->id)->where('is_primary', false)->pluck('name')->implode("\n")]);
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'category' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000', 'is_active' => 'required|boolean', 'aliases' => 'nullable|string|max:3000']);
        $names = [Str::squish($data['name'])];
        foreach (preg_split('/\R/u', $data['aliases'] ?? '') as $alias) {
            if (Str::squish($alias) !== '') {
                $names[] = Str::squish($alias);
            }
        }
        if (count($names) > 21) {
            throw ValidationException::withMessages(['aliases' => 'Tambahkan maksimal 20 nama lain.']);
        }
        $normalized = [];
        foreach ($names as $name) {
            if (mb_strlen($name) > 150) {
                throw ValidationException::withMessages(['aliases' => 'Setiap nama maksimal 150 karakter.']);
            }
            $key = Skill::normalize($name);
            if (isset($normalized[$key])) {
                throw ValidationException::withMessages(['aliases' => 'Nama utama dan nama lain tidak boleh berulang.']);
            }
            $normalized[$key] = $name;
        }
        try {
            DB::transaction(function () use ($skill, $data, $names, $normalized): void {
                $record = Skill::whereKey($skill->id)->lockForUpdate()->firstOrFail();
                if (DB::table('skill_names')->whereIn('normalized_name', array_keys($normalized))->where('skill_id', '!=', $record->id)->exists()) {
                    throw ValidationException::withMessages(['name' => 'Nama atau nama lain sudah dipakai keterampilan lain. Gunakan kompetensi yang tersedia.']);
                }
                $record->update(['name' => $names[0], 'category' => Str::squish($data['category']),
                    'description' => $data['description'] ?? null, 'is_active' => $data['is_active']]);
                DB::table('skill_names')->where('skill_id', $record->id)->delete();
                foreach (array_values($normalized) as $index => $name) {
                    DB::table('skill_names')->insert(['skill_id' => $record->id, 'name' => $name,
                        'normalized_name' => Skill::normalize($name), 'is_primary' => $index === 0,
                        'created_at' => now(), 'updated_at' => now()]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Nama tersebut baru saja dipakai keterampilan lain. Muat ulang dan gunakan data yang tersedia.']);
        }

        return redirect()->route('admin.skills.edit', $skill)->with('success', 'Keterampilan berhasil diperbarui.');
    }

    private function layout(Request $request, string $title): array
    {
        return ['pageTitle' => $title, 'roleLabel' => 'Admin', 'navigation' => WorkspaceNavigation::items('admin', 'skill'),
            'homeUrl' => route('workspace', ['role' => 'admin', 'page' => 'dashboard']),
            'backUrl' => route('admin.skills.index'), 'userName' => $request->user()->name];
    }
}
