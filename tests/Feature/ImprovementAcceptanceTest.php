<?php
namespace Tests\Feature;

use App\Models\{User,JobVacancy,WeightConfiguration};
use App\Services\MatchingService;
use App\Support\Publication;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage,Mail};
use Tests\TestCase;

class ImprovementAcceptanceTest extends TestCase
{
 use RefreshDatabase;
 protected function setUp():void {
  parent::setUp();putenv('OWNER_EMAIL=admin@skillmatching.local');putenv('OWNER_PASSWORD=SkillmatchingDemo2026!');putenv('DEMO_PASSWORD=SkillmatchingDemo2026!');putenv('DEMO_SEED=true');
  Storage::fake('local');Mail::fake();$this->seed(DatabaseSeeder::class);
 }
 protected function tearDown():void {foreach(['OWNER_EMAIL','OWNER_PASSWORD','DEMO_PASSWORD','DEMO_SEED'] as $key)putenv($key);parent::tearDown();}
 private function user(string $email='raka@example.com'):User {return User::where('email',$email)->firstOrFail();}
 private function job():JobVacancy {return JobVacancy::where('title','Staf Gudang')->firstOrFail();}
 private function training(string $title='Forklift Dasar'):int {return (int)DB::table('trainings')->where('title',$title)->value('id');}

 public function test_seed_rerun_does_not_duplicate_or_reset_existing_configuration():void {
  $tables=['users','certificates','evaluation_pairs','training_enrollments','job_applications','recommendation_histories'];$counts=[];foreach($tables as $t)$counts[$t]=DB::table($t)->count();
  $cfg=WeightConfiguration::where('is_active',true)->first();$cfg->update(['threshold'=>67]);$this->seed(DatabaseSeeder::class);
  foreach($tables as $t)$this->assertSame($counts[$t],DB::table($t)->count(),$t);
  $this->assertSame(67,(int)$cfg->fresh()->threshold);Mail::assertNothingSent();
 }
 public function test_demo_fixture_matches_55_to_85_and_zero_to_30_without_mutation():void {
  $svc=app(MatchingService::class);$raka=$this->user();$before=$svc->levels($raka);$result=$svc->simulate($raka,$this->job(),[$this->training()]);
  $this->assertSame(55.0,$result['before']['score']);$this->assertSame(85.0,$result['after']['score']);$this->assertSame(30.0,$result['delta']);$this->assertSame($before,$svc->levels($raka));
  $new=$svc->simulate($this->user('baru@example.test'),$this->job(),[$this->training()]);$this->assertSame(0.0,$new['before']['score']);$this->assertSame(30.0,$new['after']['score']);
  $this->actingAs($raka)->get('/pelamar/simulasi/hasil?job_id='.$this->job()->id.'&training_ids[]='.$this->training())->assertOk()->assertSee('+30 poin')->assertSee('Perubahan skill hipotetis');
 }
 public function test_irrelevant_training_produces_zero_gain_and_explains_it():void {
  $svc=app(MatchingService::class);$r=$svc->simulate($this->user('baru@example.test'),$this->job(),[$this->training('Komunikasi dan Pelayanan')]);
  $this->assertSame(0.0,$r['after']['score']);
  $fifty=$svc->simulate($this->user(),JobVacancy::where('title','Operator Administrasi Demo')->firstOrFail(),[$this->training()]);
  $this->assertSame(50.0,$fifty['before']['score']);$this->assertSame(50.0,$fifty['after']['score']);
  $this->actingAs($this->user())->get('/pelamar/simulasi/hasil?job_id='.$this->job()->id.'&training_id='.$this->training('Komunikasi dan Pelayanan'))->assertOk()->assertSee('Skor tetap karena');
 }
 public function test_multiple_courses_use_maximum_and_one_configuration():void {
  $r=app(MatchingService::class)->simulate($this->user(),$this->job(),[$this->training('Spreadsheet untuk Logistik'),$this->training('Spreadsheet Lanjutan')]);
  $this->assertSame(70.0,$r['after']['score']);$this->assertSame($r['before']['configuration_version'],$r['after']['configuration_version']);
  $this->assertSame(4,collect($r['changes'])->firstWhere('skill','Spreadsheet')['after']);
 }
 public function test_unpublished_and_unverified_body_data_are_hidden_and_direct_requests_rejected():void {
  $this->assertSame(9,Publication::jobs()->count());$this->assertSame(8,Publication::trainings()->count());
  $this->actingAs($this->user())->get('/pelamar/pelatihan')->assertOk()->assertDontSee('Demo Pelatihan Lembaga Pending')->assertDontSee('% cakupan gap')->assertSee('Katalog pelatihan umum');
  foreach(['Demo Pelatihan Lembaga Pending','Demo Pelatihan Lembaga Nonaktif','Demo Pelatihan Email Belum Verifikasi','Demo Pelatihan Draft'] as $title){
   $id=$this->training($title);$this->get('/pelamar/pelatihan/detail?training_id='.$id)->assertNotFound();$this->post('/learning-plan',['training_id'=>$id])->assertNotFound();
  }
  $id=DB::table('job_vacancies')->where('title','Demo Pembatasan Draft')->value('id');$this->get('/pelamar/lowongan/detail?job_id='.$id)->assertNotFound();
 }
 public function test_unverified_user_and_pending_body_cannot_bypass_access_rules():void {
  $this->actingAs($this->user('belum.verifikasi@example.test'))->get('/pelamar/dashboard')->assertRedirect('/verification-notice');
  $this->actingAs($this->user('lembaga.pending@example.test'))->get('/sertifikasi/dashboard')->assertRedirect('/sertifikasi/menunggu-verifikasi');
  $this->post('/graduations/1')->assertForbidden();
 }
 public function test_target_is_kept_in_gap_training_and_simulation_links():void {
  $id=$this->job()->id;$this->actingAs($this->user())->get('/pelamar/lowongan/kesenjangan?jobs[]='.$id)->assertOk()->assertSee('jobs%5B0%5D='.$id,false);
  $this->get('/pelamar/pelatihan/detail?job_id='.$id.'&training_id='.$this->training())->assertOk()->assertSee('LOWONGAN TARGET')->assertSee('Skill yang diajarkan')->assertSee('job_id='.$id,false);
  $this->get('/pelamar/lowongan/skor?job_id='.$id)->assertOk()->assertSee('Kecocokan setiap keterampilan')->assertDontSee('Kontribusi (w × c)')->assertSee('55');
 }
 public function test_import_preview_cancel_and_commit_are_explicit_and_replay_safe():void {
  $this->actingAs($this->user('admin@skillmatching.local'));
  $csv="title,company,source,fetched_at\nImpor Valid,PT Demo,Demo,2026-10-15\nImpor Error,,Demo,tanggal-salah\n";
  $file=UploadedFile::fake()->createWithContent('test.csv',$csv);$before=DB::table('job_vacancies')->count();
  $this->post('/admin/catalog-import/job_vacancies',['file'=>$file])->assertRedirect('/admin/lowongan/impor');
  $this->assertSame($before,DB::table('job_vacancies')->count());$token=session('import_preview.job_vacancies.token');
  $this->post('/admin/import/job_vacancies/commit',['token'=>$token])->assertRedirect();$this->assertDatabaseHas('job_vacancies',['title'=>'Impor Valid','status'=>'draft']);
  $this->assertDatabaseMissing('job_vacancies',['title'=>'Impor Error']);$this->post('/admin/import/job_vacancies/commit',['token'=>$token])->assertStatus(422);
 }
 public function test_bookmark_visibility_and_other_users_are_isolated():void {
  $id=$this->job()->id;$this->actingAs($this->user())->post('/vacancies/'.$id.'/bookmark',['saved'=>1])->assertRedirect();
  $this->get('/pelamar/lowongan?bookmarked=1')->assertOk()->assertSee('Staf Gudang');
  $this->actingAs($this->user('baru@example.test'))->get('/pelamar/lowongan?bookmarked=1')->assertOk()->assertSee('Belum ada lowongan untuk ditampilkan');
 }
 public function test_populated_details_for_all_roles_render_with_real_fixtures():void {
  $this->actingAs($this->user());$cert=DB::table('certificates')->where('user_id',$this->user()->id)->first();
  $this->get('/pelamar/sertifikat/detail?id='.$cert->id)->assertOk()->assertSee('Level klaim');
  $app=DB::table('job_applications')->first();$this->get('/pelamar/lamaran/detail?id='.$app->id)->assertOk()->assertSee('Riwayat Perubahan');
  $this->actingAs($this->user('kontak@logistiknusantara.id'));$this->get('/sertifikasi/sertifikat/detail?id='.$cert->id)->assertOk()->assertSee('Lanjut ke Keputusan');
  $this->get('/sertifikasi/sertifikat/keputusan?id='.$cert->id)->assertOk()->assertSee('Pilih keputusan');
  $this->get('/sertifikasi/riwayat')->assertOk()->assertSee('Riwayat Konfirmasi Kelulusan');
  $this->actingAs($this->user('admin@skillmatching.local'))->get('/admin/laporan')->assertOk()->assertSee('DEMO-1');
 }
 public function test_historical_results_are_preserved_after_new_configuration():void {
  $history=DB::table('recommendation_histories')->first();$old=$history->results;$cfg=WeightConfiguration::where('is_active',true)->first();
  $this->actingAs($this->user('admin@skillmatching.local'))->post('/admin/weights',['required_weight'=>4,'preferred_weight'=>2,'related_factor'=>.4,'threshold'=>45,'reason'=>'Skenario konfigurasi baru'])->assertRedirect();
  $this->assertSame($old,DB::table('recommendation_histories')->find($history->id)->results);$this->assertFalse((bool)$cfg->fresh()->is_active);
 }
 public function test_coverage_uses_weighted_gap_and_not_matching_score():void {
  $sid=DB::table('skills')->where('name','Spreadsheet')->value('id');$t=$this->training('Spreadsheet untuk Logistik');
  $options=app(MatchingService::class)->trainingOptions([['skill_id'=>$sid,'skill'=>'Spreadsheet','own'=>0,'target'=>2,'gap'=>2,'priority'=>5,'jobs'=>['Uji']]],[]);
  $option=collect($options)->firstWhere('id',$t);$this->assertSame(100.0,$option['coverage']);$this->assertSame(5,$option['coverage_total']);
  $this->assertEquals(5.0,$option['coverage_gain']);
 }
 public function test_bulk_catalog_changes_validate_before_writing_and_require_admin():void {
  $sid=DB::table('skills')->where('name','SQL Dasar')->value('id');$id=$this->job()->id;
  $this->actingAs($this->user())->post('/admin/catalog-skills/vacancy/'.$id,['items'=>[$sid=>['selected'=>'1','level'=>2,'importance'=>'required','weight'=>3]]])->assertForbidden();
  $this->actingAs($this->user('admin@skillmatching.local'))->post('/admin/catalog-skills/vacancy/'.$id,['items'=>[$sid=>['selected'=>'1','level'=>2,'importance'=>'required','weight'=>5]]])->assertSessionHasErrors('reason');
  $this->assertDatabaseMissing('job_required_skills',['job_vacancy_id'=>$id,'skill_id'=>$sid]);
  $this->post('/admin/catalog-skills/vacancy/'.$id,['items'=>[$sid=>['selected'=>'1','level'=>2,'importance'=>'required','weight'=>5,'reason'=>'Kebutuhan khusus data demo']]])->assertRedirect();
  $this->assertDatabaseHas('job_required_skills',['job_vacancy_id'=>$id,'skill_id'=>$sid,'weight'=>5,'is_override'=>true]);
 }
 public function test_protected_preview_and_explicit_decision():void {
  $cert=DB::table('certificates')->where('user_id',$this->user()->id)->where('status','pending')->first();
  $this->actingAs($this->user())->get('/certificates/'.$cert->id.'/download?preview=1')->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
  $this->actingAs($this->user('baru@example.test'))->get('/certificates/'.$cert->id.'/download?preview=1')->assertForbidden();
  $this->actingAs($this->user('kontak@logistiknusantara.id'))->post('/certificates/'.$cert->id.'/decision',[])->assertSessionHasErrors('decision');
  $this->assertDatabaseHas('certificates',['id'=>$cert->id,'status'=>'pending']);
  $this->post('/certificates/'.$cert->id.'/decision',['decision'=>'verified'])->assertRedirect();
  $this->post('/certificates/'.$cert->id.'/decision',['decision'=>'rejected','reason'=>'Pengiriman ulang keputusan'])->assertStatus(422);
  $this->assertDatabaseHas('certificates',['id'=>$cert->id,'status'=>'verified']);
 }
 public function test_account_password_requires_current_password_and_confirmation():void {
  $this->actingAs($this->user())->post('/account/password',['current_password'=>'wrong','password'=>'NewSecurePassword2026!','password_confirmation'=>'NewSecurePassword2026!'])->assertSessionHasErrors('current_password');
  $this->post('/account/password',['current_password'=>'SkillmatchingDemo2026!','password'=>'NewSecurePassword2026!','password_confirmation'=>'NewSecurePassword2026!'])->assertRedirect();
  $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewSecurePassword2026!',$this->user()->password));
 }
}
