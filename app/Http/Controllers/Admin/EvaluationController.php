<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\WeightConfiguration;
use App\Services\MatchingService;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluationController extends Controller
{
    public function setup(Request $request): View
    {
        return view('admin.evaluation.setup', [
            ...WorkspacePage::data($request, 'admin', 'evaluasi', 'Profil Uji dan Penilaian Ahli'),
            'skills' => Skill::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'profiles' => DB::table('evaluation_profiles')->orderBy('code')->get(),
            'jobs' => Publication::jobs()->orderBy('title')->get(['id', 'title', 'company']),
        ]);
    }

    public function storeProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40|unique:evaluation_profiles,code',
            'group' => 'nullable|string|max:100',
            'levels' => 'required|array',
            'levels.*' => 'nullable|integer|between:1,4',
        ]);
        $levels = array_filter($data['levels'], fn ($level) => $level !== null);
        foreach (array_keys($levels) as $id) {
            if (! is_int($id) || $id < 1) {
                throw ValidationException::withMessages(['levels' => 'Pilihan keterampilan tidak valid.']);
            }
        }
        if ($levels === [] || Skill::where('is_active', true)->whereKey(array_keys($levels))->count() !== count($levels)) {
            throw ValidationException::withMessages(['levels' => 'Pilih setidaknya satu keterampilan aktif.']);
        }
        DB::table('evaluation_profiles')->insert([
            'code' => $data['code'], 'group' => $data['group'] ?? null,
            'skills_json' => json_encode(array_map('intval', $levels), JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('admin.evaluation.setup')->with('status', 'Profil uji tersimpan.');
    }

    public function storeRating(Request $request, MatchingService $matching): RedirectResponse
    {
        $data = $request->validate([
            'evaluation_profile_id' => 'required|integer|exists:evaluation_profiles,id',
            'job_vacancy_id' => 'required|integer|min:1',
            'expert_rating' => 'required|integer|between:1,4',
        ]);
        $profile = DB::table('evaluation_profiles')->find($data['evaluation_profile_id']);
        abort_unless($profile, 404);
        $job = Publication::jobs()->findOrFail($data['job_vacancy_id']);
        $weights = WeightConfiguration::current();
        $result = $matching->score($job, json_decode($profile->skills_json, true, flags: JSON_THROW_ON_ERROR), $weights);
        DB::table('evaluation_pairs')->insert([
            ...$data, 'job_title' => $job->title, 'company' => $job->company,
            'system_score' => $result['score'], 'weight_configuration_id' => $weights->id,
            'reviewed_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('admin.evaluation.setup')->with('status', 'Penilaian ahli dan skor sistem tersimpan.');
    }

    public function feedback(Request $request): View
    {
        $rating = $request->validate(['rating' => 'nullable|in:relevant,not_relevant'])['rating'] ?? null;
        $query = DB::table('recommendation_feedback as feedback')
            ->join('job_vacancies as jobs', 'jobs.id', '=', 'feedback.job_vacancy_id')
            ->join('weight_configurations as weights', 'weights.id', '=', 'feedback.weight_configuration_id');
        if ($rating !== null) {
            $query->where('feedback.rating', $rating);
        }

        return view('admin.evaluation.feedback', [
            ...WorkspacePage::data($request, 'admin', 'evaluasi', 'Umpan Balik Rekomendasi'),
            'records' => $query->select('feedback.*', 'jobs.title', 'jobs.company', 'weights.version')
                ->latest('feedback.updated_at')->orderByDesc('feedback.id')->paginate(15)->withQueryString(),
            'rating' => $rating,
        ]);
    }
}
