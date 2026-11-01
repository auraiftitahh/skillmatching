<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\InstitutionReviewActions;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function index(Request $r): View
    {
        $data = $r->validate(['type' => ['nullable', Rule::in(['akun', 'lembaga', 'pelatihan', 'bobot'])], 'page' => 'nullable|integer|min:1|max:100000']);
        $accounts = DB::table('account_status_changes')->selectRaw("'akun' as type,id as sequence,user_id as target_id,user_name as subject,actor_name as actor,reason as note,created_at,CASE WHEN after_is_active=1 THEN 'Akun diaktifkan' ELSE 'Akun dinonaktifkan' END as action");
        $bodies = DB::table('institution_status_changes')->selectRaw("'lembaga' as type,id as sequence,certification_body_id as target_id,body_name as subject,actor_name as actor,reason as note,created_at,action");
        $reviews = DB::table('training_submission_reviews as r')->join('users as u', 'u.id', '=', 'r.user_id')->selectRaw("'pelatihan' as type,r.id as sequence,r.training_submission_id as target_id,'Pengajuan pelatihan' as subject,u.name as actor,r.note,r.created_at,r.decision as action");
        $weights = DB::table('weight_configurations as w')->leftJoin('users as u', 'u.id', '=', 'w.created_by')->selectRaw("'bobot' as type,w.id as sequence,w.id as target_id,w.version as subject,COALESCE(u.name,'Sistem') as actor,w.reason as note,w.created_at,'Versi bobot diterbitkan' as action");
        $query = DB::query()->fromSub($accounts->unionAll($bodies)->unionAll($reviews)->unionAll($weights), 'activity');
        if (! empty($data['type'])) {
            $query->where('type', $data['type']);
        }
        $events = $query->orderByDesc('created_at')->orderBy('type')->orderByDesc('sequence')->paginate(15)->withQueryString();
        foreach ($events as $event) {
            $event->label = match ($event->type) {
                'lembaga' => InstitutionReviewActions::ACTION_LABELS[$event->action] ?? 'Keputusan lembaga','pelatihan' => ['approved' => 'Pelatihan disetujui', 'revision' => 'Pelatihan diminta perbaikan', 'rejected' => 'Pengajuan pelatihan ditolak'][$event->action] ?? 'Tinjauan pelatihan',default => $event->action
            };
            $event->url = match ($event->type) {
                'akun' => route('admin.accounts.show', $event->target_id),'lembaga' => route('admin.institutions.show', $event->target_id),'pelatihan' => route('admin.training-reviews.show', $event->target_id),'bobot' => route('admin.weights.show', $event->target_id)
            };
        }

        return view('admin.activity', [...WorkspacePage::data($r, 'admin', 'aktivitas', 'Riwayat Aktivitas'), 'events' => $events, 'type' => $data['type'] ?? '']);
    }
}
