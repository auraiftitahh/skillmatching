<?php
namespace Database\Seeders;
use App\Models\{User,JobVacancy,WeightConfiguration};
use App\Services\MatchingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB,Storage};

/** Deterministic, opt-in synthetic scenarios. Does not overwrite existing records. */
class ScenarioSeeder extends Seeder
{
 private function row(string $table,array $key,array $values):int {
  $q=DB::table($table);foreach($key as $k=>$v)$q->where($k,$v);
  if($id=$q->value('id'))return (int)$id;
  return DB::table($table)->insertGetId([...$key,...$values,'created_at'=>now(),'updated_at'=>now()]);
 }
 private function user(string $email,string $name,string $role='pelamar',bool $verified=true,bool $active=true):User {
  return User::firstOrCreate(['email'=>$email],['name'=>$name,'password'=>env('DEMO_PASSWORD'),'role'=>$role,'email_verified_at'=>$verified?now():null,'is_active'=>$active,'employment_status'=>'laid_off','domicile'=>'Surabaya']);
 }
 public function run():void {
  if(!filter_var(env('DEMO_SEED',false),FILTER_VALIDATE_BOOLEAN))throw new \RuntimeException('ScenarioSeeder memerlukan DEMO_SEED=true secara eksplisit.');
  if(app()->environment('production')&&!filter_var(env('ALLOW_DEMO_IN_PRODUCTION',false),FILTER_VALIDATE_BOOLEAN))throw new \RuntimeException('Data sintetis produksi dinonaktifkan. Gunakan staging atau ALLOW_DEMO_IN_PRODUCTION=true secara eksplisit.');
  if(strlen((string)env('DEMO_PASSWORD'))<12)throw new \RuntimeException('DEMO_PASSWORD minimal 12 karakter.');
  DB::transaction(function(){
   $groups=[
    'Logistik'=>['Pencatatan Stok','Pengoperasian Forklift','Manajemen Inventori','Penerimaan Barang','Pengiriman Barang','Distribusi Logistik','Audit Persediaan','Manajemen Gudang','Pengendalian Persediaan','Pemrosesan Pesanan'],
    'Administrasi'=>['Spreadsheet','Administrasi Dokumen','Administrasi Perkantoran','Pengolahan Data','Dasar Akuntansi','Pengarsipan Digital','Penjadwalan Kerja','Komunikasi Kerja'],
    'Produksi'=>['K3','Pengoperasian Mesin','Pencatatan Produksi','Pengemasan Produk','Pemeriksaan Kualitas','Kontrol Mutu','Pemeliharaan Mesin','Operasional Produksi','Perencanaan Produksi'],
    'Digital'=>['Analisis Data','SQL Dasar','Visualisasi Data','Pemrograman Web','Dukungan TI','Keamanan Informasi','Pemasaran Digital','Desain Grafis'],
    'Layanan'=>['Layanan Pelanggan','Penanganan Keluhan','Penjualan','Pembelian Barang','Pengelolaan Kas','Pelayanan Tamu','Bahasa Inggris Kerja'],
   ];$sid=[];
   foreach($groups as $group=>$names)foreach($names as $name)$sid[$name]=$this->row('skills',['name'=>$name],['category'=>$group,'aliases'=>json_encode($name==='Spreadsheet'?['Excel','Lembar Kerja']:[]),'is_active'=>true]);
   $this->row('skills',['name'=>'Skill Arsip Demo Nonaktif'],['category'=>'Arsip','is_active'=>false]);
   $taxonomy=['Staf Gudang'=>['Pencatatan Stok','K3','Spreadsheet'],'Admin Inventori'=>['Manajemen Inventori','Spreadsheet','Administrasi Dokumen'],'Operator Produksi'=>['Pengoperasian Mesin','K3','Pencatatan Produksi'],'Analis Persediaan'=>['Analisis Data','SQL Dasar','Visualisasi Data'],'Staf Layanan Pelanggan'=>['Layanan Pelanggan','Penanganan Keluhan','Komunikasi Kerja'],'Staf Akuntansi'=>['Dasar Akuntansi','Spreadsheet','Pengelolaan Kas'],'Desainer Grafis'=>['Desain Grafis','Pemasaran Digital'],'Teknisi TI'=>['Dukungan TI','Keamanan Informasi']];
   foreach($taxonomy as $title=>$names){$id=$this->row('jobs_taxonomy',['name'=>$title],['is_active'=>true]);foreach($names as $name)DB::table('job_skills_mapping')->insertOrIgnore(['job_taxonomy_id'=>$id,'skill_id'=>$sid[$name]]);}
   DB::table('skill_relations')->insertOrIgnore(['skill_id'=>$sid['Pengoperasian Forklift'],'related_skill_id'=>$sid['Pengoperasian Mesin']]);
   $bodyIds=[];
   foreach([
    ['kontak@logistiknusantara.id','Lembaga Sertifikasi Logistik Nusantara','active',true,true],
    ['lembaga.digital@example.test','Lembaga Kompetensi Digital Demo','active',true,true],
    ['lembaga.pending@example.test','Lembaga Menunggu Persetujuan Demo','pending',true,true],
    ['lembaga.rejected@example.test','Lembaga Ditolak Demo','rejected',true,true],
    ['lembaga.disabled@example.test','Lembaga Nonaktif Demo','disabled',true,false],
    ['lembaga.unverified@example.test','Lembaga Email Belum Terverifikasi Demo','active',false,true],
   ] as [$email,$name,$status,$verified,$active]){
    $u=$this->user($email,$name,'sertifikasi',$verified,$active);
    $bodyIds[$status.($verified?'':'-email')]=$this->row('certification_bodies',['user_id'=>$u->id],['name'=>$name,'contact'=>$email,'status'=>$status,'verified_at'=>$status==='active'&&$verified?now():null]);
   }
   // Retain the canonical logistik body separately from the second active body.
   $logistik=DB::table('certification_bodies')->where('user_id',User::where('email','kontak@logistiknusantara.id')->value('id'))->value('id');
   $raka=$this->user('raka@example.com','Raka Pratama');
   $new=$this->user('baru@example.test','Nadia — Profil Kosong');
   $partial=$this->user('parsial@example.test','Dimas — Kemampuan Dasar');
   $expert=$this->user('mahir@example.test','Sinta — Kompetensi Lengkap');
   $related=$this->user('relasi@example.test','Budi — Skill Terkait');
   $digital=$this->user('digital@example.test','Maya — Analis Data');
   $this->user('belum.verifikasi@example.test','Email Belum Terverifikasi','pelamar',false);
   $this->user('nonaktif@example.test','Akun Pelamar Nonaktif','pelamar',true,false);
   foreach([
    [$raka,['Pencatatan Stok'=>3,'Spreadsheet'=>1,'K3'=>2]],
    [$partial,['Pencatatan Stok'=>1,'Spreadsheet'=>1]],
    [$expert,['Pencatatan Stok'=>4,'Pengoperasian Forklift'=>4,'Spreadsheet'=>4,'K3'=>4]],
    [$related,['Pengoperasian Mesin'=>2]],
    [$digital,['Analisis Data'=>2,'SQL Dasar'=>2,'Visualisasi Data'=>1]],
   ] as [$user,$levels])foreach($levels as $name=>$level)$this->row('user_skills',['user_id'=>$user->id,'skill_id'=>$sid[$name]],['level'=>$level,'verification_status'=>$user->id===$expert->id?'verified':'self_reported']);
   $taxId=DB::table('jobs_taxonomy')->where('name','Staf Gudang')->value('id');
   $this->row('user_experiences',['user_id'=>$raka->id,'title'=>'Operator Gudang'],['organization'=>'PT Demo Logistik','type'=>'work','job_taxonomy_id'=>$taxId,'start_date'=>'2021-01-01','end_date'=>'2025-02-28','description'=>'Data sintetis untuk saran skill, bukan riwayat nyata.']);
   $jobs=[];$titles=array_keys($taxonomy);
   foreach($titles as $i=>$title){
    $requirements=$i===0?[['Pencatatan Stok',2,'required'],['Pengoperasian Forklift',2,'required'],['Spreadsheet',2,'required'],['K3',1,'preferred']]:array_map(fn($s)=>[$s,2,'required'],$taxonomy[$title]);
    $id=$this->row('job_vacancies',['title'=>$title,'company'=>$i===0?'PT Logistik Nusantara':'PT Demo '.($i+1)],['description'=>'DATA SINTETIS / DEMO. Tidak menerima lamaran nyata. Persyaratan dibuat untuk pengujian.','location'=>['Surabaya','Jakarta','Bandung','Semarang'][$i%4],'employment_type'=>$i%2?'Tetap':'Kontrak','source'=>'DATA SINTETIS / DEMO','source_url'=>'https://example.com/lowongan-demo','fetched_at'=>now()->toDateString(),'expires_at'=>now()->addDays(90)->toDateString(),'status'=>'active']);
    $jobs[$title]=$id;
    foreach($requirements as [$name,$target,$importance])$this->row('job_required_skills',['job_vacancy_id'=>$id,'skill_id'=>$sid[$name]],['minimum_level'=>$target,'importance'=>$importance,'weight'=>$importance==='required'?3:1,'is_override'=>false]);
   }
   $fifty=$this->row('job_vacancies',['title'=>'Operator Administrasi Demo','company'=>'PT Demo Skor Tetap'],['description'=>'Fixture 50 ke 50 dengan Forklift Dasar.','source'=>'DEMO','fetched_at'=>now()->toDateString(),'expires_at'=>now()->addDays(90)->toDateString(),'status'=>'active','location'=>'Semarang']);
   $this->row('job_required_skills',['job_vacancy_id'=>$fifty,'skill_id'=>$sid['Spreadsheet']],['minimum_level'=>2,'importance'=>'required','weight'=>3,'is_override'=>false]);
   foreach(['draft','expired','disabled','closed','active'] as $i=>$status){
    $id=$this->row('job_vacancies',['title'=>'Demo Pembatasan '.($status==='active'?'Tanggal Lewat':ucfirst($status)),'company'=>'PT Demo Batasan'],['description'=>'Tidak boleh muncul pada rekomendasi publik.','source'=>'DEMO','fetched_at'=>now()->subDays(10)->toDateString(),'expires_at'=>($status==='expired'||$status==='active'?now()->subDay():now()->addDays(30))->toDateString(),'status'=>$status]);
    $this->row('job_required_skills',['job_vacancy_id'=>$id,'skill_id'=>$sid['Spreadsheet']],['minimum_level'=>2,'importance'=>'required','weight'=>3,'is_override'=>false]);
   }
   $this->row('job_vacancies',['title'=>'Demo Belum Ada Persyaratan','company'=>'PT Demo Batasan'],['fetched_at'=>now()->toDateString(),'expires_at'=>now()->addDays(30)->toDateString(),'status'=>'active']);
   $trainings=[];
   $defs=[
    ['Forklift Dasar','Pengoperasian Forklift',2,'offline',300000,$logistik,'active'],
    ['Spreadsheet untuk Logistik','Spreadsheet',2,'online',0,null,'active'],
    ['Spreadsheet Lanjutan','Spreadsheet',4,'online',250000,null,'active'],
    ['Pencatatan Stok Mandiri','Pencatatan Stok',2,'hybrid',150000,null,'active'],
    ['K3 untuk Operasional','K3',2,'offline',100000,$logistik,'active'],
    ['SQL untuk Analisis Persediaan','SQL Dasar',3,'online',200000,$bodyIds['active'],'active'],
    ['Visualisasi Data Bisnis','Visualisasi Data',3,'online',400000,$bodyIds['active'],'active'],
    ['Komunikasi dan Pelayanan','Layanan Pelanggan',2,'hybrid',0,null,'active'],
    ['Demo Pelatihan Draft','Spreadsheet',2,'online',0,null,'draft'],
    ['Demo Pelatihan Nonaktif','Spreadsheet',2,'online',0,null,'disabled'],
    ['Demo Pelatihan Lembaga Pending','Spreadsheet',2,'online',0,$bodyIds['pending'],'active'],
    ['Demo Pelatihan Lembaga Nonaktif','Spreadsheet',2,'online',0,$bodyIds['disabled'],'active'],
    ['Demo Pelatihan Email Belum Verifikasi','Spreadsheet',2,'online',0,$bodyIds['active-email'],'active'],
   ];
   foreach($defs as $i=>[$title,$skill,$level,$mode,$cost,$bodyId,$status]){
    $id=$this->row('trainings',['title'=>$title],['provider'=>'Penyedia Demo Kompetensi','description'=>'DATA SINTETIS / DEMO. Bukan penawaran resmi.','mode'=>$mode,'duration_hours'=>8+$i*2,'weekly_hours'=>2+$i%5,'cost'=>$cost,'source'=>'DEMO','source_url'=>'https://example.com/pelatihan-demo','fetched_at'=>now()->toDateString(),'status'=>$status,'certification_body_id'=>$bodyId]);
    $trainings[$title]=$id;$this->row('training_skills',['training_id'=>$id,'skill_id'=>$sid[$skill]],['target_level'=>$level]);
   }
   DB::table('training_prerequisites')->insertOrIgnore(['training_id'=>$trainings['Spreadsheet Lanjutan'],'prerequisite_id'=>$trainings['Spreadsheet untuk Logistik']]);
   $states=['planned','in_progress','waiting_confirmation','completed','rejected'];
   $learners=[$new,$partial,$raka,$expert,$digital];
   foreach($states as $i=>$status)$this->row('training_enrollments',['user_id'=>$learners[$i]->id,'training_id'=>$trainings['Forklift Dasar']],['status'=>$status,'completed_at'=>$status==='completed'?now()->subDay():null,'confirmed_by'=>$status==='completed'?User::where('email','kontak@logistiknusantara.id')->value('id'):null]);
   // Real readable synthetic PDF, stored privately; no source or real documents exposed.
   $pdfPath='certificates/demo-synthetic.pdf';
   if(!Storage::disk('local')->exists($pdfPath)){
    $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
    $stream="BT /F1 20 Tf 55 750 Td (SERTIFIKAT DEMO - TIDAK BERLAKU) Tj 0 -35 Td /F1 12 Tf (Data sintetis untuk pengujian Skill Matching.) Tj ET";
    $objects[]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";$pdf="%PDF-1.4\n";$offsets=[0];
    foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}
    $xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";foreach(array_slice($offsets,1) as $offset)$pdf.=sprintf("%010d 00000 n \n",$offset);
    $pdf.="trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";Storage::disk('local')->put($pdfPath,$pdf);
   }
   foreach(['pending','verified','rejected','cancelled'] as $i=>$status)$this->row('certificates',['user_id'=>$status==='verified'?$expert->id:$raka->id,'title'=>'Sertifikat Forklift Demo — '.ucfirst($status)],['certification_body_id'=>$logistik,'training_id'=>$trainings['Forklift Dasar'],'skill_id'=>$sid['Pengoperasian Forklift'],'level'=>2,'file_path'=>$pdfPath,'status'=>$status,'reason'=>$status==='rejected'?'Dokumen demo buram; unggah ulang dokumen yang terbaca.':null,'processed_at'=>in_array($status,['verified','rejected'])?now():null]);
   // Certificate verified scenario belongs to expert, avoiding a false verified claim on Raka's baseline.
   foreach(['saved','applied','processing','interview','accepted','rejected'] as $i=>$status){
    $id=$this->row('job_applications',['user_id'=>$raka->id,'job_title'=>'Lamaran Demo '.($i+1)],['job_vacancy_id'=>$jobs['Staf Gudang'],'company'=>'PT Contoh Lamaran','status'=>$status,'applied_at'=>now()->subDays(6-$i)->toDateString(),'note'=>'Catatan sintetis untuk status '.$status]);
    foreach(array_slice(['saved','applied','processing','interview','accepted','rejected'],0,$i+1) as $state){if(!DB::table('application_events')->where(['job_application_id'=>$id,'status'=>$state])->exists())$this->row('application_events',['job_application_id'=>$id,'status'=>$state],['note'=>'Perubahan status demo']);}
   }
   $cfg=WeightConfiguration::where('is_active',true)->firstOrFail();
   foreach([$raka,$new,$expert,$related,$digital] as $i=>$user){
    $levels=app(MatchingService::class)->levels($user);$pid=$this->row('evaluation_profiles',['code'=>'DEMO-'.($i+1)],['group'=>'Profil sintetis','skills_json'=>json_encode($levels)]);
    foreach(array_slice($jobs,0,4) as $jid)$this->row('evaluation_pairs',['evaluation_profile_id'=>$pid,'job_vacancy_id'=>$jid],['system_score'=>app(MatchingService::class)->score(JobVacancy::findOrFail($jid),$levels,$cfg)['score'],'expert_rating'=>$i%2?null:3,'weight_configuration_id'=>$cfg->id]);
   }
   foreach(['relevant','not_relevant'] as $rating)$this->row('feedback',['user_id'=>$raka->id,'rating'=>$rating,'comment'=>'Umpan balik sintetis: '.$rating],['job_vacancy_id'=>$jobs['Staf Gudang'],'weight_configuration_id'=>$cfg->id]);
   if(!DB::table('recommendation_histories')->where('user_id',$raka->id)->exists())app(MatchingService::class)->snapshot($raka);
  });
  $this->command?->warn('Data DEMO sintetis ditambahkan. Akun/status/data tersembunyi tersedia untuk uji batasan. Lihat docs/DEMO_SCENARIOS.md. Tidak ada email dikirim.');
 }
}
