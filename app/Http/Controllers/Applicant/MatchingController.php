<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Services\MatchingService;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MatchingController extends Controller
{
    public function index(Request $r, MatchingService $matching): View|RedirectResponse
    {
        $filters = $r->validate(['q' => 'nullable|string|max:100', 'all' => 'nullable|boolean', 'page' => 'nullable|integer|min:1|max:100000']);
        $results = $matching->rank($r->user(), (bool) ($filters['all'] ?? false));
        if (! empty($filters['q'])) {
            $needle = mb_strtolower($filters['q']);
            $results = array_values(array_filter($results, fn ($row) => str_contains(mb_strtolower($row['job'].' '.$row['company']), $needle)));
        }
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
