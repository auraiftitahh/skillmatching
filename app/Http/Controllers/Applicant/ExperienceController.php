<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\SaveExperienceRequest;
use App\Models\UserExperience;
use App\Support\WorkspaceNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExperienceController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $filters = $this->filters($request);
        $query = $request->user()->experiences();
        if ($filters['type'] !== 'all') {
            $query->where('type', $filters['type']);
        }
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($search)).'%';
            $query->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("LOWER(organization) LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        $experiences = $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(10)->appends($filters);
        if ($experiences->total() > 0 && $experiences->currentPage() > $experiences->lastPage()) {
            return redirect()->route('applicant.experiences.index', array_merge($filters, ['page' => $experiences->lastPage()]));
        }

        return view('applicant.experiences.index', array_merge($this->layout($request, 'Riwayat Pengalaman'), [
            'filters' => $filters, 'experiences' => $experiences,
            'totalExperiences' => $request->user()->experiences()->count(),
        ]));
    }

    public function create(Request $request): View
    {
        $filters = $this->filters($request);
        $experience = $request->user()->experiences()->make(['type' => $filters['type'] === 'all' ? 'work' : $filters['type'], 'is_current' => false]);

        return $this->form($request, $experience, $filters);
    }

    public function store(SaveExperienceRequest $request): RedirectResponse
    {
        $data = $this->data($request);
        $request->user()->experiences()->create($data);

        return redirect()->route('applicant.experiences.index', ['type' => $data['type']])->with('success', 'Riwayat berhasil ditambahkan.');
    }

    public function edit(Request $request, int $experience): View
    {
        return $this->form($request, $request->user()->experiences()->findOrFail($experience), $this->filters($request));
    }

    public function update(SaveExperienceRequest $request, int $experience): RedirectResponse
    {
        $record = $request->user()->experiences()->findOrFail($experience);
        $data = $this->data($request);
        $record->update($data);

        return redirect()->route('applicant.experiences.edit', ['experience' => $record->id, 'type' => $data['type']])->with('success', 'Riwayat berhasil diperbarui.');
    }

    public function destroy(Request $request, int $experience): RedirectResponse
    {
        $filters = $this->filters($request);
        $request->user()->experiences()->findOrFail($experience)->delete();

        return redirect()->route('applicant.experiences.index', $filters)->with('success', 'Riwayat berhasil dihapus.');
    }

    private function data(SaveExperienceRequest $request): array
    {
        $data = $request->validated();
        $data['is_current'] = $request->boolean('is_current');
        $data['start_date'] = $data['start_date'] ?? null;
        $data['end_date'] = $data['is_current'] ? null : ($data['end_date'] ?? null);
        $data['description'] = $data['description'] ?? null;

        return $data;
    }

    private function form(Request $request, UserExperience $experience, array $filters): View
    {
        return view('applicant.experiences.form', array_merge($this->layout($request, $experience->exists ? 'Ubah Riwayat' : 'Tambah Riwayat'), [
            'experience' => $experience, 'filters' => $filters,
            'backUrl' => route('applicant.experiences.index', $filters),
        ]));
    }

    private function filters(Request $request): array
    {
        $filters = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(array_merge(['all'], array_keys(UserExperience::TYPES)))],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], ['type.in' => 'Filter jenis riwayat tidak valid.', 'q.max' => 'Pencarian maksimal 100 karakter.'])->validate();
        $filters['type'] = $filters['type'] ?? 'all';

        return $filters;
    }

    private function layout(Request $request, string $title): array
    {
        return [
            'pageTitle' => $title, 'roleLabel' => 'Pelamar',
            'navigation' => WorkspaceNavigation::items('pelamar', 'riwayat'),
            'homeUrl' => route('workspace', ['role' => 'pelamar', 'page' => 'dashboard']),
            'backUrl' => route('workspace', ['role' => 'pelamar', 'page' => 'dashboard']),
            'userName' => $request->user()->name,
        ];
    }
}
