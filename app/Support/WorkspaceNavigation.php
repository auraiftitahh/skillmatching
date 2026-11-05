<?php

namespace App\Support;

class WorkspaceNavigation
{
    public const PAGES = [
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
            'jabatan' => ['Pemetaan Jabatan', 'briefcase'],
            'lowongan' => ['Lowongan Kerja', 'briefcase'],
            'bobot' => ['Pengaturan Bobot', 'settings'],
            'aktivitas' => ['Riwayat Aktivitas', 'history'],
            'laporan' => ['Laporan', 'chart'],
        ],
        'sertifikasi' => [
            'dashboard' => ['Dashboard Badan Sertifikasi', 'home'],
            'status-akun' => ['Status Akun Lembaga', 'badge'],
            'pengajuan' => ['Pengajuan Pelatihan', 'book'],
            'pelatihan' => ['Pelatihan Terpublikasi', 'book'],
            'peserta' => ['Peserta Pelatihan', 'user'],
            'kelulusan' => ['Konfirmasi Kelulusan', 'badge'],
            'bukti-eksternal' => ['Bukti Eksternal', 'list'],
            'riwayat' => ['Riwayat Layanan', 'history'],
            'akun' => ['Profil Lembaga', 'settings'],
        ],
    ];

    public const OPERATIONAL = [
        'pelamar' => ['dashboard', 'profil', 'riwayat', 'skill', 'lowongan', 'pelatihan', 'rencana-belajar', 'simulasi', 'bukti-kompetensi'],
        'admin' => ['dashboard', 'akun', 'persetujuan-lembaga', 'pengajuan-pelatihan', 'skill', 'jabatan', 'lowongan', 'bobot', 'aktivitas'],
        'sertifikasi' => ['dashboard', 'status-akun', 'pengajuan', 'pelatihan', 'peserta', 'bukti-eksternal', 'riwayat'],
    ];

    public static function label(string $role): string
    {
        return match ($role) {
            'pelamar' => 'Pelamar',
            'admin' => 'Admin',
            'sertifikasi' => 'Badan Sertifikasi',
        };
    }

    public static function items(string $role, string $page, bool $preview = false): array
    {
        $items = [];
        foreach (self::PAGES[$role] as $slug => [$label, $icon]) {
            if (! $preview && ! in_array($slug, self::OPERATIONAL[$role], true)) {
                continue;
            }
            $items[] = [
                'label' => $label,
                'icon' => $icon,
                'url' => route($preview ? 'preview.workspace' : 'workspace', ['role' => $role, 'page' => $slug]),
                'active' => $slug === $page,
            ];
        }

        return $items;
    }
}
