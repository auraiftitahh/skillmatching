<?php

namespace Tests\Feature;

use App\Models\{Skill, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SkillCatalogAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function loginAdmin(): User
    {
        $user = User::create([
            'name' => 'Admin Uji', 'email' => 'admin-catalog@example.test',
            'password' => 'kata-sandi-pengujian-aman', 'role' => 'admin',
            'is_active' => true, 'email_verified_at' => now(),
        ]);
        $this->actingAs($user);
        return $user;
    }

    public function test_inactive_skill_remains_visible_and_can_be_filtered(): void
    {
        $this->loginAdmin();
        Skill::create(['name' => 'Forklift Gudang', 'category' => 'Logistik', 'aliases'=>['Forklift'], 'is_active' => false]);
        Skill::create(['name' => 'Pengelolaan Stok', 'category' => 'Logistik', 'is_active' => true]);

        $this->get('/admin/skill')->assertOk()->assertSee('Forklift Gudang')->assertSee('Nonaktif');
        $this->get('/admin/skill?status=inactive')->assertOk()
            ->assertSee('Forklift Gudang')->assertDontSee('Pengelolaan Stok');
        $this->get('/admin/skill?status=active')->assertOk()
            ->assertSee('Pengelolaan Stok')->assertDontSee('Forklift Gudang');
        $this->get('/admin/skill?q=Forklift')->assertOk()
            ->assertSee('Forklift Gudang')->assertDontSee('Pengelolaan Stok');
        $this->get('/admin/skill?q=Logistik&status=inactive')->assertOk()
            ->assertSee('Forklift Gudang')->assertDontSee('Pengelolaan Stok');
        $this->assertDatabaseCount('skills', 2);
    }

    public function test_editing_skill_to_inactive_does_not_delete_it(): void
    {
        $this->loginAdmin();
        $skill = Skill::create(['name' => 'Administrasi Gudang', 'category' => 'Logistik', 'is_active'=>true]);
        $this->post(route('catalog.save', 'skills'), [
            'id' => $skill->id, 'name' => $skill->name, 'category' => 'Logistik',
            'aliases' => 'Inventory Admin', 'is_active' => '0',
        ])->assertRedirect('/admin/skill');
        $this->assertDatabaseHas('skills', ['id'=>$skill->id, 'is_active'=>false]);
        $this->get('/admin/skill')->assertOk()->assertSee('Administrasi Gudang');
        $this->get('/admin/skill/form?id='.$skill->id)->assertOk()->assertSee('Administrasi Gudang');
    }

    public function test_skill_can_be_reactivated_from_list(): void
    {
        $this->loginAdmin();
        $skill = Skill::create(['name'=>'K3 Gudang','category'=>'Logistik','is_active'=>false]);
        $this->patch(route('admin.skills.status', $skill->id), ['is_active'=>'1'])->assertRedirect('/admin/skill');
        $this->assertDatabaseHas('skills', ['id'=>$skill->id,'is_active'=>true]);
        $this->get('/admin/skill?status=active')->assertSee('K3 Gudang');
    }

    public function test_unused_skill_can_be_deleted_with_audit_record(): void
    {
        $this->loginAdmin();
        $skill = Skill::create(['name'=>'Belum Dipakai','category'=>'Lainnya','is_active'=>false]);
        $this->delete(route('admin.skills.destroy',$skill->id))->assertRedirect('/admin/skill');
        $this->assertDatabaseMissing('skills',['id'=>$skill->id]);
        $this->assertDatabaseHas('audit_logs',['action'=>'skill.delete','subject_id'=>$skill->id]);
    }

    public function test_skill_used_in_a_vacancy_cannot_be_permanently_deleted(): void
    {
        $this->loginAdmin();
        $skill = Skill::create(['name'=>'Skill Digunakan','category'=>'Logistik','is_active'=>true]);
        $jobId = DB::table('job_vacancies')->insertGetId([
            'title'=>'Staf Logistik', 'company'=>'Perusahaan Uji',
            'fetched_at'=>now()->toDateString(), 'expires_at'=>now()->addMonth()->toDateString(),
            'status'=>'active', 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        DB::table('job_required_skills')->insert([
            'job_vacancy_id'=>$jobId,'skill_id'=>$skill->id,'minimum_level'=>2,
            'importance'=>'required','weight'=>3,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->delete(route('admin.skills.destroy', $skill->id))->assertSessionHasErrors('hapus');
        $this->assertDatabaseHas('skills',['id'=>$skill->id]);
        $this->patch(route('admin.skills.status',$skill->id),['is_active'=>'0'])->assertRedirect('/admin/skill');
        $this->assertDatabaseHas('job_required_skills',['skill_id'=>$skill->id]);
        $this->assertDatabaseHas('skills',['id'=>$skill->id,'is_active'=>false]);
    }

    public function test_audit_activity_is_readable_instead_of_raw_programming_text(): void
    {
        $admin = $this->loginAdmin();
        $user = User::create([
            'name'=>'Pelamar Uji','email'=>'pelamar-audit@example.test',
            'password'=>'kata-sandi-pengujian-aman','role'=>'pelamar','is_active'=>true,
            'email_verified_at'=>now(),
        ]);
        DB::table('audit_logs')->insert([
            'user_id'=>$admin->id, 'action'=>'account.deactivate',
            'subject_type'=>'users', 'subject_id'=>$user->id,
            'details'=>json_encode(['reason'=>'Akun diminta dinonaktifkan']),
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $this->get('/admin/aktivitas')->assertOk()
            ->assertSee('Menonaktifkan akun')
            ->assertSee('Admin Uji')
            ->assertSee('Pelamar Uji')
            ->assertSee('Akun diminta dinonaktifkan')
            ->assertDontSee('account.deactivate')
            ->assertDontSee('users #')
            ->assertDontSee('&quot;reason&quot;');
        $this->get('/admin/dashboard')->assertOk()->assertSee('Menonaktifkan akun')->assertDontSee('account.deactivate');
    }

    public function test_non_admin_cannot_change_or_delete_skill(): void
    {
        $skill = Skill::create(['name'=>'Skill Aman','category'=>'Umum','is_active'=>true]);
        $user = User::create([
            'name'=>'Pelamar','email'=>'pelamar-skill@example.test',
            'password'=>'kata-sandi-pengujian-aman','role'=>'pelamar','is_active'=>true,
            'email_verified_at'=>now(),
        ]);
        $this->actingAs($user);
        $this->patch(route('admin.skills.status',$skill->id),['is_active'=>0])->assertForbidden();
        $this->delete(route('admin.skills.destroy',$skill->id))->assertForbidden();
        $this->assertDatabaseHas('skills',['id'=>$skill->id,'is_active'=>true]);
    }

    public function test_skill_in_saved_recommendation_snapshot_cannot_be_deleted(): void
    {
        $admin = $this->loginAdmin();
        $skill = Skill::create(['name'=>'Riwayat Lama','category'=>'Umum','is_active'=>false]);
        $weight = DB::table('weight_configurations')->insertGetId([
            'version'=>1,'required_weight'=>3,'preferred_weight'=>1,
            'related_factor'=>0.5,'threshold'=>40,'is_active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('recommendation_histories')->insert([
            'user_id'=>$admin->id,'weight_configuration_id'=>$weight,
            'profile_snapshot'=>json_encode([$skill->id=>2]),'results'=>'[]',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->delete(route('admin.skills.destroy',$skill->id))->assertSessionHasErrors('hapus');
        $this->assertDatabaseHas('skills',['id'=>$skill->id]);
    }

    public function test_evaluation_profile_form_uses_skill_selection_instead_of_raw_json(): void
    {
        $this->loginAdmin();
        $skill=Skill::create(['name'=>'Analisis Stok','category'=>'Logistik','is_active'=>true]);
        $this->get('/admin/evaluasi/profil')->assertOk()
            ->assertSee('Pilih keterampilan')->assertDontSee('skills_json');
        $this->post(route('evaluation.save','profile'),[
            'code'=>'PROFIL-01','group'=>'Logistik','skill_ids'=>[$skill->id],
            'skill_levels'=>[3],
        ])->assertRedirect();
        $profile=DB::table('evaluation_profiles')->where('code','PROFIL-01')->first();
        $this->assertNotNull($profile);
        $this->assertSame(3,(int)(json_decode($profile->skills_json,true)[$skill->id]??0));
        $this->get('/admin/evaluasi/profil')->assertOk()
            ->assertSee('Analisis Stok')->assertSee('Mahir')
            ->assertDontSee('&quot;'.$skill->id.'&quot;');
    }
}
