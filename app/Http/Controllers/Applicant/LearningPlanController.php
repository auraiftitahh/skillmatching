<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Training;
use App\Services\LearningPlans;
use App\Services\TrainingPath;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LearningPlanController extends Controller
{
    public function index(Request $r): View
    {
        $data = $r->validate(['job_id' => 'nullable|integer|min:1']);
        $job = ! empty($data['job_id']) ? Publication::jobs()->findOrFail($data['job_id']) : null;
        $records = DB::table('training_enrollments')->where('user_id', $r->user()->id)->orderBy('created_at')->orderBy('id')->get();
        $trainings = Training::whereIn('id', $records->pluck('training_id'))->get()->keyBy('id');
        $available = Publication::trainings()->whereIn('trainings.id', $records->pluck('training_id'))->pluck('trainings.id')->all();
        $paths = [];
        foreach ($trainings as $training) {
            $paths[$training->id] = app(TrainingPath::class)->forTraining($r->user(), $training);
        }

        return view('applicant.plans', [...WorkspacePage::data($r, 'pelamar', 'rencana-belajar', 'Rencana Belajar'), 'records' => $records, 'trainings' => $trainings, 'available' => $available, 'paths' => $paths, 'targetJob' => $job]);
    }

    public function store(Request $r, LearningPlans $plans): RedirectResponse
    {
        $data = $r->validate(['training_id' => 'required|integer|min:1', 'job_id' => 'nullable|integer|min:1']);
        if (! empty($data['job_id'])) {
            Publication::jobs()->findOrFail($data['job_id']);
        }
        $plans->add($r->user(), (int) $data['training_id']);

        return redirect()->route('applicant.plans.index', array_filter(['job_id' => $data['job_id'] ?? null]))->with('status', 'Pelatihan tersimpan dalam rencana belajar. Profil kemampuan Anda belum berubah.');
    }

    public function start(Request $r, int $enrollment, LearningPlans $plans): RedirectResponse
    {
        $plans->start($r->user(), $enrollment);

        return redirect()->route('applicant.plans.index')->with('status', 'Status pelatihan menjadi sedang diikuti.');
    }

    public function submitCompletion(Request $r, int $enrollment, LearningPlans $plans): RedirectResponse
    {
        $plans->submitCompletion($r->user(), $enrollment);

        return redirect()->route('applicant.plans.index')->with('status', 'Penyelesaian diajukan. Tunggu konfirmasi penyelenggara.');
    }

    public function destroy(Request $r, int $enrollment, LearningPlans $plans): RedirectResponse
    {
        $plans->remove($r->user(), $enrollment);

        return redirect()->route('applicant.plans.index')->with('status', 'Rencana belajar dihapus.');
    }
}
