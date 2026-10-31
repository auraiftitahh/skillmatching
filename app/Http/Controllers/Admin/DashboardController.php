<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificationBody;
use App\Models\TrainingSubmission;
use App\Models\User;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $r): View
    {
        return view('admin.dashboard', [...WorkspacePage::data($r, 'admin', 'dashboard', 'Dashboard Admin'), 'pendingInstitutions' => CertificationBody::where('status', 'pending')->count(), 'pendingTrainings' => TrainingSubmission::where('status', 'pending')->count(), 'publishedJobs' => Publication::jobs()->count(), 'publishedTrainings' => Publication::trainings()->count(), 'activeUsers' => User::where('is_active', true)->whereNotNull('email_verified_at')->count()]);
    }
}
