<?php

namespace Database\Seeders;

use App\Models\CertificationBody;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $ownerEmail = trim((string) config('skillmatching.seed.owner_email'));
        $owner = User::where('email', $ownerEmail)->first();
        if (! filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Isi OWNER_EMAIL dengan alamat email yang valid.');
        }
        if ($owner && $owner->role !== User::ROLE_ADMIN) {
            throw new RuntimeException('OWNER_EMAIL sudah dipakai akun non-Admin; tidak ada akun yang diubah.');
        }

        $ownerPassword = (string) config('skillmatching.seed.owner_password');
        if (! $owner && strlen($ownerPassword) < 12) {
            throw new RuntimeException('Isi OWNER_PASSWORD minimal 12 karakter sebelum membuat Admin.');
        }

        $includeDemo = config('skillmatching.seed.demo_enabled')
            && (! app()->isProduction() || config('skillmatching.seed.allow_demo_in_production'));
        $demoPassword = (string) config('skillmatching.seed.demo_password');
        if ($includeDemo && strlen($demoPassword) < 12) {
            throw new RuntimeException('Isi DEMO_PASSWORD minimal 12 karakter sebelum membuat akun contoh.');
        }

        DB::transaction(function () use ($ownerEmail, $ownerPassword, $includeDemo, $demoPassword): void {
            User::firstOrCreate(['email' => $ownerEmail], [
                'name' => 'Admin Platform',
                'password' => $ownerPassword,
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            if (! $includeDemo) {
                return;
            }

            foreach ([
                ['Nadia Pelamar Baru', 'baru@example.test', true, true],
                ['Pelamar Belum Verifikasi', 'belum.verifikasi@example.test', true, false],
                ['Pelamar Nonaktif', 'nonaktif@example.test', false, true],
            ] as [$name, $email, $active, $verified]) {
                User::firstOrCreate(['email' => $email], [
                    'name' => $name,
                    'password' => $demoPassword,
                    'role' => User::ROLE_APPLICANT,
                    'is_active' => $active,
                    'email_verified_at' => $verified ? now() : null,
                ]);
            }

            foreach ([
                ['Lembaga Logistik Nusantara', 'kontak@logistiknusantara.id', CertificationBody::STATUS_ACTIVE, true],
                ['Lembaga Menunggu Persetujuan', 'lembaga.pending@example.test', CertificationBody::STATUS_PENDING, true],
                ['Lembaga Ditolak', 'lembaga.rejected@example.test', CertificationBody::STATUS_REJECTED, true],
                ['Lembaga Dinonaktifkan', 'lembaga.disabled@example.test', CertificationBody::STATUS_DISABLED, true],
                ['Lembaga Belum Verifikasi Email', 'lembaga.unverified@example.test', CertificationBody::STATUS_ACTIVE, false],
            ] as [$name, $email, $status, $verified]) {
                $user = User::firstOrCreate(['email' => $email], [
                    'name' => $name,
                    'password' => $demoPassword,
                    'role' => User::ROLE_INSTITUTION,
                    'is_active' => true,
                    'email_verified_at' => $verified ? now() : null,
                ]);
                if ($user->role !== User::ROLE_INSTITUTION) {
                    throw new RuntimeException('Alamat akun lembaga contoh sudah digunakan role lain; perubahan dibatalkan.');
                }
                $user->certificationBody()->firstOrCreate([], [
                    'name' => $name,
                    'contact' => $email,
                    'status' => $status,
                    'verified_at' => $status === CertificationBody::STATUS_ACTIVE ? now() : null,
                ]);
            }
        });
    }
}
