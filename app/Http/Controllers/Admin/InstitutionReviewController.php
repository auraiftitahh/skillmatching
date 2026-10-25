<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewInstitutionRequest;
use App\Models\CertificationBody;
use App\Models\InstitutionStatusChange;
use App\Models\User;
use App\Support\InstitutionReviewActions;
use App\Support\WorkspaceNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InstitutionReviewController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $filters = $this->filters($request);
        $query = CertificationBody::with('user');
        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($search)).'%';
            $query->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("LOWER(certification_bodies.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('user', function (Builder $user) use ($pattern): void {
                        $user->whereRaw("LOWER(users.email) LIKE ? ESCAPE '!'", [$pattern])
                            ->orWhereRaw("LOWER(users.name) LIKE ? ESCAPE '!'", [$pattern]);
                    });
            });
        }
        $bodies = $query->orderBy('id')->paginate(15)->appends($filters);
        if ($bodies->total() > 0 && $bodies->currentPage() > $bodies->lastPage()) {
            return redirect()->route('admin.institutions.index', array_merge($filters, ['page' => $bodies->lastPage()]));
        }

        return view('admin.institutions.index', array_merge($this->layout($request, 'Persetujuan Lembaga'), ['bodies' => $bodies, 'filters' => $filters]));
    }

    public function show(Request $request, int $body): View
    {
        $filters = $this->filters($request);
        $request->validate(['history_page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $institution = CertificationBody::with('user')->findOrFail($body);

        return view('admin.institutions.show', array_merge($this->layout($request, 'Tinjau Lembaga'), [
            'body' => $institution,
            'filters' => $filters,
            'backUrl' => route('admin.institutions.index', $filters),
            'actions' => $institution->user?->role === User::ROLE_INSTITUTION ? InstitutionReviewActions::forBody($institution) : [],
            'changes' => $institution->statusChanges()->orderByDesc('id')->paginate(10, ['*'], 'history_page')->appends($filters),
        ]));
    }

    public function decide(ReviewInstitutionRequest $request, int $body): RedirectResponse
    {
        $filters = $this->filters($request);
        $data = $request->validated();
        DB::transaction(function () use ($request, $body, $data): void {
            $institution = CertificationBody::whereKey($body)->lockForUpdate()->firstOrFail();
            if ($institution->user?->role !== User::ROLE_INSTITUTION) {
                throw ValidationException::withMessages(['action' => 'Pemilik lembaga bukan akun Badan Sertifikasi. Keputusan tidak dapat diproses.']);
            }
            if ($institution->status !== $data['expected_status']) {
                throw ValidationException::withMessages(['action' => 'Status lembaga sudah berubah. Periksa kondisi terbaru sebelum mengambil keputusan.']);
            }
            if (! array_key_exists($data['action'], InstitutionReviewActions::forBody($institution))) {
                throw ValidationException::withMessages(['action' => 'Keputusan ini tidak sesuai dengan status lembaga saat ini.']);
            }
            $before = $institution->status;
            $after = match ($data['action']) {
                'approve', 'activate' => CertificationBody::STATUS_ACTIVE,
                'reject' => CertificationBody::STATUS_REJECTED,
                'deactivate' => CertificationBody::STATUS_DISABLED,
            };
            $institution->status = $after;
            if ($data['action'] === 'approve') {
                $institution->verified_at = now();
            } elseif ($data['action'] === 'reject') {
                $institution->verified_at = null;
            }
            $institution->save();
            InstitutionStatusChange::create([
                'certification_body_id' => $institution->id,
                'actor_id' => $request->user()->id,
                'body_name' => $institution->name,
                'owner_email' => $institution->user->email,
                'actor_name' => $request->user()->name,
                'action' => $data['action'],
                'before_status' => $before,
                'after_status' => $after,
                'reason' => $data['reason'],
            ]);
        });

        return redirect()->route('admin.institutions.show', array_merge(['body' => $body], $filters))
            ->with('success', 'Keputusan lembaga berhasil disimpan beserta alasan dan petugas pemeriksa.');
    }

    private function filters(Request $request): array
    {
        $filters = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_merge(['all'], array_keys(InstitutionReviewActions::LABELS)))],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], ['status.in' => 'Filter status lembaga tidak valid.', 'q.max' => 'Pencarian maksimal 100 karakter.'])->validate();
        $filters['status'] = $filters['status'] ?? CertificationBody::STATUS_PENDING;

        return $filters;
    }

    private function layout(Request $request, string $title): array
    {
        return [
            'pageTitle' => $title, 'roleLabel' => 'Admin',
            'navigation' => WorkspaceNavigation::items('admin', 'persetujuan-lembaga'),
            'homeUrl' => route('workspace', ['role' => 'admin', 'page' => 'dashboard']),
            'backUrl' => route('workspace', ['role' => 'admin', 'page' => 'dashboard']),
            'userName' => $request->user()->name,
        ];
    }
}
