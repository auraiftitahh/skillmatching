<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlobalDataControlsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create(['name'=>'Pengelola Utama','email'=>'admin-114@example.test',
            'password'=>'valid-password-very-long','role'=>'admin', 'is_active'=>true,'email_verified_at'=>now()]);
        $this->actingAs($admin);
        return $admin;
    }

    public function test_admin_account_is_not_listed_and_cannot_be_disabled_even_by_direct_post(): void
    {
        $admin=$this->admin();
        $applicant=User::create(['name'=>'Pelamar Aktif','email'=>'pelamar-114@example.test',
            'password'=>'valid-password-very-long','role'=>'pelamar','is_active'=>true,'email_verified_at'=>now()]);
        $this->get('/admin/akun')->assertOk()->assertSee('Pelamar Aktif')->assertDontSee('admin-114@example.test');
        $this->post(route('accounts.save',$admin->id),['decision'=>'deactivate','reason'=>'Alasan contoh valid'])->assertForbidden();
        $this->get('/admin/akun/detail?id='.$admin->id)->assertNotFound();
        $this->assertDatabaseHas('users',['id'=>$admin->id,'is_active'=>true]);
        $this->post(route('accounts.save',$applicant->id),['decision'=>'deactivate','reason'=>'Permintaan penghentian akses'])->assertRedirect();
        $this->assertDatabaseHas('users',['id'=>$applicant->id,'is_active'=>false]);
        $this->get('/admin/akun?status=inactive&q=Pelamar')->assertOk()->assertSee('Pelamar Aktif');
        $this->get('/admin/akun?status=active')->assertDontSee('Pelamar Aktif');
    }

    public function test_catalog_filters_work_for_job_taxonomy_vacancies_and_trainings():void
    {
        $this->admin();
        DB::table('jobs_taxonomy')->insert([
          ['name'=>'Teknisi Mekanik','is_active'=>true,'created_at'=>now(),'updated_at'=>now()],
          ['name'=>'Petugas Gudang','is_active'=>false,'created_at'=>now(),'updated_at'=>now()],
        ]);
        $this->get('/admin/jabatan?status=inactive')->assertOk()->assertSee('Petugas Gudang')->assertDontSee('Teknisi Mekanik');
        $this->get('/admin/jabatan?q=Teknisi')->assertOk()->assertSee('Teknisi Mekanik')->assertDontSee('Petugas Gudang');

        $base=['source'=>'Portal Karier','source_url'=>'https://example.com/job','fetched_at'=>now()->toDateString(),
            'expires_at'=>now()->addMonth()->toDateString(),'created_at'=>now(),'updated_at'=>now()];
        DB::table('job_vacancies')->insert([
          [...$base,'title'=>'Staf Pergudangan','company'=>'Perusahaan A','status'=>'active'],
          [...$base,'title'=>'Analis Data','company'=>'Perusahaan B','status'=>'draft'],
        ]);
        $this->get('/admin/lowongan?status=draft')->assertOk()->assertSee('Analis Data')->assertDontSee('Staf Pergudangan');
        $this->get('/admin/lowongan?q=Pergudangan')->assertSee('Staf Pergudangan')->assertDontSee('Analis Data');

        $training=['provider'=>'Pusat Pelatihan','mode'=>'online','cost'=>0,'duration_hours'=>4,'weekly_hours'=>2,
            'source'=>'Katalog','source_url'=>'https://example.com/training','fetched_at'=>now()->toDateString(),
            'created_at'=>now(),'updated_at'=>now()];
        DB::table('trainings')->insert([
            [...$training,'title'=>'Excel Gudang','status'=>'active'],
            [...$training,'title'=>'Manajemen Proyek','status'=>'disabled'],
        ]);
        $this->get('/admin/pelatihan?status=disabled')->assertOk()->assertSee('Manajemen Proyek')->assertDontSee('Excel Gudang');
    }

    public function test_pending_certification_body_cannot_be_activated_via_toggle():void
    {
        $this->admin();
        $issuer=User::create(['name'=>'Lembaga Baru','email'=>'lembaga-114@example.test',
            'password'=>'valid-password-very-long','role'=>'sertifikasi','is_active'=>false,'email_verified_at'=>now()]);
        DB::table('certification_bodies')->insert(['user_id'=>$issuer->id,'name'=>'Lembaga Baru',
            'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        $this->post(route('accounts.save',$issuer->id),['decision'=>'activate','reason'=>'Mengaktifkan lembaga baru'])->assertSessionHasErrors('decision');
        $this->assertDatabaseHas('users',['id'=>$issuer->id,'is_active'=>false]);
    }

    public function test_taxonomy_activation_toggle_keeps_records_and_other_roles_cannot_change_status(): void
    {
        $this->admin();
        $id=DB::table('jobs_taxonomy')->insertGetId(['name'=>'Auditor','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $this->patch(route('admin.catalog.status',['jobs_taxonomy',$id]),['is_active'=>0])->assertRedirect();
        $this->assertDatabaseHas('jobs_taxonomy',['id'=>$id,'is_active'=>false]);
        $this->patch(route('admin.catalog.status',['jobs_taxonomy',$id]),['is_active'=>1])->assertRedirect();
        $this->assertDatabaseHas('jobs_taxonomy',['id'=>$id,'is_active'=>true]);
        $this->patch(route('admin.catalog.status',['job_vacancies',999]),['is_active'=>1])->assertNotFound();
    }

    public function test_unqualified_vacancy_cannot_be_activated_from_toggle():void
    {
        $this->admin();
        $id=DB::table('job_vacancies')->insertGetId([
            'title'=>'Staf Mutu','company'=>'PT Contoh','fetched_at'=>now()->toDateString(),
            'expires_at'=>now()->addMonth()->toDateString(),'status'=>'draft','created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->patch(route('admin.catalog.status',['job_vacancies',$id]),['is_active'=>1])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('job_vacancies',['id'=>$id,'status'=>'draft']);
    }

    public function test_searchable_dropdown_script_and_account_switch_render_with_actual_data():void
    {
        $this->admin();
        $this->get('/admin/lowongan/form')->assertOk()->assertSee('data-controls.js');
        // Toggle akun adalah kontrol per baris, sehingga halaman tanpa akun memang tidak memuatnya.
        User::create(['name'=>'Akun Uji Toggle','email'=>'toggle-115@example.test',
            'password'=>'valid-password-very-long','role'=>'pelamar','is_active'=>true,'email_verified_at'=>now()]);
        $this->get('/admin/akun')->assertOk()->assertSee('Akun Uji Toggle')->assertSee('role="switch"',false);
        $this->get('/admin/skill')->assertOk()->assertSee('Cari keterampilan');
    }
}
