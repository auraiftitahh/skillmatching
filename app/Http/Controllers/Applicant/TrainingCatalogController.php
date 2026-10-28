<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\Training;
use App\Models\UserSkill;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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
        if (isset($filters['max_cost'])) {
            $query->where('cost', '<=', $filters['max_cost']);
        }
        if (! empty($filters['skill_id'])) {
            $query->whereHas('skills', fn ($q) => $q->where('skills.id', $filters['skill_id']));
        }

        return view('applicant.trainings.index', [...WorkspacePage::data($r, 'pelamar', 'pelatihan', 'Katalog Pelatihan'),
            'trainings' => $query->orderBy('title')->paginate(9)->withQueryString(), 'filters' => $filters, 'skills' => Skill::where('is_active', true)->orderBy('name')->get()]);
    }

    public function show(Request $r, int $training): View
    {
        $filters = $this->filters($r);
        $record = Publication::trainings()->with(['body', 'skills'])->findOrFail($training);

        return view('applicant.trainings.show', [...WorkspacePage::data($r, 'pelamar', 'pelatihan', $record->title, route('applicant.trainings.index', $filters)),
            'prerequisites' => Training::whereIn('id', DB::table('training_prerequisites')->where('training_id', $record->id)->select('prerequisite_id'))->orderBy('title')->get(),
            'completedIds' => DB::table('training_enrollments')->where('user_id', $r->user()->id)->where('status', 'completed')->pluck('training_id')->all(),
            'availablePrerequisiteIds' => Publication::trainings()->whereIn('trainings.id', DB::table('training_prerequisites')->where('training_id', $record->id)->select('prerequisite_id'))->pluck('trainings.id')->all(),
            'training' => $record, 'own' => UserSkill::where('user_id', $r->user()->id)->pluck('level', 'skill_id'), 'levels' => SkillController::LEVELS]);
    }

    private function filters(Request $r): array
    {
        return $r->validate(['q' => 'nullable|string|max:100', 'mode' => ['nullable', Rule::in(['online', 'offline', 'hybrid'])], 'max_cost' => 'nullable|numeric|min:0|max:999999999', 'skill_id' => ['nullable', 'integer', Rule::exists('skills', 'id')->where('is_active', true)]]);
    }
}
