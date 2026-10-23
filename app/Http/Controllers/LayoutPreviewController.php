<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class LayoutPreviewController extends Controller
{
    private const PAGES = [
        'pelamar' => [
            'dashboard' => ['Dashboard Pelamar', 'home'],
            'profil' => ['Profil Saya', 'user'],
            'riwayat' => ['Riwayat Pengalaman', 'history'],
            'skill' => ['Keterampilan Saya', 'spark'],
            'lowongan' => ['Posisi Tujuan', 'briefcase'],
            'pelatihan' => ['Pelatihan', 'book'],
            'rencana-belajar' => ['Rencana Belajar', 'list'],
            'simulasi' => ['Simulasi Kecocokan', 'chart'],
            'bukti-kompetensi' => ['Bukti Kompetensi', 'badge'],
            'pengaturan' => ['Pengaturan', 'settings'],
        ],
        'admin' => [
            'dashboard' => ['Dashboard Admin', 'home'],
            'akun' => ['Pengelolaan Akun', 'user'],
            'persetujuan-lembaga' => ['Persetujuan Lembaga', 'badge'],
            'pengajuan-pelatihan' => ['Pengajuan Pelatihan', 'book'],
            'skill' => ['Katalog Keterampilan', 'spark'],
            'lowongan' => ['Lowongan Kerja', 'briefcase'],
            'bobot' => ['Pengaturan Bobot', 'settings'],
            'aktivitas' => ['Riwayat Aktivitas', 'history'],
            'laporan' => ['Laporan', 'chart'],
        ],
        'sertifikasi' => [
            'dashboard' => ['Dashboard Badan Sertifikasi', 'home'],
            'pengajuan' => ['Pengajuan Pelatihan', 'book'],
            'peserta' => ['Peserta Pelatihan', 'user'],
            'kelulusan' => ['Konfirmasi Kelulusan', 'badge'],
            'bukti-eksternal' => ['Bukti Eksternal', 'list'],
            'riwayat' => ['Riwayat Layanan', 'history'],
            'akun' => ['Profil Lembaga', 'settings'],
        ],
    ];

    public function index(): View
    {
        $this->ensureLocalPreview();

        return view('preview.index');
    }

    public function workspace(string $role, string $page = 'dashboard'): View
    {
        $this->ensureLocalPreview();
        abort_unless(isset(self::PAGES[$role][$page]), 404);

        $roleLabel = match ($role) {
            'pelamar' => 'Pelamar',
            'admin' => 'Admin',
            'sertifikasi' => 'Badan Sertifikasi',
        };
        $navigation = [];
        foreach (self::PAGES[$role] as $slug => [$label, $icon]) {
            $navigation[] = [
                'label' => $label,
                'icon' => $icon,
                'url' => route('preview.workspace', ['role' => $role, 'page' => $slug]),
                'active' => $page === $slug,
            ];
        }

        return view('preview.workspace', [
            'roleLabel' => $roleLabel,
            'pageTitle' => self::PAGES[$role][$page][0],
            'navigation' => $navigation,
            'homeUrl' => route('preview.workspace', ['role' => $role, 'page' => 'dashboard']),
        ]);
    }

    public function authentication(string $page): View
    {
        $this->ensureLocalPreview();
        abort_unless(in_array($page, ['login', 'register', 'forgot-password'], true), 404);

        return view('preview.authentication', [
            'mode' => $page,
            'pageTitle' => match ($page) {
                'login' => 'Selamat datang kembali',
                'register' => 'Mulai perjalanan karier Anda',
                'forgot-password' => 'Lupa kata sandi?',
            },
        ]);
    }

    public function components(): View
    {
        $this->ensureLocalPreview();

        return view('preview.components');
    }

    private function ensureLocalPreview(): void
    {
        abort_unless(app()->environment('local', 'testing'), 404);
    }
}
