<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Services\LearningPlans;
use App\Services\MatchingService;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $r, MatchingService $matching): View
    {
        $user = $r->user();
        $levels = $matching->levels($user);
        $matches = array_slice($matching->rank($user), 0, 3);

        return view('applicant.dashboard', [...WorkspacePage::data($r, 'pelamar', 'dashboard', 'Dashboard Pelamar'), 'skillCount' => count($levels), 'experienceCount' => $user->experiences()->count(), 'activePlans' => DB::table('training_enrollments')->where('user_id', $user->id)->whereIn('status', LearningPlans::ACTIVE)->count(), 'matches' => $matches, 'missingProfile' => collect(['phone' => 'Nomor telepon', 'domicile' => 'Domisili', 'employment_status' => 'Status pekerjaan'])->filter(fn ($label, $field) => blank($user->$field))->all()]);
    }
}
