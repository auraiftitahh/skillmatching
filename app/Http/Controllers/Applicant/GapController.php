<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use App\Services\GapService;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class GapController extends Controller
{
    public function __construct(private GapService $gaps) {}

    public function index(Request $r): View
    {
        $data = $r->validate(['job_id' => 'nullable|integer|min:1']);
        $job = isset($data['job_id']) ? Publication::jobs()->findOrFail($data['job_id']) : null;

        return $this->page($r, $job);
    }

    public function show(Request $r, int $job): View
    {
        return $this->page($r, Publication::jobs()->findOrFail($job));
    }

    private function page(Request $r, ?JobVacancy $job): View
    {
        return view('applicant.gaps', [...WorkspacePage::data($r, 'pelamar', 'lowongan', 'Kemampuan yang Perlu Ditingkatkan', $job ? route('applicant.matching.show', $job->id) : route('applicant.jobs.index')),
            'targetJob' => $job, 'gap' => $job ? $this->gaps->forJob($r->user(), $job) : null, 'jobs' => Publication::jobs()->orderBy('title')->get(), 'levels' => SkillController::LEVELS]);
    }
}
