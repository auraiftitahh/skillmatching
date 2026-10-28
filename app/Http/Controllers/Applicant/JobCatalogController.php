<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use App\Models\UserSkill;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JobCatalogController extends Controller
{
    public function index(Request $r): View
    {
        $filters = $this->filters($r);
        $saved = DB::table('saved_jobs')->where('user_id', $r->user()->id)->pluck('job_vacancy_id')->all();
        $query = ($filters['saved'] ?? false) ? JobVacancy::whereIn('id', $saved) : Publication::jobs();
        if (! empty($filters['q'])) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q'])).'%';
            $query->where(fn ($q) => $q->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(company) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(location) LIKE ? ESCAPE '!'", [$pattern]));
        }
        if (! empty($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }
        $jobs = $query->orderBy('title')->paginate(9)->withQueryString();

        return view('applicant.jobs.index', [...WorkspacePage::data($r, 'pelamar', 'lowongan', 'Posisi Tujuan'), 'jobs' => $jobs, 'savedIds' => $saved, 'filters' => $filters, 'availableIds' => Publication::jobs()->whereIn('job_vacancies.id', $jobs->pluck('id'))->pluck('job_vacancies.id')->all()]);
    }

    public function show(Request $r, int $job): View
    {
        $filters = $this->filters($r);
        $record = Publication::jobs()->with('skills')->findOrFail($job);

        return view('applicant.jobs.show', [...WorkspacePage::data($r, 'pelamar', 'lowongan', $record->title, route('applicant.jobs.index', $filters)),
            'job' => $record, 'own' => UserSkill::where('user_id', $r->user()->id)->pluck('level', 'skill_id'), 'levels' => SkillController::LEVELS,
            'saved' => DB::table('saved_jobs')->where('user_id', $r->user()->id)->where('job_vacancy_id', $job)->exists()]);
    }

    public function save(Request $r, int $job): RedirectResponse
    {
        $record = Publication::jobs()->findOrFail($job);
        DB::table('saved_jobs')->insertOrIgnore(['user_id' => $r->user()->id, 'job_vacancy_id' => $record->id, 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('applicant.jobs.show', $record->id)->with('success', 'Posisi tujuan disimpan di akun Anda.');
    }

    public function unsave(Request $r, int $job): RedirectResponse
    {
        $count = DB::table('saved_jobs')->where('user_id', $r->user()->id)->where('job_vacancy_id', $job)->delete();
        abort_unless($count > 0, 404);

        return redirect()->route('applicant.jobs.index', ['saved' => 1])->with('success', 'Posisi dihapus dari daftar simpanan Anda.');
    }

    private function filters(Request $r): array
    {
        return $r->validate(['q' => 'nullable|string|max:100', 'employment_type' => ['nullable', Rule::in(array_keys(JobVacancy::TYPES))], 'saved' => 'nullable|boolean']);
    }
}
