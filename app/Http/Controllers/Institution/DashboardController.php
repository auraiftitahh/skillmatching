<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\TrainingSubmission;
use App\Support\Publication;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $r): View
    {
        $body = $r->user()->certificationBody;
        $query = TrainingSubmission::where('certification_body_id', $body->id);

        return view('institution.dashboard', [...WorkspacePage::data($r, 'sertifikasi', 'dashboard', 'Dashboard Badan Sertifikasi'), 'body' => $body, 'counts' => (clone $query)->selectRaw('status,COUNT(*) as total')->groupBy('status')->pluck('total', 'status'), 'latest' => (clone $query)->latest('updated_at')->limit(5)->get(), 'publishedCount' => Publication::trainings()->where('certification_body_id', $body->id)->count()]);
    }
}
