<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JobApplicationController extends Controller
{
    private const STATUSES = [
        'saved' => 'Direncanakan', 'applied' => 'Dikirim', 'processing' => 'Dalam proses',
        'interview' => 'Wawancara', 'accepted' => 'Diterima', 'rejected' => 'Ditolak',
    ];

    public function index(Request $request): View
    {
        $records = DB::table('job_applications')->where('user_id', $request->user()->id)->latest('updated_at')->orderByDesc('id')->paginate(10);

        return view('applicant.applications', [
            ...WorkspacePage::data($request, 'pelamar', 'lamaran', 'Catatan Lamaran'),
            'records' => $records,
            'events' => DB::table('application_events')->whereIn('job_application_id', $records->pluck('id'))->latest('id')->get()->groupBy('job_application_id'),
            'statuses' => self::STATUSES,
        ]);
    }

    public function save(Request $request, ?int $application = null): RedirectResponse
    {
        $data = $request->validate([
            'job_title' => 'required|string|max:190', 'company' => 'required|string|max:190',
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'applied_at' => 'nullable|date_format:Y-m-d', 'note' => 'nullable|string|max:2000',
        ]);
        DB::transaction(function () use ($request, $application, $data): void {
            $fields = [...$data, 'applied_at' => $data['applied_at'] ?? null, 'note' => $data['note'] ?? null, 'updated_at' => now()];
            if ($application !== null) {
                $query = DB::table('job_applications')->where('user_id', $request->user()->id)->where('id', $application);
                $previous = $query->lockForUpdate()->first();
                abort_unless($previous, 404);
                $query->update($fields);
                if ($previous->status === $data['status'] && $previous->note === $fields['note']) {
                    return;
                }
            } else {
                $application = DB::table('job_applications')->insertGetId([
                    ...$fields, 'user_id' => $request->user()->id, 'created_at' => now(),
                ]);
            }
            DB::table('application_events')->insert([
                'job_application_id' => $application, 'status' => $data['status'], 'note' => $fields['note'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);

        return redirect()->route('applicant.applications.index')->with('status', 'Catatan lamaran disimpan.');
    }
}
