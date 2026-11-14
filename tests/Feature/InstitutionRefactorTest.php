<?php
namespace Tests\Feature;

use App\Models\{User, WeightConfiguration};
use App\Services\{MatchingService, SkillEvidenceService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;

class InstitutionRefactorTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role,string $suffix=''): User
    {
        return User::create(['name'=>$role.$suffix,'email'=>$role.$suffix.'@test.local','password'=>'Password-test-123','role'=>$role,'is_active'=>true,'email_verified_at'=>now()]);
    }
    private function institution(string $suffix='',string $status='active'): array
    {
        $u=$this->user('sertifikasi',$suffix);
        $id=DB::table('certification_bodies')->insertGetId(['user_id'=>$u->id,'name'=>'Lembaga '.$suffix,'status'=>$status,'created_at'=>now(),'updated_at'=>now()]);
        return [$u,$id];
    }
    private function config(): void
    {
        WeightConfiguration::create(['version'=>1,'required_weight'=>3,'preferred_weight'=>1,'related_factor'=>.5,'threshold'=>40,'is_active'=>true]);
    }
    private function payload(): array
    {
        return ['title'=>'Praktik K3 Logistik','description'=>'Praktik kerja aman pada operasional gudang.','mode'=>'offline','duration_hours'=>8,'weekly_hours'=>4,'cost'=>0,'source_url'=>'https://example.com/training','intent'=>'submit','outcomes'=>[['new_name'=>'Praktik K3 Gudang','category'=>'Logistik','description'=>'Menerapkan prosedur keselamatan kerja dalam gudang.','target_level'=>2]]];
    }

    public function test_submission_is_not_public_until_reviewed_and_duplicate_name_reuses_skill(): void
    {
        [$institution,$body]=$this->institution();$admin=$this->user('admin');
        $this->actingAs($institution)->post(route('submissions.save'),$this->payload())->assertRedirect();
        $id=DB::table('training_submissions')->value('id');
        $this->assertDatabaseCount('skills',0);$this->assertDatabaseCount('trainings',0);
        $this->actingAs($admin)->post(route('submissions.review',$id),['decision'=>'approved','review_note'=>'Isi dan capaian telah ditinjau.'])->assertRedirect();
        $this->assertDatabaseCount('skills',1);$this->assertDatabaseHas('trainings',['certification_body_id'=>$body,'status'=>'active']);
        $payload=$this->payload();$payload['title']='K3 untuk Shift Kedua';$payload['outcomes'][0]['new_name']='  praktik   k3 gudang  ';
        $this->actingAs($institution)->post(route('submissions.save'),$payload)->assertRedirect();
        $second=DB::table('training_submissions')->max('id');
        $this->actingAs($admin)->post(route('submissions.review',$second),['decision'=>'approved','review_note'=>'Pemetaan kompetensi sama.'])->assertRedirect();
        $this->assertDatabaseCount('skills',1);$this->assertDatabaseCount('trainings',2);
        $this->post(route('submissions.review',$second),['decision'=>'approved','review_note'=>'Tidak boleh ditinjau ulang.'])->assertStatus(422);
    }
    public function test_pending_body_and_other_institution_cannot_edit_submission(): void
    {
        [$pending]=$this->institution('pending','pending');
        $this->actingAs($pending)->post(route('submissions.save'),$this->payload())->assertForbidden();
        [$owner]=$this->institution('owner');[$other]=$this->institution('other');
        $this->actingAs($owner)->post(route('submissions.save'),$this->payload())->assertRedirect();
        $id=DB::table('training_submissions')->value('id');
        $this->actingAs($other)->post(route('submissions.save'),['id'=>$id,...$this->payload()])->assertNotFound();
    }
    public function test_admin_cannot_create_new_skill_or_bypass_training_review(): void
    {
        $this->actingAs($this->user('admin'))->post(route('catalog.save','skills'),['name'=>'Tidak boleh','category'=>'Umum','is_active'=>1])->assertForbidden();
        $this->post(route('catalog.save','trainings'),[])->assertForbidden();
        $this->assertDatabaseCount('skills',0);
    }
    public function test_external_unregistered_issuer_keeps_proof_unverified_without_changing_profile(): void
    {
        Storage::fake('local');$pelamar=$this->user('pelamar');
        $skill=DB::table('skills')->insertGetId(['name'=>'K3','category'=>'Lintas bidang','is_active'=>true]);
        $this->actingAs($pelamar)->post(route('external-evidence.save'),['title'=>'Sertifikat eksternal','issuer_name'=>'Penerbit belum terdaftar','skill_id'=>$skill,'level'=>2,'file'=>UploadedFile::fake()->create('bukti.pdf',10,'application/pdf')])->assertRedirect();
        $this->assertDatabaseHas('external_certificates',['user_id'=>$pelamar->id,'status'=>'unverified','certification_body_id'=>null]);
        $this->assertDatabaseCount('user_skills',0);
        $id=DB::table('external_certificates')->value('id');
        $other=$this->user('pelamar','other');
        $this->actingAs($other)->get(route('external-evidence.file',$id))->assertNotFound();
    }
    public function test_only_issuer_can_verify_and_lower_proof_does_not_verify_higher_claim(): void
    {
        $pelamar=$this->user('pelamar');[$issuer,$body]=$this->institution('issuer');[$other]=$this->institution('other');
        $skill=DB::table('skills')->insertGetId(['name'=>'K3','category'=>'Umum','is_active'=>true]);
        DB::table('user_skills')->insert(['user_id'=>$pelamar->id,'skill_id'=>$skill,'level'=>4,'verification_status'=>'self_reported']);
        $proof=DB::table('external_certificates')->insertGetId(['user_id'=>$pelamar->id,'certification_body_id'=>$body,'issuer_name'=>'Penerbit','title'=>'Bukti','skill_id'=>$skill,'level'=>2,'file_path'=>'fake.pdf','status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
        $decision=['decision'=>'verified','reason'=>'Dokumen penerbit sesuai.'];
        $this->actingAs($other)->post(route('external-evidence.decision',$proof),$decision)->assertNotFound();
        $this->actingAs($issuer)->post(route('external-evidence.decision',$proof),$decision)->assertRedirect();
        $this->assertDatabaseHas('user_skills',['user_id'=>$pelamar->id,'skill_id'=>$skill,'level'=>4,'verification_status'=>'self_reported']);
        $this->assertDatabaseHas('skill_evidence',['user_id'=>$pelamar->id,'skill_id'=>$skill,'level'=>2]);
    }
    public function test_direct_skill_addition_does_not_reduce_related_contribution_in_database_service(): void
    {
        $this->config();$pelamar=$this->user('pelamar');
        $required=DB::table('skills')->insertGetId(['name'=>'Skill target','category'=>'Umum','is_active'=>true]);
        $related=DB::table('skills')->insertGetId(['name'=>'Skill terkait','category'=>'Umum','is_active'=>true]);
        DB::table('skill_relations')->insert(['skill_id'=>$required,'related_skill_id'=>$related]);
        $jobId=DB::table('job_vacancies')->insertGetId(['title'=>'Posisi uji','company'=>'Perusahaan','fetched_at'=>now(),'expires_at'=>now()->addMonth(),'status'=>'active']);
        DB::table('job_required_skills')->insert(['job_vacancy_id'=>$jobId,'skill_id'=>$required,'minimum_level'=>4,'weight'=>3,'importance'=>'required']);
        $job=\App\Models\JobVacancy::findOrFail($jobId);$svc=app(MatchingService::class);
        $this->assertSame(50.0,$svc->score($job,[$related=>4])['score']);
        $this->assertSame(50.0,$svc->score($job,[$related=>4,$required=>1])['score']);
        $this->assertSame(75.0,$svc->score($job,[$related=>4,$required=>3])['score']);
    }
    public function test_reviewed_revision_freezes_previous_participant_outcomes(): void
    {
        [$institution,$body]=$this->institution();$admin=$this->user('admin');$pelamar=$this->user('pelamar');
        $this->actingAs($institution)->post(route('submissions.save'),$this->payload());
        $first=DB::table('training_submissions')->value('id');
        $this->actingAs($admin)->post(route('submissions.review',$first),['decision'=>'approved','review_note'=>'Capaian awal disetujui.']);
        $training=DB::table('trainings')->first();$skill=DB::table('training_skills')->value('skill_id');
        $enrollment=DB::table('training_enrollments')->insertGetId(['user_id'=>$pelamar->id,'training_id'=>$training->id,'status'=>'waiting_confirmation','created_at'=>now(),'updated_at'=>now()]);
        $revision=$this->payload();$revision['training_id']=$training->id;$revision['outcomes']=[['skill_id'=>$skill,'target_level'=>4]];
        $this->actingAs($institution)->post(route('submissions.save'),$revision)->assertRedirect();
        $this->assertDatabaseHas('training_skills',['training_id'=>$training->id,'target_level'=>2]);
        $second=DB::table('training_submissions')->max('id');
        $this->actingAs($admin)->post(route('submissions.review',$second),['decision'=>'approved','review_note'=>'Revisi capaian baru disetujui.'])->assertRedirect();
        $this->assertDatabaseHas('training_skills',['training_id'=>$training->id,'target_level'=>4]);
        $snapshot=json_decode(DB::table('training_enrollments')->find($enrollment)->outcomes_snapshot,true);
        $this->assertSame(2,$snapshot[0]['target_level']);
        $this->actingAs($institution)->post(route('graduations.confirm',$enrollment))->assertRedirect();
        $this->assertDatabaseHas('user_skills',['user_id'=>$pelamar->id,'skill_id'=>$skill,'level'=>2,'verification_status'=>'verified']);
    }

    public function test_new_views_render_and_back_link_is_not_a_nonexistent_skill_route(): void
    {
        $this->config();$admin=$this->user('admin');[$institution]=$this->institution();$pelamar=$this->user('pelamar');
        $this->actingAs($institution)->get('/sertifikasi/pengajuan/form')->assertOk()->assertSee('Kirim Pengajuan');
        $this->post(route('submissions.save'),$this->payload());$id=DB::table('training_submissions')->value('id');
        $this->get('/sertifikasi/pengajuan/detail?id='.$id)->assertOk();
        $this->actingAs($admin)->get('/admin/pengajuan/detail?id='.$id)->assertOk()->assertSee('Padanan katalog');
        $this->actingAs($pelamar)->get('/pelamar/skill/konfirmasi')->assertOk()->assertSee('href="/pelamar/riwayat"',false)->assertDontSee('href="/pelamar/skill"',false);
        $this->get('/pelamar/sertifikat/ajukan')->assertOk()->assertSee('Simpan bukti pelatihan eksternal');
    }
}
