<?php
namespace Tests\Feature;

use App\Models\{User,WeightConfiguration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Hash,Mail,URL};
use Tests\TestCase;

class RoleBusinessFlowAuditTest extends TestCase
{
    use RefreshDatabase;
    private User $applicant;
    private User $other;
    private User $admin;
    private int $skill;
    private int $secondSkill;
    private int $job;
    protected function setUp(): void
    {
        parent::setUp();Mail::fake();
        foreach(['applicant'=>'pelamar','other'=>'pelamar','admin'=>'admin'] as $property=>$role)$this->$property=User::create(['name'=>$property,'email'=>$property.'@business.test','password'=>'audit-password-123','role'=>$role,'is_active'=>true,'email_verified_at'=>now()]);
        WeightConfiguration::create(['version'=>1,'required_weight'=>3,'preferred_weight'=>1,'related_factor'=>.5,'threshold'=>40,'is_active'=>true]);
        $this->skill=DB::table('skills')->insertGetId(['name'=>'Inventori Uji','category'=>'Logistik','is_active'=>true]);
        $this->secondSkill=DB::table('skills')->insertGetId(['name'=>'Spreadsheet Uji','category'=>'Administrasi','is_active'=>true]);
        $this->job=DB::table('job_vacancies')->insertGetId(['title'=>'Staf Inventori Uji','company'=>'Perusahaan Uji','source'=>'Sumber Uji','source_url'=>'https://example.com/career','fetched_at'=>now(),'expires_at'=>now()->addMonth(),'status'=>'active']);
        DB::table('job_required_skills')->insert(['job_vacancy_id'=>$this->job,'skill_id'=>$this->skill,'minimum_level'=>2,'importance'=>'required','weight'=>3]);
    }
    public function test_applicant_profile_experience_skills_and_saved_matching_flow(): void
    {
        $this->actingAs($this->applicant)->post(route('profile.save'),['name'=>'Pelamar diperbarui','phone'=>'08123456789','domicile'=>'Surabaya','employment_status'=>'laid_off'])->assertRedirect();
        $this->assertDatabaseHas('users',['id'=>$this->applicant->id,'name'=>'Pelamar diperbarui']);
        $data=['type'=>'work','organization'=>'Gudang Uji','title'=>'Operator Inventori','start_date'=>'2023-01-01','end_date'=>'2024-01-01'];
        $this->post(route('experience.save'),$data)->assertRedirect('/pelamar/riwayat');
        $id=DB::table('user_experiences')->where('user_id',$this->applicant->id)->value('id');
        $this->post(route('experience.save'),[...$data,'id'=>$id,'title'=>'Operator Stok'])->assertRedirect();
        $this->actingAs($this->other)->post(route('experience.save'),[...$data,'id'=>$id])->assertNotFound();
        $this->delete(route('experience.delete',$id))->assertNotFound();
        $this->actingAs($this->applicant)->post(route('skills.save'),['skill_levels'=>[$this->skill=>2],'job_id'=>$this->job])->assertRedirect('/pelamar/lowongan/detail?job_id='.$this->job);
        $this->assertDatabaseHas('user_skills',['user_id'=>$this->applicant->id,'skill_id'=>$this->skill,'level'=>2]);
        $this->post(route('recommendations.snapshot'))->assertRedirect('/pelamar/lowongan/riwayat');
        $snapshot=DB::table('recommendation_histories')->first();$saved=$snapshot->results;
        $this->post(route('skills.save'),['skill_levels'=>[$this->skill=>1]])->assertRedirect();
        $this->assertSame($saved,DB::table('recommendation_histories')->find($snapshot->id)->results);
        $this->delete(route('experience.delete',$id))->assertRedirect();
        $this->assertDatabaseMissing('user_experiences',['id'=>$id]);
        $this->assertDatabaseHas('user_skills',['user_id'=>$this->applicant->id,'skill_id'=>$this->skill,'level'=>1]);
    }
    public function test_application_timeline_feedback_and_bookmark_are_personal(): void
    {
        $this->actingAs($this->applicant);
        $data=['job_title'=>'Staf Inventori Uji','company'=>'Perusahaan Uji','job_vacancy_id'=>$this->job,'status'=>'saved'];
        $this->post(route('applications.save'),$data)->assertRedirect();$id=DB::table('job_applications')->value('id');
        $this->post(route('applications.save'),[...$data,'id'=>$id,'status'=>'applied','applied_at'=>now()->toDateString(),'note'=>'Melamar di situs penyedia.'])->assertRedirect();
        $this->assertDatabaseCount('application_events',2);
        $this->actingAs($this->other)->post(route('applications.save'),[...$data,'id'=>$id])->assertNotFound();
        $this->actingAs($this->applicant)->post(route('feedback.save'),['job_vacancy_id'=>$this->job,'rating'=>'relevant','comment'=>'Sesuai kebutuhan.'])->assertRedirect();
        $this->assertDatabaseHas('feedback',['user_id'=>$this->applicant->id,'rating'=>'relevant']);
        $this->post(route('vacancy.bookmark',$this->job),['saved'=>1])->assertRedirect();
        $this->post(route('vacancy.bookmark',$this->job),['saved'=>1])->assertRedirect();
        $this->assertDatabaseCount('vacancy_bookmarks',1);
        $this->actingAs($this->other)->post(route('vacancy.bookmark',$this->job),['saved'=>0])->assertRedirect();
        $this->assertDatabaseCount('vacancy_bookmarks',1);
        $this->actingAs($this->applicant)->post(route('vacancy.bookmark',$this->job),['saved'=>0])->assertRedirect();
        $this->assertDatabaseCount('vacancy_bookmarks',0);
    }
    public function test_admin_taxonomy_relations_weights_evaluation_and_publication_flow(): void
    {
        $this->actingAs($this->admin)->post(route('catalog.save','jobs_taxonomy'),['name'=>'Jabatan audit','is_active'=>1])->assertRedirect();
        $taxonomy=DB::table('jobs_taxonomy')->where('name','Jabatan audit')->value('id');
        $this->post(route('mappings.save'),['job_taxonomy_id'=>$taxonomy,'skill_id'=>$this->skill])->assertRedirect();
        $mapping=DB::table('job_skills_mapping')->value('id');
        $this->delete(route('admin.relation.remove',['type'=>'job-mapping','id'=>$mapping]))->assertRedirect();
        $this->assertDatabaseMissing('job_skills_mapping',['id'=>$mapping]);
        $this->post(route('relations.save'),['skill_id'=>$this->skill,'related_skill_id'=>$this->secondSkill])->assertRedirect();
        $relation=DB::table('skill_relations')->value('id');
        $this->delete(route('admin.relation.remove',['type'=>'skill-relation','id'=>$relation]))->assertRedirect();
        $this->assertDatabaseMissing('skill_relations',['id'=>$relation]);
        $this->post(route('weights.save'),['required_weight'=>3,'preferred_weight'=>1,'related_factor'=>.5,'threshold'=>40,'reason'=>'Konfigurasi tinjauan baru.'])->assertRedirect('/admin/bobot');
        $this->assertDatabaseHas('weight_configurations',['version'=>1,'is_active'=>false]);
        $this->assertDatabaseHas('weight_configurations',['version'=>2,'is_active'=>true]);
        $this->post(route('evaluation.save','profile'),['code'=>'AUDIT-PROFILE','skill_ids'=>[$this->skill],'skill_levels'=>[2]])->assertRedirect();
        $profile=DB::table('evaluation_profiles')->where('code','AUDIT-PROFILE')->value('id');
        $this->post(route('evaluation.save','pair'),['evaluation_profile_id'=>$profile,'job_vacancy_id'=>$this->job,'expert_rating'=>4])->assertRedirect();
        $this->assertDatabaseHas('evaluation_pairs',['evaluation_profile_id'=>$profile,'system_score'=>100,'expert_rating'=>4]);
        $this->get(route('evaluation.export'))->assertOk()->assertDownload();
        $this->post(route('catalog.save','job_vacancies'),['title'=>'Lowongan audit baru','company'=>'Perusahaan','source'=>'Portal Uji','source_url'=>'https://example.com/new','fetched_at'=>now()->toDateString(),'expires_at'=>now()->addMonth()->toDateString(),'status'=>'draft'])->assertRedirect();
        $job=DB::table('job_vacancies')->where('title','Lowongan audit baru')->value('id');
        $this->patch(route('admin.catalog.status',['type'=>'job_vacancies','id'=>$job]),['is_active'=>1])->assertSessionHasErrors('status');
        $this->post(route('catalog.bulk',['kind'=>'vacancy','id'=>$job]),['items'=>[$this->skill=>['selected'=>'1','level'=>2,'importance'=>'required','weight'=>3]]])->assertRedirect();
        $this->patch(route('admin.catalog.status',['type'=>'job_vacancies','id'=>$job]),['is_active'=>1])->assertRedirect();
        $this->assertDatabaseHas('job_vacancies',['id'=>$job,'status'=>'active']);
    }
    public function test_institution_registration_requires_email_then_admin_approval(): void
    {
        $this->post('/register',['name'=>'Petugas Lembaga','email'=>'new-institution@audit.test','password'=>'audit-password-123','password_confirmation'=>'audit-password-123','role'=>'sertifikasi','body_name'=>'Lembaga Baru Uji'])->assertRedirect('/verification-notice');
        $user=User::where('email','new-institution@audit.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);$this->assertDatabaseHas('certification_bodies',['user_id'=>$user->id,'status'=>'pending']);
        $url=URL::temporarySignedRoute('verification.verify',now()->addMinutes(30),['id'=>$user->id,'hash'=>sha1($user->email)]);
        $this->get($url)->assertRedirect('/login');
        $this->actingAs($user->fresh())->get('/sertifikasi/dashboard')->assertRedirect('/sertifikasi/menunggu-verifikasi');
        $this->actingAs($this->admin)->post(route('accounts.save',$user->id),['decision'=>'verify','reason'=>'Identitas lembaga sesuai.'])->assertRedirect();
        $this->actingAs($user->fresh())->get('/sertifikasi/dashboard')->assertOk();
    }
    public function test_prerequisites_block_progress_until_completed_and_cycles_are_rejected(): void
    {
        $training=function($title){$id=DB::table('trainings')->insertGetId(['title'=>$title,'provider'=>'Mandiri','mode'=>'online','duration_hours'=>8,'weekly_hours'=>4,'cost'=>0,'fetched_at'=>now(),'status'=>'active']);DB::table('training_skills')->insert(['training_id'=>$id,'skill_id'=>$this->skill,'target_level'=>1]);return $id;};
        $a=$training('Prasyarat A');$b=$training('Lanjutan B');$c=$training('Lanjutan C');
        $this->actingAs($this->admin)->post(route('training.prerequisite',$b),['prerequisite_id'=>$a])->assertRedirect();
        $this->post(route('training.prerequisite',$c),['prerequisite_id'=>$b])->assertRedirect();
        $this->post(route('training.prerequisite',$a),['prerequisite_id'=>$c])->assertSessionHasErrors('prerequisite_id');
        $this->post(route('training.prerequisite',$b),['prerequisite_id'=>$b])->assertStatus(422);
        $this->actingAs($this->applicant)->post(route('plan.save'),['training_id'=>$b])->assertRedirect();
        $enrollment=DB::table('training_enrollments')->where('training_id',$b)->value('id');
        $this->post(route('enrollments.status',$enrollment),['status'=>'in_progress'])->assertStatus(422);
        $this->post(route('training.complete'),['training_id'=>$b])->assertStatus(422);
        $this->post(route('plan.save'),['training_id'=>$a])->assertRedirect();
        $this->post(route('training.complete'),['training_id'=>$a])->assertRedirect();
        $this->post(route('enrollments.status',$enrollment),['status'=>'in_progress'])->assertRedirect();
        $this->post(route('training.complete'),['training_id'=>$b])->assertRedirect();
        $this->assertDatabaseHas('training_enrollments',['id'=>$enrollment,'status'=>'completed']);
        $relation=DB::table('training_prerequisites')->where('training_id',$c)->value('id');
        $this->actingAs($this->admin)->delete(route('admin.relation.remove',['type'=>'training-prerequisite','id'=>$relation]))->assertRedirect();
        $this->assertDatabaseMissing('training_prerequisites',['id'=>$relation]);
    }
    public function test_csv_preview_accepts_1000_rows_and_invalid_replacement_clears_previous_preview(): void
    {
        $csv=function($count){$rows=['title,company,source,fetched_at'];foreach(range(1,$count) as $n)$rows[]='Posisi '.$n.',Perusahaan,Sumber,'.now()->toDateString();return implode("\n",$rows);};
        $this->actingAs($this->admin)->post(route('catalog.import','job_vacancies'),['file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('valid.csv',$csv(1000))])->assertRedirect();
        $this->assertCount(1000,session('import_preview.job_vacancies.rows'));
        $token=session('import_preview.job_vacancies.token');
        $this->post(route('catalog.import','job_vacancies'),['file'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('too-many.csv',$csv(1001))])->assertSessionHasErrors('file')->assertSessionMissing('import_preview.job_vacancies');
        $this->post(route('import.commit','job_vacancies'),['token'=>$token])->assertStatus(422);
        $this->assertDatabaseCount('job_vacancies',1);
    }
    public function test_certificate_upload_rejects_wrong_file_type_and_size_without_writing_data(): void
    {
        $request=['title'=>'Bukti upload uji','issuer_name'=>'Penerbit luar','skill_id'=>$this->skill,'level'=>2];
        $this->actingAs($this->applicant)->post(route('external-evidence.save'),[...$request,'file'=>\Illuminate\Http\UploadedFile::fake()->create('invalid.txt',10,'text/plain')])->assertSessionHasErrors('file');
        $this->post(route('external-evidence.save'),[...$request,'file'=>\Illuminate\Http\UploadedFile::fake()->create('large.pdf',5121,'application/pdf')])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('external_certificates',0);
    }
    public function test_password_change_validation_applies_to_each_role(): void
    {
        $institution=User::create(['name'=>'Institution','email'=>'institution@audit.test','role'=>'sertifikasi','password'=>'audit-password-123','is_active'=>true,'email_verified_at'=>now()]);
        DB::table('certification_bodies')->insert(['user_id'=>$institution->id,'name'=>'Lembaga','status'=>'active']);
        foreach([$this->applicant,$this->admin,$institution] as $user){
            $this->actingAs($user)->post(route('account.password'),['current_password'=>'wrong','password'=>'new-audit-password-456','password_confirmation'=>'new-audit-password-456'])->assertSessionHasErrors('current_password');
            $this->post(route('account.password'),['current_password'=>'audit-password-123','password'=>'new-audit-password-456','password_confirmation'=>'new-audit-password-456'])->assertRedirect();
            $this->assertTrue(Hash::check('new-audit-password-456',$user->fresh()->password));
        }
    }
}
