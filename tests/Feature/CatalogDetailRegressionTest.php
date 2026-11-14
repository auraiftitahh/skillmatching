<?php

namespace Tests\Feature;

use App\Models\{User, WeightConfiguration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogDetailRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        WeightConfiguration::create([
            'version' => 1, 'required_weight' => 3, 'preferred_weight' => 1,
            'related_factor' => .5, 'threshold' => 40, 'is_active' => true,
        ]);
    }

    private function logInAdmin(): User
    {
        $admin = User::create([
            'name' => 'Admin Uji Detail',
            'email' => 'admin-detail@example.test',
            'password' => 'valid-password-for-testing',
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin);
        return $admin;
    }

    public function test_jabatan_detail_opens_from_list_and_shows_associated_skills(): void
    {
        $this->logInAdmin();
        $jobId = DB::table('jobs_taxonomy')->insertGetId([
            'name' => 'Petugas Inventori', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $skillId = DB::table('skills')->insertGetId([
            'name' => 'Audit Stok', 'category' => 'Logistik', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('job_skills_mapping')->insert(['job_taxonomy_id' => $jobId, 'skill_id' => $skillId]);

        $this->get('/admin/jabatan')->assertOk()
            ->assertSee('/admin/jabatan/detail?id='.$jobId, false);
        $this->get('/admin/jabatan/detail?id='.$jobId)->assertOk()
            ->assertSee('Detail Jabatan')->assertSee('Petugas Inventori')
            ->assertSee('Audit Stok')->assertSee('/admin/jabatan/pemetaan?job_taxonomy_id='.$jobId, false);
        $this->get('/admin/jabatan/pemetaan?job_taxonomy_id='.$jobId)
            ->assertOk()->assertSee('Petugas Inventori')->assertSee('Audit Stok');
    }

    public function test_catalog_details_are_available_for_all_four_admin_catalogs(): void
    {
        $this->logInAdmin();
        $skillId = DB::table('skills')->insertGetId([
            'name' => 'Manajemen Arsip', 'category' => 'Administrasi', 'is_active' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $taxonomyId = DB::table('jobs_taxonomy')->insertGetId([
            'name' => 'Staf Administrasi', 'is_active' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $vacancyId = DB::table('job_vacancies')->insertGetId([
            'title' => 'Petugas Logistik', 'company' => 'PT Contoh', 'status' => 'draft',
            'source' => 'Portal Karier', 'fetched_at' => now()->toDateString(),
            'expires_at' => now()->addMonth()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $trainingId = DB::table('trainings')->insertGetId([
            'title' => 'Pelatihan Administrasi', 'provider' => 'Lembaga Uji',
            'mode' => 'online', 'cost' => 0, 'duration_hours' => 8, 'weekly_hours' => 2,
            'status' => 'draft', 'fetched_at' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('job_required_skills')->insert([
            'job_vacancy_id' => $vacancyId, 'skill_id' => $skillId,
            'minimum_level' => 2, 'importance' => 'required', 'weight' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('training_skills')->insert([
            'training_id' => $trainingId, 'skill_id' => $skillId, 'target_level' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([
            ['skill', $skillId, 'Manajemen Arsip'],
            ['jabatan', $taxonomyId, 'Staf Administrasi'],
            ['lowongan', $vacancyId, 'Petugas Logistik'],
            ['pelatihan', $trainingId, 'Pelatihan Administrasi'],
        ] as [$section, $id, $label]) {
            $this->get("/admin/{$section}/detail?id={$id}")
                ->assertOk()->assertSee($label);
            $this->get("/admin/{$section}/detail?id=999999")
                ->assertOk()->assertSee('tidak ditemukan');
        }
        $this->get('/admin/lowongan/detail?id='.$vacancyId)
            ->assertSee('Manajemen Arsip')->assertSee('Persyaratan Keterampilan');
        $this->get('/admin/pelatihan/detail?id='.$trainingId)
            ->assertSee('Manajemen Arsip')->assertSee('Mahir');
    }

    public function test_account_detail_is_not_limited_to_current_list_page(): void
    {
        $this->logInAdmin();
        $first = null;
        for ($i = 1; $i <= 18; $i++) {
            $user = User::create([
                'name' => 'Pengguna Detail '.sprintf('%02d', $i),
                'email' => "pengguna-detail-{$i}@example.test",
                'password' => 'valid-password-for-testing',
                'role' => 'pelamar', 'is_active' => true, 'email_verified_at' => now(),
            ]);
            if ($i === 1) $first = $user;
        }
        $this->get('/admin/akun?page=2')->assertOk();
        $this->get('/admin/akun/detail?id='.$first->id)->assertOk()
            ->assertSee($first->email)->assertSee('Status akses akun');
    }

    public function test_detail_pages_preserve_role_authorization(): void
    {
        $user = User::create([
            'name' => 'Pelamar Uji', 'email' => 'pelamar-detail@example.test',
            'password' => 'valid-password-for-testing', 'role' => 'pelamar',
            'is_active' => true, 'email_verified_at' => now(),
        ]);
        $this->actingAs($user)
            ->get('/admin/jabatan/detail?id=1')->assertForbidden();
        $this->get('/admin/akun/detail?id=1')->assertForbidden();
    }

    public function test_invalid_explicit_training_id_does_not_show_different_training(): void
    {
        $user = User::create([
            'name' => 'Pelamar Uji', 'email' => 'pelamar-training@example.test',
            'password' => 'valid-password-for-testing', 'role' => 'pelamar',
            'is_active' => true, 'email_verified_at' => now(),
        ]);
        DB::table('trainings')->insert([
            'title' => 'Pelatihan Pilihan', 'provider' => 'Lembaga Uji',
            'status' => 'active', 'cost' => 0, 'duration_hours' => 8, 'weekly_hours' => 2,
            'mode' => 'online', 'fetched_at' => now()->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($user)
            ->get('/pelamar/pelatihan/detail?training_id=999999')
            ->assertNotFound()->assertDontSee('Pelatihan Pilihan');
    }
}
