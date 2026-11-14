<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{User,WeightConfiguration};
use App\Http\Controllers\WorkspaceController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
class WorkspaceTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();WeightConfiguration::create(['version'=>1,'required_weight'=>3,'preferred_weight'=>1,'related_factor'=>.5,'threshold'=>40,'is_active'=>true]);}
 private function userWithRole(string $role):User {
  $user=User::create(['name'=>'Pengguna Uji','email'=>str_replace('/','-',$role).'@example.test','password'=>'password-for-testing-123','role'=>$role,'email_verified_at'=>now(),'is_active'=>true]);
  if($role==='sertifikasi')DB::table('certification_bodies')->insert(['user_id'=>$user->id,'name'=>'Lembaga Uji','status'=>'active','created_at'=>now(),'updated_at'=>now()]);
  return $user;
 }
 public function test_every_canonical_workspace_page_renders_without_blade_parse_error():void {
  foreach(['pelamar','admin','sertifikasi'] as $role){
   $user=$this->userWithRole($role);$this->actingAs($user);
   foreach(array_keys(WorkspaceController::TITLES[$role]) as $page){
    if($role==='sertifikasi'&&$page==='menunggu-verifikasi')continue;
    if(in_array($page,['bukti/detail','bukti/form','pengajuan/detail'])){$this->get('/'.$role.'/'.$page)->assertNotFound();continue;}
    if($role==='admin' && in_array($page,['skill/form','pelatihan/form','pelatihan/skill','pelatihan/impor','pelatihan/hasil-impor'])){$this->get('/'.$role.'/'.$page)->assertRedirect('/admin/pengajuan');continue;}
    $this->get('/'.$role.'/'.$page)->assertOk();
   }
  }
 }
 public function test_user_cannot_open_other_roles_workspace():void {
  $this->actingAs($this->userWithRole('pelamar'));
  $this->get('/admin/dashboard')->assertForbidden();
  $this->get('/sertifikasi/dashboard')->assertForbidden();
 }
 public function test_pending_certification_body_cannot_view_tasks():void {
  $user=$this->userWithRole('sertifikasi');
  DB::table('certification_bodies')->where('user_id',$user->id)->update(['status'=>'pending']);
  $this->actingAs($user)->get('/sertifikasi/dashboard')->assertRedirect('/sertifikasi/menunggu-verifikasi');
 }
}
