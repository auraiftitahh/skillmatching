<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluationController extends Controller
{
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
