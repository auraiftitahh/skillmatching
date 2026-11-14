<?php
namespace Database\Seeders;
use App\Models\{User,WeightConfiguration};
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder {
 public function run():void {
  $password=(string)env('OWNER_PASSWORD');
  if(strlen($password)<12)throw new \RuntimeException('Set OWNER_PASSWORD minimal 12 karakter.');
  $admin=User::firstOrCreate(['email'=>env('OWNER_EMAIL','admin@skillmatching.local')],['name'=>'Admin Platform','password'=>$password,'role'=>'admin','email_verified_at'=>now(),'is_active'=>true]);
  // Existing active and historical versions are never reset by a seed rerun.
  if(!WeightConfiguration::exists())WeightConfiguration::create(['version'=>1,'required_weight'=>3,'preferred_weight'=>1,'related_factor'=>.5,'threshold'=>40,'reason'=>'Konfigurasi awal','created_by'=>$admin->id,'is_active'=>true]);
  if(filter_var(env('DEMO_SEED',false),FILTER_VALIDATE_BOOLEAN)){$this->call(ScenarioSeeder::class);$this->call(InstitutionWorkflowSeeder::class);}
  else $this->command?->info('Akun owner/konfigurasi siap. Data demo tidak ditambahkan.');
 }
}
