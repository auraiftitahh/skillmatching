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

class ParticipantController extends Controller
{
    public function index(Request $request): View
    {
        $participants = $this->participants($request)
            ->select('enrollments.*', 'trainings.title', 'users.name')
            ->orderByRaw("CASE WHEN enrollments.status = 'waiting_confirmation' THEN 0 ELSE 1 END")
            ->orderByDesc('enrollments.updated_at')->orderByDesc('enrollments.id')->paginate(12);

        return view('institution.participants', [
            ...WorkspacePage::data($request, 'sertifikasi', 'peserta', 'Peserta Pelatihan'),
            'participants' => $participants,
        ]);
    }

    public function confirm(Request $request, int $enrollment, ApplicantSkills $skills): RedirectResponse
    {
        $scope = $this->participants($request)->where('enrollments.id', $enrollment);
        $userId = $scope->value('enrollments.user_id');
        abort_unless($userId, 404);

        DB::transaction(function () use ($scope, $userId, $enrollment, $skills, $request): void {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $record = $scope->lockForUpdate()->first(['enrollments.*']);
            abort_unless($record, 404);
            if ($record->status === 'completed') {
                return;
            }
            abort_unless($record->status === 'waiting_confirmation', 422, 'Peserta belum mengajukan penyelesaian.');
            $snapshot = $record->outcomes_snapshot;
            if ($snapshot === null) {
                $version = DB::table('training_versions')->where('training_id', $record->training_id)
                    ->where('version', $record->training_version)->value('snapshot');
                $outcomes = json_decode($version ?? 'null', true)['outcomes'] ?? null;
            } else {
                $outcomes = json_decode($snapshot, true);
            }
            abort_unless(is_array($outcomes) && $outcomes !== [], 422, 'Data capaian versi pelatihan belum tersedia.');
            foreach ($outcomes as $outcome) {
                $skills->recordEvidence((int) $userId, (int) $outcome['skill_id'], (int) $outcome['target_level'], 'catalog_graduation', $enrollment, $request->user()->id);
            }
            DB::table('training_enrollments')->where('id', $enrollment)->update([
                'status' => 'completed',
                'completed_at' => now(),
                'confirmed_by' => $request->user()->id,
                'updated_at' => now(),
            ]);
        }, 3);

        return redirect()->route('institution.participants.index')->with('status', 'Kelulusan dikonfirmasi. Capaian telah tercatat di profil peserta.');
    }

    private function participants(Request $request): Builder
    {
        return DB::table('training_enrollments as enrollments')
            ->join('trainings', 'trainings.id', '=', 'enrollments.training_id')
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->where('trainings.certification_body_id', $request->user()->certificationBody->id)
            ->where('users.is_active', true)->whereNotNull('users.email_verified_at')
            ->whereIn('enrollments.status', ['in_progress', 'waiting_confirmation', 'completed']);
    }
}
