<?php
namespace Tests\Feature;

use App\Models\{User,WeightConfiguration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;

class UiRevisionWorkflowTest extends TestCase
{
    use RefreshDatabase;
    private User $owner;
    private User $issuer;
    private int $body;
    private int $skill;

    protected function setUp(): void
    {
        parent::setUp();Storage::fake('local');Storage::disk('local')->put('external-certificates/proof.pdf','%PDF-1.4 test');
        $this->owner=$this->user('pelamar','owner');$this->issuer=$this->user('sertifikasi','issuer');
        $this->body=DB::table('certification_bodies')->insertGetId(['user_id'=>$this->issuer->id,'name'=>'Penerbit Uji','status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        $this->skill=DB::table('skills')->insertGetId(['name'=>'Kompetensi Uji','category'=>'Umum','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        WeightConfiguration::create(['version'=>1,'required_weight'=>3,'preferred_weight'=>1,'related_factor'=>.5,'threshold'=>40,'is_active'=>true]);
    }
    private function user(string $role,string $name): User
    {
        return User::create(['name'=>$name,'email'=>$name.'@example.test','role'=>$role,'password'=>'password-testing-123','email_verified_at'=>now(),'is_active'=>true]);
    }
    private function proof(string $status='unverified',bool $linked=false): int
    {
        return DB::table('external_certificates')->insertGetId(['user_id'=>$this->owner->id,'certification_body_id'=>$linked?$this->body:null,'title'=>'Bukti pribadi','issuer_name'=>'Penerbit Uji','skill_id'=>$this->skill,'level'=>2,'file_path'=>'external-certificates/proof.pdf','status'=>$status,'created_at'=>now(),'updated_at'=>now()]);
    }
    private function data(): array
    {
        return ['title'=>'Bukti diperbaiki','issuer_name'=>'Penerbit mandiri','skill_id'=>$this->skill,'level'=>3];
    }
    public function test_validation_messages_and_password_failure_do_not_leak_translation_keys(): void
    {
        $message=\Illuminate\Support\Facades\Validator::make([],['title'=>'required'])->errors()->first();
        $this->assertSame('Judul wajib diisi.',$message);
        $this->post('/reset-password',['token'=>'invalid-token','email'=>$this->owner->email,'password'=>'new-testing-password-123','password_confirmation'=>'new-testing-password-123'])->assertSessionHasErrors('email');
        $this->assertStringNotContainsString('passwords.',session('errors')->first('email'));
        $this->assertStringContainsString('Tautan',session('errors')->first('email'));
    }
    public function test_institution_history_contains_external_decisions_and_submission_reviews(): void
    {
        $id=$this->proof('verified',true);DB::table('external_certificates')->where('id',$id)->update(['processed_at'=>now(),'reason'=>'Dokumen sesuai.']);
        DB::table('training_submissions')->insert(['certification_body_id'=>$this->body,'payload'=>json_encode(['title'=>'Pengajuan disetujui uji','outcomes'=>[]]),'status'=>'approved','review_note'=>'Telah ditinjau.','reviewed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $this->actingAs($this->issuer)->get('/sertifikasi/riwayat')->assertOk()->assertSee('Bukti pribadi')->assertSee('Pengajuan disetujui uji');
        $this->get('/sertifikasi/riwayat?status=approved')->assertOk()->assertSee('Pengajuan disetujui uji')->assertDontSee('Bukti pribadi');
    }
    public function test_edit_permissions_follow_every_evidence_status(): void
    {
        $this->actingAs($this->owner);
        foreach(['unverified','cancelled','pending','verified','rejected'] as $status){
            $id=$this->proof($status);
            $response=$this->put(route('external-evidence.update',$id),$this->data());
            if(in_array($status,['unverified','cancelled'])){
                $response->assertRedirect('/pelamar/bukti/detail?id='.$id);
                $this->assertDatabaseHas('external_certificates',['id'=>$id,'title'=>'Bukti diperbaiki','status'=>'unverified']);
            }else{
                $response->assertStatus(422);$this->assertDatabaseHas('external_certificates',['id'=>$id,'title'=>'Bukti pribadi','status'=>$status]);
            }
        }
    }
    public function test_link_requires_owner_explicit_consent_active_issuer_and_private_status(): void
    {
        $id=$this->proof();$request=['certification_body_id'=>$this->body,'confirm_issuer'=>'1'];
        $this->actingAs($this->owner)->post(route('external-evidence.link',$id),['certification_body_id'=>$this->body])->assertSessionHasErrors('confirm_issuer');
        $this->actingAs($this->user('pelamar','intruder'))->post(route('external-evidence.link',$id),$request)->assertNotFound();
        DB::table('certification_bodies')->where('id',$this->body)->update(['status'=>'pending']);
        $this->actingAs($this->owner)->post(route('external-evidence.link',$id),$request)->assertStatus(422);
        DB::table('certification_bodies')->where('id',$this->body)->update(['status'=>'active']);
        $this->post(route('external-evidence.link',$id),$request)->assertRedirect();
        $this->assertDatabaseHas('external_certificates',['id'=>$id,'certification_body_id'=>$this->body,'status'=>'pending']);
        $this->assertDatabaseCount('user_skills',0);
        foreach(['pending','verified','rejected'] as $status){$locked=$this->proof($status,true);$this->post(route('external-evidence.link',$locked),$request)->assertStatus(422);}
    }
    public function test_withdraw_removes_issuer_access_and_blocks_a_late_decision(): void
    {
        $id=$this->proof('pending',true);
        $this->actingAs($this->owner)->post(route('external-evidence.withdraw',$id))->assertRedirect();
        $this->assertDatabaseHas('external_certificates',['id'=>$id,'status'=>'cancelled','certification_body_id'=>null]);
        $this->actingAs($this->issuer)->get(route('external-evidence.file',$id))->assertNotFound();
        $this->post(route('external-evidence.decision',$id),['decision'=>'verified','reason'=>'Keputusan terlambat.'])->assertNotFound();
        $this->assertDatabaseCount('skill_evidence',0);
        $this->actingAs($this->owner)->get(route('external-evidence.file',$id))->assertOk();
        foreach(['unverified','cancelled','verified','rejected'] as $status){$other=$this->proof($status);$this->post(route('external-evidence.withdraw',$other))->assertStatus(422);}
    }
    public function test_rejected_proof_resubmits_without_overwriting_original_decision(): void
    {
        $id=$this->proof('rejected',true);
        DB::table('external_certificates')->where('id',$id)->update(['reason'=>'Dokumen perlu diperjelas','processed_at'=>now()]);
        $this->actingAs($this->owner)->get('/pelamar/bukti/form?source_certificate_id='.$id)->assertOk()->assertSee('pengajuan lama tetap tersimpan');
        $this->post(route('external-evidence.save'),[...$this->data(),'source_certificate_id'=>$id,'certification_body_id'=>$this->body])->assertRedirect();
        $this->assertDatabaseCount('external_certificates',2);
        $this->assertDatabaseHas('external_certificates',['id'=>$id,'status'=>'rejected','reason'=>'Dokumen perlu diperjelas']);
        $this->assertDatabaseHas('external_certificates',['title'=>'Bukti diperbaiki','status'=>'pending']);
    }
    public function test_manual_issuer_required_only_when_unregistered_and_never_overrides_selected_body(): void
    {
        $this->actingAs($this->owner);
        $file=fn()=>UploadedFile::fake()->create('sertifikat.pdf',10,'application/pdf');
        $this->post(route('external-evidence.save'),['title'=>'Tanpa penerbit','skill_id'=>$this->skill,'level'=>2,'file'=>$file()])->assertSessionHasErrors('issuer_name');
        $this->post(route('external-evidence.save'),[...$this->data(),'issuer_name'=>'Nama palsu di field tersembunyi','certification_body_id'=>$this->body,'file'=>$file()])->assertRedirect();
        $this->assertDatabaseHas('external_certificates',['title'=>'Bukti diperbaiki','issuer_name'=>'Penerbit Uji','status'=>'pending']);
        $response=$this->withSession(['_old_input'=>['certification_body_id'=>$this->body]])->get('/pelamar/sertifikat/ajukan')->assertOk();
        $response->assertSee('data-manual-issuer  hidden',false)->assertSee('js/conditional-fields.js');
    }
    public function test_all_new_actions_reject_wrong_roles_and_other_owners(): void
    {
        $id=$this->proof();$other=$this->user('pelamar','other');
        $this->actingAs($other)->get('/pelamar/bukti/form?id='.$id)->assertNotFound();
        $this->put(route('external-evidence.update',$id),$this->data())->assertNotFound();
        $this->post(route('external-evidence.withdraw',$id))->assertNotFound();
        $this->get(route('external-evidence.file',$id))->assertNotFound();
        foreach([$this->issuer,$this->user('admin','admin')] as $wrong){
            $this->actingAs($wrong)->put(route('external-evidence.update',$id),$this->data())->assertForbidden();
            $this->post(route('external-evidence.link',$id),['certification_body_id'=>$this->body,'confirm_issuer'=>1])->assertForbidden();
            $this->post(route('external-evidence.withdraw',$id))->assertForbidden();
        }
    }
    public function test_object_history_uses_readable_label_and_indonesian_date(): void
    {
        $admin=$this->user('admin','admin');
        DB::table('audit_logs')->insert(['user_id'=>$admin->id,'action'=>'skill.status','subject_type'=>'skills','subject_id'=>$this->skill,'details'=>'{}','created_at'=>'2026-10-25 12:09:35','updated_at'=>now()]);
        $this->actingAs($admin)->get('/admin/skill/detail?id='.$this->skill)->assertOk()->assertSee('Mengubah status keterampilan')->assertSee('25 Oktober 2026, 12.09')->assertDontSee('skill.status')->assertDontSee('2026-10-25 12:09:35');
    }
    public function test_rejected_pending_certificate_does_not_remove_other_verified_evidence(): void
    {
        DB::table('user_skills')->insert(['user_id'=>$this->owner->id,'skill_id'=>$this->skill,'level'=>2,'verification_status'=>'verified']);
        DB::table('skill_evidence')->insert(['user_id'=>$this->owner->id,'skill_id'=>$this->skill,'level'=>2,'source_type'=>'catalog_graduation','source_id'=>999,'confirmed_by'=>$this->issuer->id]);
        $id=DB::table('certificates')->insertGetId(['user_id'=>$this->owner->id,'certification_body_id'=>$this->body,'skill_id'=>$this->skill,'title'=>'Bukti tambahan','level'=>2,'file_path'=>'proof.pdf','status'=>'pending']);
        $this->actingAs($this->issuer)->post(route('certificates.decision',$id),['decision'=>'rejected','reason'=>'Dokumen tambahan tidak valid.'])->assertRedirect();
        $this->assertDatabaseHas('user_skills',['user_id'=>$this->owner->id,'skill_id'=>$this->skill,'level'=>2,'verification_status'=>'verified']);
    }
    public function test_self_completion_also_uses_frozen_enrollment_outcomes(): void
    {
        $id=DB::table('trainings')->insertGetId(['title'=>'Mandiri','provider'=>'Penyedia','mode'=>'online','duration_hours'=>8,'weekly_hours'=>4,'cost'=>0,'fetched_at'=>now(),'status'=>'active']);
        DB::table('training_skills')->insert(['training_id'=>$id,'skill_id'=>$this->skill,'target_level'=>4]);
        DB::table('training_enrollments')->insert(['user_id'=>$this->owner->id,'training_id'=>$id,'status'=>'planned','outcomes_snapshot'=>json_encode([['skill_id'=>$this->skill,'target_level'=>1]])]);
        $this->actingAs($this->owner)->post(route('training.complete'),['training_id'=>$id])->assertRedirect();
        $this->assertDatabaseHas('user_skills',['user_id'=>$this->owner->id,'skill_id'=>$this->skill,'level'=>1,'verification_status'=>'self_reported']);
    }
    public function test_rejected_enrollment_can_restart_but_cannot_bypass_active_plan_capacity(): void
    {
        $training=fn($title)=>DB::table('trainings')->insertGetId(['title'=>$title,'provider'=>'Lembaga','mode'=>'offline','duration_hours'=>8,'weekly_hours'=>4,'cost'=>0,'fetched_at'=>now(),'status'=>'active','certification_body_id'=>$this->body,'created_at'=>now(),'updated_at'=>now()]);
        $id=$training('Pelatihan ulang');DB::table('training_skills')->insert(['training_id'=>$id,'skill_id'=>$this->skill,'target_level'=>2]);
        DB::table('training_enrollments')->insert(['user_id'=>$this->owner->id,'training_id'=>$id,'status'=>'rejected']);
        $this->actingAs($this->owner)->post(route('plan.save'),['training_id'=>$id])->assertRedirect();
        $this->assertDatabaseHas('training_enrollments',['user_id'=>$this->owner->id,'training_id'=>$id,'status'=>'planned']);
        DB::table('training_enrollments')->where('training_id',$id)->update(['status'=>'rejected']);
        foreach(range(1,3) as $n){$other=$training('Aktif '.$n);DB::table('training_enrollments')->insert(['user_id'=>$this->owner->id,'training_id'=>$other,'status'=>'planned']);}
        $this->post(route('plan.save'),['training_id'=>$id])->assertSessionHasErrors('training_id');
        $this->assertDatabaseHas('training_enrollments',['training_id'=>$id,'status'=>'rejected']);
    }
}
