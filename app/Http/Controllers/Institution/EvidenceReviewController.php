<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApplicantSkills;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceReviewController extends Controller
{
    public function index(Request $request): View
    {
        return view('institution.evidence-reviews', [
            ...WorkspacePage::data($request, 'sertifikasi', 'bukti-eksternal', 'Pemeriksaan Bukti Eksternal'),
            'records' => $this->assigned($request)->join('skills', 'skills.id', '=', 'external_certificates.skill_id')
                ->join('users', 'users.id', '=', 'external_certificates.user_id')
                ->select('external_certificates.*', 'skills.name as skill', 'users.name as applicant')
                ->orderByRaw("CASE WHEN external_certificates.status = 'pending' THEN 0 ELSE 1 END")
                ->latest('external_certificates.id')->paginate(10),
        ]);
    }

    public function download(Request $request, int $evidence): StreamedResponse
    {
        $record = $this->assigned($request)->find($evidence);
        abort_unless($record && Storage::disk('local')->exists($record->file_path), 404);

        return Storage::disk('local')->download($record->file_path, 'bukti-'.$record->id.'.'.pathinfo($record->file_path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff']);
    }

    public function decide(Request $request, int $evidence, ApplicantSkills $skills): RedirectResponse
    {
        $data = $request->validate(['decision' => 'required|in:verified,rejected', 'reason' => 'required|string|min:5|max:2000']);
        $query = $this->assigned($request)->where('id', $evidence);
        $applicantId = (clone $query)->value('user_id');
        abort_unless($applicantId, 404);
        DB::transaction(function () use ($query, $applicantId, $skills, $data, $request): void {
            User::whereKey($applicantId)->where('is_active', true)->whereNotNull('email_verified_at')->lockForUpdate()->firstOrFail();
            $record = $query->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_unless($record->status === 'pending', 422, 'Pengajuan sudah diputuskan.');
            if ($data['decision'] === 'verified') {
                $skills->recordEvidence((int) $record->user_id, (int) $record->skill_id, (int) $record->level, 'external_certificate', (int) $record->id, $request->user()->id);
            }
            $query->update([
                'status' => $data['decision'], 'reason' => $data['reason'],
                'processed_by' => $request->user()->id, 'processed_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);

        return redirect()->route('institution.evidence.index')->with('status', 'Keputusan bukti tersimpan.');
    }

    private function assigned(Request $request): Builder
    {
        return DB::table('external_certificates')->where('external_certificates.certification_body_id', $request->user()->certificationBody->id);
    }
}
