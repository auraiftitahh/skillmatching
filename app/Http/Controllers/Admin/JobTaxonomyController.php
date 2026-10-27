<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobTaxonomy;
use App\Models\Skill;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JobTaxonomyController extends Controller
{
    public function index(Request $r): View
    {
        $r->validate(['q' => 'nullable|string|max:100']);
        $query = JobTaxonomy::withCount('skills');
        if ($r->filled('q')) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Skill::normalize($r->input('q'))).'%';
            $query->whereRaw("normalized_name LIKE ? ESCAPE '!'", [$pattern]);
        }

        return view('admin.job-taxonomies.index', [...WorkspacePage::data($r, 'admin', 'jabatan', 'Pemetaan Jabatan'), 'taxonomies' => $query->orderBy('name')->paginate(12)->withQueryString()]);
    }

    public function create(Request $r): View
    {
        return $this->form($r, new JobTaxonomy(['is_active' => true]));
    }

    public function edit(Request $r, JobTaxonomy $taxonomy): View
    {
        return $this->form($r, $taxonomy);
    }

    private function form(Request $r, JobTaxonomy $taxonomy): View
    {
        return view('admin.job-taxonomies.form', [...WorkspacePage::data($r, 'admin', 'jabatan', $taxonomy->exists ? 'Ubah Pemetaan Jabatan' : 'Tambah Jabatan', route('admin.taxonomies.index')), 'taxonomy' => $taxonomy, 'skills' => Skill::where('is_active', true)->orderBy('name')->get(), 'selected' => $taxonomy->exists ? $taxonomy->skills()->pluck('skills.id')->all() : []]);
    }

    public function store(Request $r): RedirectResponse
    {
        return $this->save($r, new JobTaxonomy);
    }

    public function update(Request $r, JobTaxonomy $taxonomy): RedirectResponse
    {
        return $this->save($r, $taxonomy);
    }

    private function save(Request $r, JobTaxonomy $taxonomy): RedirectResponse
    {
        $input = $r->all();
        $input['normalized_name'] = Skill::normalize((string) $r->input('name'));
        $data = Validator::make($input, ['name' => 'required|string|max:150', 'normalized_name' => ['required', 'string', 'max:150', Rule::unique('job_taxonomies')->ignore($taxonomy->id)], 'description' => 'nullable|string|max:2000', 'is_active' => 'required|boolean', 'skills' => 'nullable|array|max:100', 'skills.*' => ['required', 'integer', 'distinct', Rule::exists('skills', 'id')->where('is_active', true)]], ['normalized_name.unique' => 'Jabatan dengan nama yang sama sudah tersedia.'])->validate();
        try {
            DB::transaction(function () use ($data, $taxonomy): void {
                $record = $taxonomy->exists ? JobTaxonomy::whereKey($taxonomy->id)->lockForUpdate()->firstOrFail() : $taxonomy;
                $record->fill(['name' => Str::squish($data['name']), 'normalized_name' => $data['normalized_name'], 'description' => $data['description'] ?? null, 'is_active' => $data['is_active']]);
                $record->save();
                $historical = $record->skills()->where('skills.is_active', false)->pluck('skills.id')->all();
                $record->skills()->sync(array_unique(array_merge($data['skills'] ?? [], $historical)));
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Jabatan tersebut sudah tersedia.']);
        }

        return redirect()->route('admin.taxonomies.index')->with('success','Pemetaan jabatan tersimpan. Saran tetap memerlukan konfirmasi Pelamar.');
    }
}
