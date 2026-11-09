<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\WeightConfiguration;
use App\Services\MatchingService;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MatchingController extends Controller
{
    public function feedback(Request $request, int $job): RedirectResponse
    {
        $record = Publication::jobs()->findOrFail($job);
        $data = $request->validate(['rating' => 'required|in:relevant,not_relevant', 'comment' => 'nullable|string|max:2000']);
        DB::table('recommendation_feedback')->upsert([
            [
                ...$data, 'comment' => $data['comment'] ?? null, 'user_id' => $request->user()->id,
                'job_vacancy_id' => $record->id, 'weight_configuration_id' => WeightConfiguration::current()->id,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ], ['user_id', 'job_vacancy_id', 'weight_configuration_id'], ['rating', 'comment', 'updated_at']);

        return redirect()->route('applicant.matching.show', $job)->with('status', 'Umpan balik tersimpan.');
    }

    public function history(Request $request): View
    {
        return view('applicant.matching.history', [
            ...WorkspacePage::data($request, 'pelamar', 'lowongan', 'Riwayat Rekomendasi', route('applicant.matching.index')),
            'snapshots' => DB::table('recommendation_histories')->where('user_id', $request->user()->id)->latest('id')->paginate(6),
        ]);
    }

    public function saveHistory(Request $request, MatchingService $matching): RedirectResponse
    {
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'all' => 'nullable|boolean']);
        $results = $this->filteredResults($request, $matching, $filters);
        if ($results === []) {
            return back()->withErrors(['recommendation' => 'Tidak ada hasil rekomendasi untuk disimpan.']);
        }
        DB::table('recommendation_histories')->insert([
            'user_id' => $request->user()->id,
            'results' => json_encode($results, JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('applicant.matching.history')->with('status', 'Hasil rekomendasi tersimpan.');
    }

    private function filteredResults(Request $request, MatchingService $matching, array $filters): array
    {
        $results = $matching->rank($request->user(), (bool) ($filters['all'] ?? false));
        if (! empty($filters['q'])) {
            $needle = mb_strtolower($filters['q']);
            $results = array_values(array_filter($results, fn ($row) => str_contains(mb_strtolower($row['job'].' '.$row['company']), $needle)));
        }

        return $results;
    }

    public function index(Request $r, MatchingService $matching): View|RedirectResponse
    {
        $filters = $r->validate(['q' => 'nullable|string|max:100', 'all' => 'nullable|boolean', 'page' => 'nullable|integer|min:1|max:100000']);
        $results = $this->filteredResults($r, $matching, $filters);
        $page = (int) ($filters['page'] ?? 1);
        $last = max(1, (int) ceil(count($results) / 9));
        if ($page > $last) {
            return redirect()->route('applicant.matching.index', [...$filters, 'page' => $last]);
        }
        $paginator = new LengthAwarePaginator(array_slice($results, ($page - 1) * 9, 9), count($results), 9, $page, ['path' => route('applicant.matching.index'), 'query' => $filters]);

        return view('applicant.matching.index', [...WorkspacePage::data($r, 'pelamar', 'lowongan', 'Rekomendasi Posisi Berdasarkan Kemampuan', route('applicant.jobs.index')), 'results' => $paginator, 'filters' => $filters, 'hasSkills' => $matching->levels($r->user()) !== []]);
    }

    public function show(Request $r, int $job, MatchingService $matching): View
    {
        $record = Publication::jobs()->findOrFail($job);
        $result = $matching->score($record, $matching->levels($r->user()));

        return view('applicant.matching.show', [...WorkspacePage::data($r, 'pelamar', 'lowongan', 'Kecocokan '.$record->title, route('applicant.jobs.show', $job)), 'job' => $record, 'result' => $result, 'levels' => SkillController::LEVELS]);
    }
}
