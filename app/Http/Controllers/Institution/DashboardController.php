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
    public function history(Request $request): View
    {
        $bodyId = $request->user()->certificationBody->id;
        $reviews = DB::table('training_submissions')->where('certification_body_id', $bodyId)->whereNotNull('reviewed_at')
            ->selectRaw("'review' as kind, id as record_id, status, review_note as note, reviewed_at as occurred_at");
        $graduations = DB::table('training_enrollments as enrollments')->join('trainings', 'trainings.id', '=', 'enrollments.training_id')
            ->where('trainings.certification_body_id', $bodyId)->where('enrollments.status', 'completed')->whereNotNull('enrollments.completed_at')
            ->selectRaw("'graduation' as kind, enrollments.id as record_id, enrollments.status, trainings.title as note, enrollments.completed_at as occurred_at");
        $evidence = DB::table('external_certificates')->where('certification_body_id', $bodyId)->whereNotNull('processed_at')
            ->selectRaw("'evidence' as kind, id as record_id, status, reason as note, processed_at as occurred_at");

        return view('institution.service-history', [
            ...WorkspacePage::data($request, 'sertifikasi', 'riwayat', 'Riwayat Layanan'),
            'events' => DB::query()->fromSub($reviews->unionAll($graduations)->unionAll($evidence), 'events')
                ->latest('occurred_at')->orderBy('kind')->orderByDesc('record_id')->paginate(15),
        ]);
    }

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
