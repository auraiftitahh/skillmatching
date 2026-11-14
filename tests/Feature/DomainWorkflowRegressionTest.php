<?php
namespace Tests\Feature;

use App\Models\{Certificate, User, WeightConfiguration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DomainWorkflowRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'name'=>'Pengguna '.$role,'email'=>$role.'@test.example',
            'password'=>'sandi-uji-minimum-123','role'=>$role,
            'is_active'=>true,'email_verified_at'=>now(),
        ]);
    }

    private function fixtures(string $bodyStatus='active'): array
    {
        WeightConfiguration::create([
            'version'=>1,'required_weight'=>3,'preferred_weight'=>1,
            'related_factor'=>0.5,'threshold'=>40,'is_active'=>true,
        ]);
        $pelamar=$this->user('pelamar');$sertifikasi=$this->user('sertifikasi');
        $bodyId=DB::table('certification_bodies')->insertGetId([
            'user_id'=>$sertifikasi->id,'name'=>'Badan Sertifikasi Uji',
            'status'=>$bodyStatus,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $skillId=DB::table('skills')->insertGetId([
            'name'=>'Pengoperasian Forklift','category'=>'Logistik','is_active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $trainingId=DB::table('trainings')->insertGetId([
            'title'=>'Forklift Dasar','provider'=>'Contoh','mode'=>'offline',
            'cost'=>0,'duration_hours'=>12,'weekly_hours'=>4,
            'fetched_at'=>now()->toDateString(),'status'=>'active',
            'certification_body_id'=>$bodyId,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('training_skills')->insert([
            'training_id'=>$trainingId,'skill_id'=>$skillId,'target_level'=>2,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return compact('pelamar','sertifikasi','bodyId','skillId','trainingId');
    }

    public function test_completed_training_cannot_be_reopened_by_adding_to_plan(): void
    {
        $f=$this->fixtures();
        DB::table('trainings')->where('id',$f['trainingId'])->update(['certification_body_id'=>null]);
        $this->actingAs($f['pelamar'])->post(route('plan.save'),['training_id'=>$f['trainingId']])->assertRedirect('/pelamar/rencana-belajar');
        $this->post(route('training.complete'),['training_id'=>$f['trainingId']])->assertRedirect('/pelamar/pelatihan/aktivitas');
        $this->assertDatabaseHas('training_enrollments',[
            'user_id'=>$f['pelamar']->id,'training_id'=>$f['trainingId'],'status'=>'completed',
        ]);
        $this->post(route('plan.save'),['training_id'=>$f['trainingId']])->assertSessionHasErrors('training_id');
        $this->assertDatabaseHas('user_skills',[
            'user_id'=>$f['pelamar']->id,'skill_id'=>$f['skillId'],
            'level'=>2,'verification_status'=>'self_reported',
        ]);
    }

    public function test_training_with_active_body_waits_until_body_confirms(): void
    {
        $f=$this->fixtures();
        $this->actingAs($f['pelamar'])->post(route('plan.save'),['training_id'=>$f['trainingId']]);
        $this->post(route('training.complete'),['training_id'=>$f['trainingId']])->assertRedirect('/pelamar/pelatihan/aktivitas');
        $e=DB::table('training_enrollments')->where('user_id',$f['pelamar']->id)->first();
        $this->assertSame('waiting_confirmation',$e->status);
        $this->actingAs($f['sertifikasi'])->post(route('graduations.confirm',$e->id))->assertRedirect();
        $this->assertDatabaseHas('training_enrollments',['id'=>$e->id,'status'=>'completed']);
        $this->assertDatabaseHas('user_skills',[
            'user_id'=>$f['pelamar']->id,'skill_id'=>$f['skillId'],
            'level'=>2,'verification_status'=>'verified',
        ]);
    }

    public function test_lower_level_certificate_does_not_remove_prior_verification(): void
    {
        $f=$this->fixtures();
        DB::table('user_skills')->insert([
            'user_id'=>$f['pelamar']->id,'skill_id'=>$f['skillId'],
            'level'=>4,'verification_status'=>'verified',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $cert=Certificate::create([
            'user_id'=>$f['pelamar']->id,'certification_body_id'=>$f['bodyId'],
            'training_id'=>$f['trainingId'],'skill_id'=>$f['skillId'],
            'title'=>'Forklift Dasar','file_path'=>'certificates/sample.pdf',
            'level'=>2,'status'=>'pending',
        ]);
        $this->actingAs($f['sertifikasi'])->post(route('certificates.decision',$cert->id),[
            'decision'=>'verified',
        ])->assertRedirect();
        $this->assertDatabaseHas('user_skills',[
            'user_id'=>$f['pelamar']->id,'skill_id'=>$f['skillId'],
            'level'=>4,'verification_status'=>'verified',
        ]);
    }
}
