<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\Training;
use App\Models\UserSkill;
use App\Services\TrainingPath;
use App\Services\TrainingRecommendations;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrainingCatalogController extends Controller
{
    public function index(Request $r): View
    {
        $filters = $this->filters($r);
        $query = Publication::trainings()->with(['body', 'skills']);
        if (! empty($filters['q'])) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['q'])).'%';
            $query->where(fn ($q) => $q->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$pattern])->orWhereHas('body', fn ($b) => $b->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])));
        }
        if (! empty($filters['mode'])) {
            $query->where('mode', $filters['mode']);
        }
        if (isset($filters['weekly_hours'])) {
            $query->where('weekly_hours', '<=', $filters['weekly_hours']);
        }
        if (isset($filters['max_cost'])) {
            $query->where('cost', '<=', $filters['max_cost']);
        }
        if (! empty($filters['skill_id'])) {
            $query->whereHas('skills', fn ($q) => $q->where('skills.id', $filters['skill_id']));
        }

        $targetJob = ! empty($filters['job_id']) ? Publication::jobs()->findOrFail($filters['job_id']) : null;
        if ($targetJob) {
            $ranked = app(TrainingRecommendations::class)->rank($r->user(), $targetJob, $query->get());
            $page = LengthAwarePaginator::resolveCurrentPage();
            $trainings = new LengthAwarePaginator($ranked->forPage($page, 9)->values(), $ranked->count(), 9, $page, ['path' => $r->url(), 'query' => $r->query()]);
        } else {
            $trainings = $query->orderBy('title')->paginate(9)->withQueryString();
        }

        return view('applicant.trainings.index', [...WorkspacePage::data($r, 'pelamar', 'pelatihan', 'Katalog Pelatihan'),
            'targetJob' => $targetJob, 'trainings' => $trainings, 'filters' => $filters, 'skills' => Skill::where('is_active', true)->orderBy('name')->get()]);
    }

    public function show(Request $r, int $training): View
    {
        $filters = $this->filters($r);
        $record = Publication::trainings()->with(['body', 'skills'])->findOrFail($training);

        return view('applicant.trainings.show', [...WorkspacePage::data($r, 'pelamar', 'pelatihan', $record->title, route('applicant.trainings.index', $filters)),
            'learningPath' => app(TrainingPath::class)->forTraining($r->user(), $record),
            'prerequisites' => Training::whereIn('id', DB::table('training_prerequisites')->where('training_id', $record->id)->select('prerequisite_id'))->orderBy('title')->get(),
            'completedIds' => DB::table('training_enrollments')->where('user_id', $r->user()->id)->where('status', 'completed')->pluck('training_id')->all(),
            'availablePrerequisiteIds' => Publication::trainings()->whereIn('trainings.id', DB::table('training_prerequisites')->where('training_id', $record->id)->select('prerequisite_id'))->pluck('trainings.id')->all(),
            'targetJob' => ! empty($filters['job_id']) ? Publication::jobs()->findOrFail($filters['job_id']) : null, 'training' => $record, 'own' => UserSkill::where('user_id', $r->user()->id)->pluck('level', 'skill_id'), 'levels' => SkillController::LEVELS]);
    }

    private function filters(Request $r): array
    {
        $filters = $r->validate(['job_id' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:100', 'mode' => ['nullable', Rule::in(['online', 'offline', 'hybrid'])], 'max_cost' => 'nullable|numeric|min:0|max:999999999', 'weekly_hours' => 'nullable|integer|min:1|max:168', 'skill_id' => ['nullable', 'integer', Rule::exists('skills', 'id')->where('is_active', true)]]);
        if (! empty($filters['job_id'])) {
            Publication::jobs()->findOrFail($filters['job_id']);
        }

        return $filters;
    }
}
