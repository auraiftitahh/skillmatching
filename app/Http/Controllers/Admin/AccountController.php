<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAccountStatusRequest;
use App\Models\AccountStatusChange;
use App\Models\User;
use App\Support\WorkspaceNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $filters = $this->filters($request);
        $query = User::query()->with('certificationBody');
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($search)).'%';
            $query->where(function ($query) use ($pattern): void {
                $query->whereRaw("LOWER(users.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(users.email) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('certificationBody', fn ($body) => $body->whereRaw("LOWER(certification_bodies.name) LIKE ? ESCAPE '!'", [$pattern]));
            });
        }
        if ($role = $filters['role'] ?? null) {
            $query->where('role', $role);
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }
        if (($filters['verification'] ?? null) === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif (($filters['verification'] ?? null) === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        $accounts = $query->orderByDesc('id')->paginate(15)->appends($filters);
        if ($accounts->total() > 0 && $accounts->currentPage() > $accounts->lastPage()) {
            return redirect()->route('admin.accounts.index', array_merge($filters, ['page' => $accounts->lastPage()]));
        }

        return view('admin.accounts.index', array_merge($this->layout($request, 'Pengelolaan Akun'), [
            'accounts' => $accounts,
            'filters' => $filters,
        ]));
    }

    public function show(Request $request, int $user): View
    {
        $filters = $this->filters($request);
        $request->validate(['history_page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $account = User::with('certificationBody')->findOrFail($user);

        return view('admin.accounts.show', array_merge($this->layout($request, 'Detail Akun'), [
            'backUrl' => route('admin.accounts.index', $filters),
            'account' => $account,
            'filters' => $filters,
            'changes' => AccountStatusChange::where('user_id', $account->id)->orderByDesc('id')->paginate(10, ['*'], 'history_page')->appends($filters),
        ]));
    }

    public function updateStatus(UpdateAccountStatusRequest $request, int $user): RedirectResponse
    {
        $filters = $this->filters($request);
        $data = $request->validated();
        DB::transaction(function () use ($request, $user, $data): void {
            $account = User::whereKey($user)->lockForUpdate()->firstOrFail();
            abort_if($account->role === User::ROLE_ADMIN, 403, 'Status akun Admin tidak dapat diubah melalui halaman ini.');
            if ($account->is_active !== (bool) $data['expected_is_active']) {
                throw ValidationException::withMessages(['action' => 'Status akun sudah berubah. Muat ulang halaman sebelum mencoba kembali.']);
            }
            $newStatus = $data['action'] === 'activate';
            if ($account->is_active === $newStatus) {
                throw ValidationException::withMessages(['action' => 'Status akun sudah sesuai. Tidak ada perubahan yang disimpan.']);
            }
            $before = $account->is_active;
            $account->update(['is_active' => $newStatus]);
            AccountStatusChange::create([
                'user_id' => $account->id,
                'actor_id' => $request->user()->id,
                'user_name' => $account->name,
                'user_email' => $account->email,
                'user_role' => $account->role,
                'actor_name' => $request->user()->name,
                'before_is_active' => $before,
                'after_is_active' => $newStatus,
                'reason' => $data['reason'],
            ]);
        });

        return redirect()->route('admin.accounts.show', array_merge(['user' => $user], $filters))
            ->with('success', 'Status akun berhasil diperbarui dan alasan perubahan telah dicatat.');
    }

    private function filters(Request $request): array
    {
        return Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in([User::ROLE_ADMIN, User::ROLE_APPLICANT, User::ROLE_INSTITUTION])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'verification' => ['nullable', Rule::in(['verified', 'unverified'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], [
            'q.max' => 'Pencarian maksimal 100 karakter.',
            'role.in' => 'Filter peran tidak valid.',
            'status.in' => 'Filter status akun tidak valid.',
            'verification.in' => 'Filter verifikasi email tidak valid.',
        ])->validate();
    }

    private function layout(Request $request, string $title): array
    {
        return [
            'pageTitle' => $title,
            'roleLabel' => 'Admin',
            'navigation' => WorkspaceNavigation::items('admin', 'akun'),
            'homeUrl' => route('workspace', ['role' => 'admin', 'page' => 'dashboard']),
            'backUrl' => route('workspace', ['role' => 'admin', 'page' => 'dashboard']),
            'userName' => $request->user()->name,
        ];
    }
}
