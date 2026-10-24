<?php

namespace App\Http\Controllers;

use App\Models\CertificationBody;
use App\Support\AccountDestination;
use App\Support\WorkspaceNavigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function show(Request $request, string $role, string $page = 'dashboard'): View
    {
        abort_unless(isset(WorkspaceNavigation::PAGES[$role][$page]), 404);

        return view('workspace.placeholder', [
            'roleLabel' => WorkspaceNavigation::label($role),
            'pageTitle' => WorkspaceNavigation::PAGES[$role][$page][0],
            'navigation' => WorkspaceNavigation::items($role, $page),
            'homeUrl' => route('workspace', ['role' => $role, 'page' => 'dashboard']),
            'backUrl' => $page === 'dashboard' ? null : route('workspace', ['role' => $role, 'page' => 'dashboard']),
            'userName' => $request->user()->name,
        ]);
    }

    public function institutionStatus(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $body = $user->certificationBody;
        if ($body?->status === CertificationBody::STATUS_ACTIVE) {
            return redirect()->to(AccountDestination::forUser($user));
        }

        return view('workspace.institution-status', [
            'statusMessage' => match ($body?->status) {
                CertificationBody::STATUS_PENDING => 'Pengajuan akun lembaga Anda sedang menunggu tinjauan Admin.',
                CertificationBody::STATUS_REJECTED => 'Pengajuan akun lembaga Anda belum disetujui. Hubungi Admin untuk informasi lebih lanjut.',
                CertificationBody::STATUS_DISABLED => 'Akses operasional lembaga Anda sedang dinonaktifkan. Hubungi Admin untuk informasi lebih lanjut.',
                default => 'Profil lembaga belum tersedia. Hubungi Admin untuk melengkapi informasi akun.',
            },
        ]);
    }
}
