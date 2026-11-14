<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{User,JobVacancy,WeightConfiguration};
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
class MatchingTest extends TestCase {
 use RefreshDatabase;
 public function test_example_from_proposal_scores_55_then_85():void {
  $user=User::create(['name'=>'Raka','email'=>'raka@example.test','password'=>'example-long-pass','role'=>'pelamar','email_verified_at'=>now()]);
  $config=WeightConfiguration::create(['version'=>1,'required_weight'=>3,'preferred_weight'=>1,'related_factor'=>.5,'threshold'=>40,'is_active'=>true]);
  $job=JobVacancy::create(['title'=>'Staf Gudang','company'=>'Contoh','fetched_at'=>now(),'expires_at'=>now()->addDays(30),'status'=>'active']);
  $skills=[];foreach(['Pencatatan Stok','Pengoperasian Forklift','Spreadsheet','K3'] as $name){$skills[$name]=DB::table('skills')->insertGetId(['name'=>$name,'category'=>'Logistik','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);}
  foreach([['Pencatatan Stok',2,'required',3],['Pengoperasian Forklift',2,'required',3],['Spreadsheet',2,'required',3],['K3',1,'preferred',1]] as [$name,$level,$importance,$weight]){DB::table('job_required_skills')->insert(['job_vacancy_id'=>$job->id,'skill_id'=>$skills[$name],'minimum_level'=>$level,'importance'=>$importance,'weight'=>$weight,'created_at'=>now(),'updated_at'=>now()]);}
  $levels=[$skills['Pencatatan Stok']=>3,$skills['Spreadsheet']=>1,$skills['K3']=>2];$engine=app(MatchingService::class);
  $this->assertSame(55.0,$engine->score($job,$levels,$config)['score']);
  $levels[$skills['Pengoperasian Forklift']]=2;
  $this->assertSame(85.0,$engine->score($job,$levels,$config)['score']);
 }
 public function test_role_is_restricted_on_server():void {
  $user=User::create(['name'=>'Pelamar','email'=>'p@example.test','password'=>'example-long-pass','role'=>'pelamar','email_verified_at'=>now()]);
  $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
 }
}
