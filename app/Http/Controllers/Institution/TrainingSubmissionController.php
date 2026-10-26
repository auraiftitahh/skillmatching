<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Models\Training;
use App\Models\TrainingSubmission;
use App\Support\TrainingSubmissionPayload;
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
        abort_unless(in_array($submission->status, ['draft', 'revision', 'rejected'], true), 422, 'Draf ini tidak dapat diubah.');

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
        $data = TrainingSubmissionPayload::validate($r->all());
        $id = DB::transaction(function () use ($r, $data, $submission): int {
            DB::table('certification_bodies')->where('id', $r->user()->certificationBody->id)->lockForUpdate()->first();
            if ($submission) {
                $submission = TrainingSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
                abort_unless(in_array($submission->status, ['draft', 'revision', 'rejected'], true), 422, 'Draf ini tidak dapat diubah.');
            }
            $submission ??= new TrainingSubmission(['certification_body_id' => $r->user()->certificationBody->id, 'status' => 'draft']);
            $submission->payload = $data;
            $submission->status = 'draft';
            $submission->save();

            return $submission->id;
        });

        return redirect()->route('institution.submissions.show', $id)->with('success', 'Draf tersimpan. Pelatihan belum diterbitkan.');
    }

    public function show(Request $r, int $submission): View
    {
        return view('institution.submissions.show', [...WorkspacePage::data($r, 'sertifikasi', 'pengajuan', 'Detail Pengajuan Pelatihan', route('institution.submissions.index')), 'submission' => $this->owned($r, $submission)]);
    }

    public function send(Request $r, int $submission): RedirectResponse
    {
        $owned = $this->owned($r, $submission);
        DB::transaction(function () use ($owned): void {
            DB::table('certification_bodies')->where('id', $owned->certification_body_id)->lockForUpdate()->first();
            $record = TrainingSubmission::whereKey($owned->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($record->status, ['draft', 'revision', 'rejected'], true), 422, 'Pengajuan sudah dikirim. Tunggu hasil tinjauan Admin.');
            $payload = TrainingSubmissionPayload::validate($record->payload, true);
            $record->update(['payload' => $payload, 'status' => 'pending', 'submitted_at' => now(), 'review_note' => null, 'reviewed_at' => null, 'reviewed_by' => null]);
        });

        return redirect()->route('institution.submissions.show', $owned)->with('success', 'Pengajuan terkirim. Tunggu tinjauan Admin sebelum pelatihan diterbitkan.');
    }

    public function published(Request $r): View
    {
        return view('institution.submissions.published', [...WorkspacePage::data($r, 'sertifikasi', 'pengajuan', 'Pelatihan Terbit Saya', route('institution.submissions.index')), 'trainings' => Training::where('certification_body_id', $r->user()->certificationBody->id)->latest('id')->paginate(10)]);
    }

    public function revise(Request $r, int $training): RedirectResponse
    {
        $id = DB::transaction(function () use ($r, $training): int {
            DB::table('certification_bodies')->where('id', $r->user()->certificationBody->id)->lockForUpdate()->first();
            $record = Training::where('certification_body_id', $r->user()->certificationBody->id)->lockForUpdate()->findOrFail($training);
            $existing = TrainingSubmission::where('training_id', $record->id)->whereIn('status', ['draft', 'pending', 'revision'])->first();
            if ($existing) {
                return $existing->id;
            }
            $payload = $record->only(['title', 'description', 'mode', 'duration_hours', 'weekly_hours', 'cost', 'source_url']);
            $payload['outcomes'] = $record->skills()->get()->map(fn ($s) => ['skill_id' => $s->id, 'target_level' => (int) $s->pivot->target_level])->all();

            return TrainingSubmission::create(['certification_body_id' => $record->certification_body_id, 'training_id' => $record->id, 'base_version' => $record->version, 'status' => 'draft', 'payload' => $payload])->id;
        });

        return redirect()->route('institution.submissions.show', $id)->with('success', 'Draf revisi tersedia. Katalog tetap memakai versi yang sudah disetujui.');
    }

    private function owned(Request $r, int $id): TrainingSubmission
    {
        return TrainingSubmission::where('certification_body_id', $r->user()->certificationBody->id)->findOrFail($id);
    }
}
