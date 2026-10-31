<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use App\Services\TrainingSimulation;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SimulationController extends Controller
{
    public function create(Request $r): View
    {
        $data = $r->validate(['job_id' => 'nullable|integer|min:1', 'training_id' => 'nullable|integer|min:1']);
        $job = ! empty($data['job_id']) ? Publication::jobs()->findOrFail($data['job_id']) : null;
        if (! empty($data['training_id'])) {
            Publication::trainings()->findOrFail($data['training_id']);
        }

        return $this->page($r, $job, isset($data['training_id']) ? [(int) $data['training_id']] : [], null);
    }

    public function calculate(Request $r, TrainingSimulation $simulation): View
    {
        $data = $r->validate(['job_id' => 'required|integer|min:1', 'training_ids' => 'required|array|min:1|max:3', 'training_ids.*' => 'required|integer|min:1|distinct']);
        $job = Publication::jobs()->findOrFail($data['job_id']);
        $ids = array_map('intval', $data['training_ids']);

        return $this->page($r, $job, $ids, $simulation->run($r->user(), $job, $ids));
    }

    private function page(Request $r, ?JobVacancy $job, array $selected, ?array $result): View
    {
        return view('applicant.simulation', [...WorkspacePage::data($r, 'pelamar', 'simulasi', 'Simulasi Kecocokan', $job ? route('applicant.gaps.show', $job->id) : route('applicant.jobs.index')), 'targetJob' => $job, 'jobs' => Publication::jobs()->orderBy('title')->get(), 'trainings' => Publication::trainings()->with('skills')->orderBy('title')->get(), 'selected' => $selected, 'result' => $result, 'levels' => SkillController::LEVELS]);
    }
}
