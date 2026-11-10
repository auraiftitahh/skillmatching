<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\WeightConfiguration;
use App\Services\MatchingService;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvaluationController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $query = $this->reportRows($this->filters($request))->orderByDesc('pairs.created_at')->orderByDesc('pairs.id');

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Profil', 'Kelompok', 'Posisi', 'Perusahaan', 'Skor sistem', 'Penilaian ahli', 'Versi bobot', 'Tanggal WIB'], escape: '');
            foreach ($query->lazy(250) as $record) {
                fputcsv($output, [
                    $this->csvText($record->code), $this->csvText($record->group),
                    $this->csvText($record->job_title), $this->csvText($record->company),
                    $record->system_score, $record->expert_rating, $record->version,
                    Carbon::parse($record->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
                ], escape: '');
            }
            fclose($output);
        }, 'evaluasi-rekomendasi-'.now('Asia/Jakarta')->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvText(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[\t\r\n]|^\s*[=+@-]/u', $value) === 1 ? "'".$value : $value;
    }

    public function report(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->reportRows($filters);

        return view('admin.evaluation.report', [
            ...WorkspacePage::data($request, 'admin', 'evaluasi', 'Rekap Evaluasi'),
            'records' => (clone $query)->latest('pairs.created_at')->orderByDesc('pairs.id')->paginate(15)->withQueryString(),
            'totals' => (clone $query)->select([])->selectRaw('COUNT(*) AS total, AVG(pairs.system_score) AS system_average, AVG(pairs.expert_rating) AS expert_average')->first(),
            'versions' => WeightConfiguration::orderByDesc('version')->get(['id', 'version']),
            'filters' => $filters,
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'version' => 'nullable|integer|exists:weight_configurations,id',
        ]);
    }

    private function reportRows(array $filters): Builder
    {
        $query = DB::table('evaluation_pairs as pairs')
            ->join('evaluation_profiles as profiles', 'profiles.id', '=', 'pairs.evaluation_profile_id')
            ->join('weight_configurations as weights', 'weights.id', '=', 'pairs.weight_configuration_id');
        if (! empty($filters['from'])) {
            $query->where('pairs.created_at', '>=', Carbon::parse($filters['from'], 'Asia/Jakarta')->startOfDay()->timezone(config('app.timezone')));
        }
        if (! empty($filters['to'])) {
            $query->where('pairs.created_at', '<', Carbon::parse($filters['to'], 'Asia/Jakarta')->startOfDay()->addDay()->timezone(config('app.timezone')));
        }
        if (! empty($filters['version'])) {
            $query->where('pairs.weight_configuration_id', $filters['version']);
        }

        return $query->select('pairs.*', 'profiles.code', 'profiles.group', 'weights.version');
    }

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
