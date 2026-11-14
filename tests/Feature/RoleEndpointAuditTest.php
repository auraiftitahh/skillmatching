<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Route};
use Tests\TestCase;

class RoleEndpointAuditTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role,string $suffix=''): User
    {
        $u=User::create(['name'=>$role.$suffix,'email'=>$role.$suffix.'@audit.test','role'=>$role,'password'=>'audit-password-123','is_active'=>true,'email_verified_at'=>now()]);
        if($role==='sertifikasi')DB::table('certification_bodies')->insert(['user_id'=>$u->id,'name'=>'Badan audit','status'=>'active','created_at'=>now(),'updated_at'=>now()]);
        return $u;
    }

    private function endpoints(): array
    {
        return [
            ['profile.save','pelamar'],['experience.save','pelamar'],['experience.delete','pelamar'],['skills.save','pelamar'],
            ['recommendations.snapshot','pelamar'],['plan.save','pelamar'],['training.complete','pelamar'],['certificates.save','pelamar'],
            ['certificates.cancel','pelamar'],['applications.save','pelamar'],['feedback.save','pelamar'],['vacancy.bookmark','pelamar'],['enrollments.status','pelamar'],
            ['external-evidence.save','pelamar'],['external-evidence.update','pelamar'],['external-evidence.link','pelamar'],['external-evidence.withdraw','pelamar'],
            ['submissions.save','sertifikasi'],['graduations.confirm','sertifikasi'],['certificates.decision','sertifikasi'],['external-evidence.decision','sertifikasi'],
            ['submissions.review','admin'],['weights.save','admin'],['accounts.save','admin'],['catalog.save','admin'],['admin.catalog.status','admin'],
            ['admin.skills.status','admin'],['admin.skills.destroy','admin'],['vacancy.skill','admin'],['training.skill','admin'],['training.prerequisite','admin'],
            ['mappings.save','admin'],['relations.save','admin'],['evaluation.save','admin'],['admin.relation.remove','admin'],['catalog.bulk','admin'],
            ['catalog.import','admin'],['import.commit','admin'],['import.cancel','admin'],['evaluation.export','admin'],
        ];
    }
    private function endpoint(string $name): array
    {
        $route=Route::getRoutes()->getByName($name);$this->assertNotNull($route,$name);
        $params=[];foreach($route->parameterNames() as $key)$params[$key]=match($key){'type'=>'job_vacancies','kind'=>'vacancy',default=>999999};
        return [$route->methods()[0],route($name,$params)];
    }
    public function test_every_role_specific_endpoint_rejects_other_roles_before_business_validation(): void
    {
        $users=[];foreach(['pelamar','admin','sertifikasi'] as $role)$users[$role]=$this->user($role);
        foreach($this->endpoints() as [$name,$allowed]){
            [$method,$url]=$this->endpoint($name);
            foreach($users as $role=>$user){if($role===$allowed)continue;
                $this->actingAs($user)->call($method,$url)->assertForbidden();
            }
        }
    }
    public function test_every_protected_action_requires_login(): void
    {
        foreach($this->endpoints() as [$name]){[$method,$url]=$this->endpoint($name);$this->call($method,$url)->assertRedirect('/login');}
    }
    public function test_pending_institution_cannot_post_to_any_institution_action(): void
    {
        $user=$this->user('sertifikasi','pending');DB::table('certification_bodies')->where('user_id',$user->id)->update(['status'=>'pending']);
        foreach($this->endpoints() as [$name,$allowed])if($allowed==='sertifikasi'){[$method,$url]=$this->endpoint($name);$this->actingAs($user)->call($method,$url)->assertForbidden();}
    }
}
