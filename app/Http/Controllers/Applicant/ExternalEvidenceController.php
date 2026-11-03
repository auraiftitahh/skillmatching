<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\CertificationBody;
use App\Models\Skill;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ExternalEvidenceController extends Controller
{
    public function index(Request $request): View
    {
        return view('applicant.evidence', [
            ...WorkspacePage::data($request, 'pelamar', 'bukti-kompetensi', 'Bukti Kompetensi'),
            'records' => DB::table('external_certificates as evidence')
                ->join('skills', 'skills.id', '=', 'evidence.skill_id')
                ->where('evidence.user_id', $request->user()->id)
                ->select('evidence.*', 'skills.name as skill')->latest('evidence.id')->paginate(10),
            'issuers' => $this->issuers()->orderBy('name')->get(),
            'skills' => Skill::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedEvidence($request, true);
        $path = $request->file('file')->store('external-certificates', 'local');
        abort_unless($path, 500, 'Dokumen belum dapat disimpan.');
        try {
            DB::table('external_certificates')->insert([
                ...$data, 'file_path' => $path, 'user_id' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return redirect()->route('applicant.evidence.index')->with('status', 'Bukti disimpan. Level keterampilan berubah setelah bukti disetujui.');
    }

    public function download(Request $request, int $evidence): StreamedResponse
    {
        $record = DB::table('external_certificates')->where('user_id', $request->user()->id)->find($evidence);
        abort_unless($record && Storage::disk('local')->exists($record->file_path), 404);

        return Storage::disk('local')->download($record->file_path, 'bukti-'.$record->id.'.'.pathinfo($record->file_path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff']);
    }

    public function update(Request $request, int $evidence): RedirectResponse
    {
        $data = $this->validatedEvidence($request, false);
        $disk = Storage::disk('local');
        $newPath = $request->file('file')?->store('external-certificates', 'local');
        abort_if($newPath === false, 500, 'Dokumen belum dapat disimpan.');
        try {
            $oldPath = DB::transaction(function () use ($request, $evidence, $data, $disk, $newPath): string {
                $query = DB::table('external_certificates')->where('user_id', $request->user()->id)->where('id', $evidence);
                $record = $query->lockForUpdate()->first();
                abort_unless($record, 404);
                abort_unless(in_array($record->status, ['unverified', 'cancelled'], true), 422, 'Bukti ini tidak dapat diubah.');
                $path = $newPath ?? $record->file_path;
                abort_unless($disk->exists($path), 422, 'Unggah dokumen pengganti terlebih dahulu.');
                $query->update([...$data, 'file_path' => $path, 'updated_at' => now()]);

                return $record->file_path;
            }, 3);
        } catch (Throwable $error) {
            if ($newPath) {
                $disk->delete($newPath);
            }
            throw $error;
        }
        if ($newPath) {
            $disk->delete($oldPath);
        }

        return redirect()->route('applicant.evidence.index')->with('status', 'Perubahan bukti tersimpan.');
    }

    public function withdraw(Request $request, int $evidence): RedirectResponse
    {
        $query = DB::table('external_certificates')->where('user_id', $request->user()->id)->where('id', $evidence);
        abort_unless((clone $query)->exists(), 404);
        $updated = $query->where('status', 'pending')->update([
            'status' => 'cancelled', 'certification_body_id' => null, 'updated_at' => now(),
        ]);
        abort_unless($updated, 422, 'Hanya pengajuan yang masih menunggu pemeriksaan dapat ditarik.');

        return redirect()->route('applicant.evidence.index')->with('status', 'Pengajuan ditarik. Bukti tersimpan secara pribadi.');
    }

    private function validatedEvidence(Request $request, bool $fileRequired): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:190',
            'certification_body_id' => 'nullable|integer|min:1',
            'issuer_name' => [Rule::excludeIf($request->filled('certification_body_id')), 'required_without:certification_body_id', 'string', 'max:190'],
            'skill_id' => ['required', 'integer', Rule::exists('skills', 'id')->where('is_active', true)],
            'level' => 'required|integer|between:1,4',
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $issuer = $request->filled('certification_body_id') ? $this->issuers()->findOrFail($data['certification_body_id']) : null;

        return [
            'title' => $data['title'], 'skill_id' => $data['skill_id'], 'level' => $data['level'],
            'certification_body_id' => $issuer?->id,
            'issuer_name' => $issuer?->name ?? $data['issuer_name'],
            'status' => $issuer ? 'pending' : 'unverified',
        ];
    }

    private function issuers(): Builder
    {
        return CertificationBody::where('status', CertificationBody::STATUS_ACTIVE)
            ->whereHas('user', fn ($query) => $query->where('is_active', true)->whereNotNull('email_verified_at'));
    }
}
