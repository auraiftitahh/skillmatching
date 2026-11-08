<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobVacancy;
use App\Support\WorkspacePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JobImportController extends Controller
{
    private const SESSION_KEY = 'job_csv_preview';

    private const REQUIRED = ['title', 'company', 'location', 'description', 'employment_type', 'source_url', 'fetched_at'];

    public function show(Request $request): View
    {
        return view('admin.jobs.import', [
            ...WorkspacePage::data($request, 'admin', 'lowongan', 'Impor CSV Lowongan', route('admin.jobs.index')),
            'preview' => $request->session()->get(self::SESSION_KEY),
            'columns' => [...self::REQUIRED, 'source_name', 'expires_at'],
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:3072']);
        $stream = fopen($request->file('file')->getRealPath(), 'rb');
        abort_unless($stream, 422, 'File CSV tidak dapat dibaca.');

        try {
            $header = fgetcsv($stream, escape: '') ?: [];
            $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);
            if ($header !== []) {
                $header[0] = Str::replaceStart("\xEF\xBB\xBF", '', $header[0]);
            }
            if (array_diff(self::REQUIRED, $header) || count($header) !== count(array_unique($header))) {
                $this->invalid('Lengkapi semua kolom wajib dan pastikan nama kolom tidak duplikat.');
            }

            $rows = [];
            $seen = [];
            $line = 1;
            while (($values = fgetcsv($stream, escape: '')) !== false) {
                if (++$line > 1001) {
                    $this->invalid('CSV maksimal berisi 1.000 baris data.');
                }
                if ($values === [null]) {
                    continue;
                }
                $data = count($header) === count($values) ? array_combine($header, array_map(fn ($value) => trim((string) $value), $values)) : [];
                $validator = Validator::make($data, $this->rules());
                $errors = $validator->errors()->all();
                $valid = $errors === [] ? $validator->validated() : [];
                if ($valid !== []) {
                    $key = serialize([mb_strtolower($valid['title']), mb_strtolower($valid['company'])]);
                    if (isset($seen[$key]) || $this->exists($valid)) {
                        $errors[] = 'Judul dan perusahaan sudah ada.';
                    }
                    $seen[$key] = true;
                }
                $rows[] = ['line' => $line, 'title' => Str::limit($data['title'] ?? '', 190), 'data' => $errors === [] ? $valid : [], 'errors' => $errors];
            }
        } finally {
            fclose($stream);
        }

        if ($rows === []) {
            $this->invalid('CSV belum berisi data lowongan.');
        }

        $request->session()->put(self::SESSION_KEY, [
            'owner' => $request->user()->id, 'token' => (string) Str::uuid(),
            'expires' => now()->addMinutes(30)->timestamp, 'rows' => $rows,
        ]);

        return redirect()->route('admin.jobs.import.show');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|uuid']);
        $preview = $request->session()->get(self::SESSION_KEY);
        abort_unless($preview && $preview['owner'] === $request->user()->id
            && $preview['expires'] > now()->timestamp && hash_equals($preview['token'], $data['token']),
            422, 'Pratinjau tidak berlaku. Unggah ulang CSV.');

        $lock = Cache::lock('catalog-job-csv-import', 60);
        abort_unless($lock->get(), 409, 'Impor lain sedang berjalan. Coba konfirmasi lagi.');
        try {
            [$saved, $skipped] = $this->saveRows($preview['rows']);
            $request->session()->forget(self::SESSION_KEY);
        } finally {
            $lock->release();
        }

        return redirect()->route('admin.jobs.import.show')
            ->with('success', "{$saved} lowongan tersimpan sebagai draf.")
            ->with('import_skipped', $skipped);
    }

    private function saveRows(array $rows): array
    {
        return DB::transaction(function () use ($rows): array {
            $saved = 0;
            $skipped = [];
            foreach ($rows as $row) {
                if ($row['errors'] !== [] || $this->exists($row['data'])) {
                    $skipped[] = $row['line'];

                    continue;
                }
                JobVacancy::create([...$row['data'], 'status' => 'draft']);
                $saved++;
            }

            return [$saved, $skipped];
        });
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('admin.jobs.import.show')->with('success', 'Pratinjau dibatalkan.');
    }

    private function rules(): array
    {
        return [
            'title' => 'required|string|max:190',
            'company' => 'required|string|max:190',
            'location' => 'required|string|max:190',
            'description' => 'required|string|max:10000',
            'employment_type' => ['required', Rule::in(array_keys(JobVacancy::TYPES))],
            'source_url' => 'required|url:http,https|max:1000',
            'source_name' => 'nullable|string|max:190',
            'fetched_at' => 'required|date_format:Y-m-d|before_or_equal:'.now('Asia/Jakarta')->toDateString(),
            'expires_at' => 'nullable|date_format:Y-m-d|after_or_equal:fetched_at',
        ];
    }

    private function exists(array $data): bool
    {
        return JobVacancy::whereRaw('LOWER(title) = ?', [mb_strtolower($data['title'])])
            ->whereRaw('LOWER(company) = ?', [mb_strtolower($data['company'])])->exists();
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
