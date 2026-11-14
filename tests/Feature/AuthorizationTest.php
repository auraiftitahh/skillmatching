<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{User,Certificate};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
class AuthorizationTest extends TestCase {
 use RefreshDatabase;
 public function test_applicant_cannot_read_other_certificate():void {
  $a=User::create(['name'=>'A','email'=>'a@example.test','password'=>'long-password-a','role'=>'pelamar','email_verified_at'=>now()]);$b=User::create(['name'=>'B','email'=>'b@example.test','password'=>'long-password-b','role'=>'pelamar','email_verified_at'=>now()]);
  $issuer=User::create(['name'=>'L','email'=>'l@example.test','password'=>'long-password-l','role'=>'sertifikasi','email_verified_at'=>now()]);
  $body=DB::table('certification_bodies')->insertGetId(['user_id'=>$issuer->id,'name'=>'Lembaga','status'=>'active','created_at'=>now(),'updated_at'=>now()]);$skill=DB::table('skills')->insertGetId(['name'=>'K3','category'=>'Umum','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $cert=Certificate::create(['user_id'=>$a->id,'certification_body_id'=>$body,'skill_id'=>$skill,'title'=>'Sertifikat','file_path'=>'certificates/missing.pdf','level'=>2,'status'=>'pending']);
  $this->actingAs($b)->get(route('certificates.file',$cert->id))->assertForbidden();
 }
}
