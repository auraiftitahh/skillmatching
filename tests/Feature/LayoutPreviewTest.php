<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LayoutPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_preview_hub_links_to_three_roles_and_supporting_layouts(): void
    {
        $this->get('/preview')->assertOk()
            ->assertSee('Satu sistem, tiga ruang kerja.')
            ->assertSee('/preview/pelamar/dashboard')
            ->assertSee('/preview/admin/dashboard')
            ->assertSee('/preview/sertifikasi/dashboard')
            ->assertSee('/preview/auth/login');
    }

    #[DataProvider('workspacePages')]
    public function test_workspace_placeholder_renders_navigation_and_contextual_back_link(string $role, string $page, string $title): void
    {
        $back = $page === 'dashboard' ? '/preview' : '/preview/'.$role.'/dashboard';
        $this->get('/preview/'.$role.'/'.$page)->assertOk()
            ->assertSee($title)
            ->assertSee('Lewati ke konten utama')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Menu navigasi')
            ->assertSee('href="'.url($back).'"', false)
            ->assertSee('Fitur halaman ini akan tersedia');
    }

    public static function workspacePages(): array
    {
        return [
            ['pelamar', 'dashboard', 'Dashboard Pelamar'],
            ['pelamar', 'profil', 'Profil Saya'],
            ['pelamar', 'riwayat', 'Riwayat Pengalaman'],
            ['pelamar', 'skill', 'Keterampilan Saya'],
            ['pelamar', 'lowongan', 'Posisi Tujuan'],
            ['pelamar', 'pelatihan', 'Pelatihan'],
            ['pelamar', 'rencana-belajar', 'Rencana Belajar'],
            ['pelamar', 'simulasi', 'Simulasi Kecocokan'],
            ['pelamar', 'bukti-kompetensi', 'Bukti Kompetensi'],
            ['pelamar', 'pengaturan', 'Pengaturan'],
            ['admin', 'dashboard', 'Dashboard Admin'],
            ['admin', 'akun', 'Pengelolaan Akun'],
            ['admin', 'persetujuan-lembaga', 'Persetujuan Lembaga'],
            ['admin', 'pengajuan-pelatihan', 'Pengajuan Pelatihan'],
            ['admin', 'skill', 'Katalog Keterampilan'],
            ['admin', 'lowongan', 'Lowongan Kerja'],
            ['admin', 'bobot', 'Pengaturan Bobot'],
            ['admin', 'aktivitas', 'Riwayat Aktivitas'],
            ['admin', 'laporan', 'Laporan'],
            ['sertifikasi', 'dashboard', 'Dashboard Badan Sertifikasi'],
            ['sertifikasi', 'status-akun', 'Status Akun Lembaga'],
            ['sertifikasi', 'pengajuan', 'Pengajuan Pelatihan'],
            ['sertifikasi', 'peserta', 'Peserta Pelatihan'],
            ['sertifikasi', 'kelulusan', 'Konfirmasi Kelulusan'],
            ['sertifikasi', 'bukti-eksternal', 'Bukti Eksternal'],
            ['sertifikasi', 'riwayat', 'Riwayat Layanan'],
            ['sertifikasi', 'akun', 'Profil Lembaga'],
        ];
    }

    public function test_auth_preview_cannot_submit_credentials(): void
    {
        foreach (['login', 'register', 'forgot-password'] as $page) {
            $this->get('/preview/auth/'.$page)->assertOk()->assertSee('fieldset disabled', false)->assertSee('belum diaktifkan');
            $this->post('/preview/auth/'.$page, ['email' => 'test@example.test'])->assertStatus(405);
        }
    }

    public function test_unknown_roles_pages_and_auth_modes_are_not_rendered(): void
    {
        foreach (['/preview/owner/dashboard', '/preview/admin/not-found', '/preview/auth/not-found'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_all_preview_routes_are_unavailable_in_production(): void
    {
        $this->app->instance('env', 'production');
        foreach (['/preview', '/preview/admin/dashboard', '/preview/auth/login', '/preview/komponen'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_component_preview_has_labelled_validation_and_scrollable_table(): void
    {
        $this->get('/preview/komponen')->assertOk()
            ->assertSee('aria-describedby="sample-email-error"', false)
            ->assertSee('role="alert"', false)
            ->assertSee('Contoh tabel lembaga')
            ->assertSee('Data ilustrasi, bukan data akun.');
    }
}
