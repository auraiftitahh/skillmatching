<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\UserSkill;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SkillSuggestionController extends Controller
{
    public function index(Request $r): View
    {
        $rows = DB::table('user_experiences as e')->join('job_taxonomies as j', 'j.id', '=', 'e.job_taxonomy_id')->join('job_taxonomy_skill as m', 'm.job_taxonomy_id', '=', 'j.id')->join('skills as s', 's.id', '=', 'm.skill_id')
            ->where('e.user_id', $r->user()->id)->where('e.type', 'work')->where('j.is_active', true)->where('s.is_active', true)->select('s.id', 's.name', 's.category', 'j.name as job')->orderBy('s.name')->get();
        $suggestions = $rows->groupBy('id')->map(fn ($items) => ['id' => $items->first()->id, 'name' => $items->first()->name, 'category' => $items->first()->category, 'jobs' => $items->pluck('job')->unique()->values()->all()])->values();

        return view('applicant.skill-suggestions', [...WorkspacePage::data($r, 'pelamar', 'skill', 'Saran Keterampilan', route('applicant.experiences.index')), 'suggestions' => $suggestions, 'own' => UserSkill::where('user_id', $r->user()->id)->get()->keyBy('skill_id'), 'levels' => SkillController::LEVELS]);
    }
}
