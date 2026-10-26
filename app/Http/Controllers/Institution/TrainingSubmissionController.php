<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\TrainingSubmission;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TrainingSubmissionController extends Controller
{
    public function index(Request $r): View
    {
        $r->validate(['q' => 'nullable|string|max:100', 'status' => ['nullable', Rule::in(array_keys(TrainingSubmission::LABELS))]]);
        $q = TrainingSubmission::where('certification_body_id', $r->user()->certificationBody->id);
        if ($r->filled('status')) {
            $q->where('status', $r->input('status'));
        }

        return view('institution.submissions.index', [...WorkspacePage::data($r, 'sertifikasi', 'pengajuan', 'Pengajuan Pelatihan'), 'submissions' => $q->latest('id')->paginate(10)->withQueryString()]);
    }

    public function create(Request $r): View
    {
        return $this->form($r, new TrainingSubmission(['payload' => [], 'status' => 'draft']));
    }

    public function edit(Request $r, int $submission): View
    {
        return $this->form($r, $this->owned($r, $submission));
    }

    private function form(Request $r, TrainingSubmission $submission): View
    {
        abort_unless($submission->status === 'draft', 422, 'Draf ini tidak dapat diubah.');

        return view('institution.submissions.form', [...WorkspacePage::data($r, 'sertifikasi', 'pengajuan', $submission->exists ? 'Ubah Draf Pelatihan' : 'Buat Draf Pelatihan', route('institution.submissions.index')), 'submission' => $submission, 'payload' => $submission->payload ?? [], 'skills' => Skill::where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $r): RedirectResponse
    {
        return $this->save($r, null);
    }

    public function update(Request $r, int $submission): RedirectResponse
    {
        return $this->save($r, $this->owned($r, $submission));
    }

    private function save(Request $r, ?TrainingSubmission $submission): RedirectResponse
    {
        $data = $r->validate(['title' => 'required|string|max:190', 'description' => 'nullable|string|max:5000', 'mode' => ['nullable', Rule::in(['online', 'offline', 'hybrid'])], 'duration_hours' => 'nullable|integer|min:1|max:10000', 'weekly_hours' => 'nullable|integer|min:1|max:168', 'cost' => 'nullable|numeric|min:0|max:999999999', 'source_url' => 'nullable|url:http,https|max:1000', 'outcomes' => 'nullable|array|max:20', 'outcomes.*.skill_id' => ['nullable', Rule::in(array_merge(['new'], Skill::where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->all()))], 'outcomes.*.new_name' => 'nullable|string|max:150', 'outcomes.*.category' => 'nullable|string|max:150', 'outcomes.*.description' => 'nullable|string|max:1500', 'outcomes.*.target_level' => 'nullable|integer|between:1,4']);
        $id = DB::transaction(function () use ($r, $data, $submission): int {
            DB::table('certification_bodies')->where('id', $r->user()->certificationBody->id)->lockForUpdate()->first();
            if ($submission) {
                $submission = TrainingSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
                abort_unless($submission->status === 'draft', 422, 'Draf ini tidak dapat diubah.');
            }
            $submission ??= new TrainingSubmission(['certification_body_id' => $r->user()->certificationBody->id, 'status' => 'draft']);
            $submission->payload = $data;
            $submission->save();

            return $submission->id;
        });

        return redirect()->route('institution.submissions.show', $id)->with('success', 'Draf tersimpan. Pelatihan belum diterbitkan.');
    }

    public function show(Request $r, int $submission): View
    {
        return view('institution.submissions.show', [...WorkspacePage::data($r, 'sertifikasi', 'pengajuan', 'Detail Pengajuan Pelatihan', route('institution.submissions.index')), 'submission' => $this->owned($r, $submission)]);
    }

    private function owned(Request $r, int $id): TrainingSubmission
    {
        return TrainingSubmission::where('certification_body_id',$r->user()->certificationBody->id)->findOrFail($id);
    }
}
