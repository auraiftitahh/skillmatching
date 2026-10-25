<?php

namespace App\Http\Controllers;

use App\Models\CertificationBody;
use App\Support\AccountDestination;
use App\Support\InstitutionReviewActions;
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
        $canOperate = $body?->status === CertificationBody::STATUS_ACTIVE;
        if ($canOperate && $request->routeIs('institution.status')) {
            return redirect()->to(AccountDestination::forUser($user));
        }

        $request->validate(['history_page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $changes = $body?->statusChanges()->orderByDesc('id')->paginate(10, ['*'], 'history_page');
        if ($changes && $changes->total() > 0 && $changes->currentPage() > $changes->lastPage()) {
            return redirect()->route('institution.account-status', ['history_page' => $changes->lastPage()]);
        }

        return view('workspace.institution-status', [
            'user' => $user,
            'body' => $body,
            'canOperate' => $canOperate,
            'statusLabel' => $body ? InstitutionReviewActions::LABELS[$body->status] : 'Profil belum tersedia',
            'statusMessage' => match ($body?->status) {
                CertificationBody::STATUS_PENDING => 'Pengajuan akun lembaga Anda sedang menunggu tinjauan Admin.',
                CertificationBody::STATUS_REJECTED => 'Pengajuan akun lembaga Anda belum disetujui.',
                CertificationBody::STATUS_DISABLED => 'Akses operasional lembaga Anda sedang dinonaktifkan.',
                CertificationBody::STATUS_ACTIVE => 'Lembaga Anda telah disetujui dan dapat mengakses layanan yang tersedia.',
                default => 'Profil lembaga belum tersedia. Hubungi Admin untuk melengkapi informasi akun.',
            },
            'nextStep' => match ($body?->status) {
                CertificationBody::STATUS_PENDING => 'Tunggu hasil tinjauan Admin. Gunakan Cek status terbaru untuk melihat perubahan keputusan.',
                CertificationBody::STATUS_REJECTED => 'Periksa alasan keputusan di bawah, lalu sampaikan klarifikasi kepada Admin. Persetujuan ulang hanya dapat dilakukan oleh Admin.',
                CertificationBody::STATUS_DISABLED => 'Periksa alasan penonaktifan di bawah dan hubungi Admin untuk tindak lanjut. Pengaktifan kembali dilakukan oleh Admin.',
                CertificationBody::STATUS_ACTIVE => 'Buka dashboard untuk melanjutkan. Anda tetap dapat melihat riwayat keputusan melalui menu Status Akun Lembaga.',
                default => 'Hubungi Admin untuk pemeriksaan profil lembaga. Anda belum dapat memakai layanan operasional sebelum profil dan persetujuannya tersedia.',
            },
            'latestDecision' => $body?->statusChanges()->orderByDesc('id')->first(),
            'changes' => $changes,
        ]);
    }
}
