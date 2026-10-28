<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use App\Models\Skill;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JobVacancyController extends Controller
{
    public function index(Request $r): View
    {
        $r->validate(['q' => 'nullable|string|max:100']);
        $query = JobVacancy::withCount('skills');
        if ($r->filled('q')) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($r->input('q'))).'%';
            $query->where(fn ($q) => $q->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(company) LIKE ? ESCAPE '!'", [$pattern]));
        }

        return view('admin.jobs.index', [...WorkspacePage::data($r, 'admin', 'lowongan', 'Lowongan Kerja'), 'jobs' => $query->latest('id')->paginate(12)->withQueryString()]);
    }

    public function create(Request $r): View
    {
        return $this->form($r, new JobVacancy(['employment_type' => 'contract', 'status' => 'draft', 'fetched_at' => now()]));
    }

    public function edit(Request $r, JobVacancy $job): View
    {
        return $this->form($r, $job);
    }

    private function form(Request $r, JobVacancy $job): View
    {
        $rows = $job->exists ? $job->skills()->get()->map(fn ($s) => ['skill_id' => $s->id, 'minimum_level' => $s->pivot->minimum_level, 'importance' => $s->pivot->importance, 'weight' => $s->pivot->is_override ? $s->pivot->weight : null])->all() : [[]];

        return view('admin.jobs.form', [...WorkspacePage::data($r, 'admin', 'lowongan', $job->exists ? 'Ubah Lowongan' : 'Tambah Lowongan', route('admin.jobs.index')), 'job' => $job, 'rows' => $rows, 'skills' => Skill::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $r): RedirectResponse
    {
        return $this->save($r, new JobVacancy);
    }

    public function update(Request $r, JobVacancy $job): RedirectResponse
    {
        return $this->save($r, $job);
    }

    private function save(Request $r, JobVacancy $job): RedirectResponse
    {
        $data = $r->validate(['title' => 'required|string|max:190', 'company' => 'required|string|max:190', 'location' => 'required|string|max:190', 'description' => 'required|string|max:10000', 'employment_type' => ['required', Rule::in(array_keys(JobVacancy::TYPES))],
            'source_url' => 'required|url:http,https|max:1000', 'source_name' => 'nullable|string|max:190', 'fetched_at' => 'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Jakarta')->toDateString(), 'expires_at' => 'nullable|date_format:Y-m-d',
            'requirements' => 'required|array|min:1|max:50', 'requirements.*' => 'array:skill_id,minimum_level,importance,weight', 'requirements.*.skill_id' => ['required', 'integer', 'distinct', Rule::exists('skills', 'id')->where('is_active', true)],
            'requirements.*.minimum_level' => 'required|integer|between:1,4', 'requirements.*.importance' => ['required', Rule::in(['required', 'preferred'])], 'requirements.*.weight' => 'nullable|integer|between:1,5']);
        $id = DB::transaction(function () use ($job, $data): int {
            $record = $job->exists ? JobVacancy::whereKey($job->id)->lockForUpdate()->firstOrFail() : $job;
            $record->fill(array_diff_key($data, ['requirements' => true]));
            if (! $record->exists) {
                $record->status = 'draft';
            }$record->save();
            $requirements = [];
            foreach ($data['requirements'] as $row) {
                $override = isset($row['weight']);
                $requirements[$row['skill_id']] = ['minimum_level' => $row['minimum_level'], 'importance' => $row['importance'], 'weight' => $override ? $row['weight'] : ($row['importance'] === 'required' ? 3 : 1), 'is_override' => $override];
            }
            $record->skills()->sync($requirements);

            return $record->id;
        });

        return redirect()->route('admin.jobs.edit',$id)->with('success','Lowongan dan persyaratan keterampilan tersimpan.');
    }
}
