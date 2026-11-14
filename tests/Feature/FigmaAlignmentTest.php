<?php
namespace Tests\Feature;

use App\Models\{User,JobVacancy};
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Mail,Storage};
use Tests\TestCase;

class FigmaAlignmentTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void
    {
        parent::setUp();
        foreach(['OWNER_EMAIL'=>'admin@skillmatching.local','OWNER_PASSWORD'=>'SkillmatchingDemo2026!','DEMO_PASSWORD'=>'SkillmatchingDemo2026!','DEMO_SEED'=>'true'] as $key=>$value) putenv($key.'='.$value);
        Storage::fake('local'); Mail::fake(); $this->seed(DatabaseSeeder::class);
    }
    protected function tearDown():void
    {
        foreach(['OWNER_EMAIL','OWNER_PASSWORD','DEMO_PASSWORD','DEMO_SEED'] as $key) putenv($key);
        parent::tearDown();
    }
    private function loginAs(string $email='raka@example.com'):void { $this->actingAs(User::where('email',$email)->firstOrFail()); }

    public function test_simulation_remains_55_to_85_without_exposing_calculation_tables():void
    {
        $this->loginAs();$job=JobVacancy::where('title','Staf Gudang')->firstOrFail();$training=DB::table('trainings')->where('title','Forklift Dasar')->value('id');
        $this->get('/pelamar/simulasi/hasil?job_id='.$job->id.'&training_id='.$training)->assertOk()->assertSee('+30 poin')->assertSee('55')->assertSee('85')->assertDontSee('Kontribusi (w × c)')->assertDontSee('Rumus dan asal angka');
        $this->get('/pelamar/lowongan/skor?job_id='.$job->id)->assertOk()->assertSee('Kecocokan setiap keterampilan')->assertSee('Bagaimana skor dihitung?')->assertDontSee('Σ')->assertDontSee('Konfigurasi global');
    }
    public function test_pending_institution_has_no_operational_navigation_and_still_cannot_open_tasks():void
    {
        $this->loginAs('lembaga.pending@example.test');
        $this->get('/sertifikasi/menunggu-verifikasi')->assertOk()->assertSee('Identitas lembaga sedang ditinjau')->assertDontSee('href="/sertifikasi/pelatihan"',false)->assertDontSee('href="/sertifikasi/sertifikat"',false);
        $this->get('/sertifikasi/sertifikat')->assertRedirect('/sertifikasi/menunggu-verifikasi');
    }
    public function test_institution_inspection_and_decision_are_separate_but_permission_remains_enforced():void
    {
        $this->loginAs('kontak@logistiknusantara.id');$cert=DB::table('certificates')->where('status','pending')->first();
        $this->get('/sertifikasi/sertifikat/detail?id='.$cert->id)->assertOk()->assertSee('Lanjut ke Keputusan')->assertDontSee('name="decision"',false);
        $this->get('/sertifikasi/sertifikat/keputusan?id='.$cert->id)->assertOk()->assertSee('name="decision"',false);
        $this->loginAs('lembaga.pending@example.test');$this->post('/certificates/'.$cert->id.'/decision',['decision'=>'verified'])->assertForbidden();
        $this->assertSame('pending',DB::table('certificates')->find($cert->id)->status);
    }
    public function test_admin_can_distinguish_email_and_institution_approval_from_account_activation():void
    {
        $this->loginAs('admin@skillmatching.local');
        $this->get('/admin/akun?role=sertifikasi')->assertOk()->assertSee('Verifikasi email')->assertSee('Persetujuan lembaga')->assertSee('Menunggu persetujuan');
    }
    public function test_completion_screen_is_specific_and_does_not_complete_training_on_get():void
    {
        $this->loginAs('baru@example.test');$user=auth()->id();$enrollment=DB::table('training_enrollments')->where('user_id',$user)->whereIn('status',['planned','in_progress'])->first();
        $this->assertNotNull($enrollment);
        $this->get('/pelamar/pelatihan/selesaikan?training_id='.$enrollment->training_id)->assertOk()->assertSee('Konfirmasi Penyelesaian')->assertSee('Capaian pelatihan');
        $this->assertSame($enrollment->status,DB::table('training_enrollments')->find($enrollment->id)->status);
    }
    public function test_selected_application_has_only_one_external_source_action():void
    {
        $this->loginAs();$application=DB::table('job_applications')->where('user_id',auth()->id())->whereNotNull('job_vacancy_id')->first();
        $response=$this->get('/pelamar/lamaran/detail?id='.$application->id)->assertOk()->assertSee('Riwayat Perubahan');
        $this->assertSame(1,substr_count($response->getContent(),'Buka Sumber Lowongan ↗'));
    }
    public function test_each_bulk_editor_renders_its_existing_selected_rows():void
    {
        $this->loginAs('admin@skillmatching.local');
        $job=JobVacancy::where('title','Staf Gudang')->firstOrFail();
        $training=DB::table('trainings')->where('title','Forklift Dasar')->value('id');
        $taxonomy=DB::table('job_skills_mapping')->value('job_taxonomy_id');
        $this->get('/admin/pelatihan/skill?id='.$training)->assertRedirect('/admin/pengajuan');
        foreach(['/admin/lowongan/persyaratan?id='.$job->id,'/admin/jabatan/pemetaan?job_taxonomy_id='.$taxonomy] as $url) {
            $this->get($url)->assertOk()->assertSee('Simpan Keterampilan Terpilih')->assertSee('Kelola Keterampilan Tersimpan');
        }
    }
}
