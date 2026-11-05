<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\TrainingSubmission;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $r): View
    {
        $body = $r->user()->certificationBody;
        $query = TrainingSubmission::where('certification_body_id', $body->id);

        return view('institution.dashboard', [
            ...WorkspacePage::data($r, 'sertifikasi', 'dashboard', 'Dashboard Badan Sertifikasi'),
            'body' => $body,
            'counts' => (clone $query)->selectRaw('status,COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'latest' => (clone $query)->latest('updated_at')->limit(5)->get(),
            'publishedCount' => Publication::trainings()->where('certification_body_id', $body->id)->count(),
            'pendingGraduations' => DB::table('training_enrollments as enrollments')
                ->join('trainings', 'trainings.id', '=', 'enrollments.training_id')
                ->join('users', 'users.id', '=', 'enrollments.user_id')
                ->where('trainings.certification_body_id', $body->id)->where('enrollments.status', 'waiting_confirmation')
                ->where('users.is_active', true)->whereNotNull('users.email_verified_at')->count(),
            'pendingEvidence' => DB::table('external_certificates')->where('certification_body_id', $body->id)->where('status', 'pending')->count(),
        ]);
    }
}
