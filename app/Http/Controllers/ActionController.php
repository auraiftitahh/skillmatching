<?php
namespace App\Http\Controllers;
use App\Models\{User,Skill,JobVacancy,Training,WeightConfiguration,Certificate};
use App\Services\MatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Validation\Rule;
class ActionController {
 private function role(Request $r,string $role):void {abort_unless($r->user() && $r->user()->role===$role,403);}
 private function audit(Request $r,string $action,string $type,?int $id,array $details=[]):void {DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>$action,'subject_type'=>$type,'subject_id'=>$id,'details'=>json_encode($details),'created_at'=>now(),'updated_at'=>now()]);}
 private function ok(string $message='Perubahan berhasil disimpan.'){return back()->with('success',$message);}
 public function profile(Request $r){$this->role($r,'pelamar');$d=$r->validate(['name'=>'required|string|max:150','phone'=>'nullable|max:40','domicile'=>'nullable|string|max:150','employment_status'=>'nullable|in:working,laid_off,graduate,other']);$r->user()->update($d);return $this->ok();}
 public function experience(Request $r){$this->role($r,'pelamar');$d=$r->validate(['type'=>'required|in:work,education,organization','organization'=>'required|string|max:200','title'=>'required|string|max:200','start_date'=>'nullable|date','end_date'=>'nullable|date|after_or_equal:start_date','description'=>'nullable|string|max:2000','job_taxonomy_id'=>['nullable',Rule::exists('jobs_taxonomy','id')->where('is_active',true)]]);$id=$r->integer('id');$row=$id?$r->user()->experiences()->findOrFail($id):null;if($row)$row->update($d);else $r->user()->experiences()->create($d);return redirect('/pelamar/riwayat')->with('success','Riwayat tersimpan.');}
 public function deleteExperience(Request $r,int $id){$this->role($r,'pelamar');$r->user()->experiences()->findOrFail($id)->delete();return $this->ok('Riwayat dihapus.');}
 public function skills(Request $r){$this->role($r,'pelamar');$d=$r->validate(['skill_levels'=>'required|array','skill_levels.*'=>'nullable|integer|between:1,4']);
  foreach($d['skill_levels'] as $id=>$level){if(!ctype_digit((string)$id)||!Skill::whereKey($id)->where('is_active',true)->exists())continue;
   if($level)DB::table('user_skills')->updateOrInsert(['user_id'=>$r->user()->id,'skill_id'=>$id],['level'=>$level,'verification_status'=>((int)DB::table('user_skills')->where(['user_id'=>$r->user()->id,'skill_id'=>$id])->value('level')===(int)$level ? (DB::table('user_skills')->where(['user_id'=>$r->user()->id,'skill_id'=>$id])->value('verification_status')??'self_reported') : 'self_reported'),'updated_at'=>now(),'created_at'=>now()]);
   else DB::table('user_skills')->where(['user_id'=>$r->user()->id,'skill_id'=>$id])->delete();
  }if($r->integer('job_id') && \App\Support\Publication::jobs()->whereKey($r->integer('job_id'))->exists())return redirect('/pelamar/lowongan/detail?job_id='.$r->integer('job_id'))->with('success','Kemampuan tersimpan. Kecocokan target sudah dihitung ulang.');return redirect('/pelamar/profil/kesiapan')->with('success','Keterampilan diperbarui.');}
 public function snapshot(Request $r,MatchingService $svc){$this->role($r,'pelamar');$id=$svc->snapshot($r->user());return redirect('/pelamar/lowongan/riwayat')->with('success',"Rekomendasi #{$id} tersimpan.");}
 public function plan(Request $r){
  $this->role($r,'pelamar');$d=$r->validate(['training_id'=>'required|exists:trainings,id','jobs'=>'nullable|array|max:60','jobs.*'=>'integer|min:1|distinct']);
  DB::transaction(function()use($r,$d){
   DB::table('users')->where('id',$r->user()->id)->lockForUpdate()->first();
   $training=\App\Support\Publication::trainings()->whereKey($d['training_id'])->lockForUpdate()->firstOrFail();
   $existing=DB::table('training_enrollments')->where(['user_id'=>$r->user()->id,'training_id'=>$training->id])->first();
   if($existing && in_array($existing->status,['completed','waiting_confirmation']))throw \Illuminate\Validation\ValidationException::withMessages(['training_id'=>'Pelatihan sudah diselesaikan atau sedang menunggu konfirmasi, tidak dapat dimulai ulang dari rencana.']);
   if($existing && in_array($existing->status,['planned','in_progress']))return;
   $count=DB::table('training_enrollments')->where('user_id',$r->user()->id)->whereIn('status',['planned','in_progress','waiting_confirmation'])->count();
   if($count>=3)throw \Illuminate\Validation\ValidationException::withMessages(['training_id'=>'Rencana belajar maksimal tiga pelatihan.']);
   $values=['status'=>'planned','completed_at'=>null,'confirmed_by'=>null,'outcomes_snapshot'=>DB::table('training_skills')->where('training_id',$training->id)->get(['skill_id','target_level'])->toJson(),'updated_at'=>now()];
   if($existing)DB::table('training_enrollments')->where('id',$existing->id)->update($values);
   else DB::table('training_enrollments')->insert([...$values,'user_id'=>$r->user()->id,'training_id'=>$training->id,'created_at'=>now()]);
  },3);
  $targets=\App\Support\Publication::jobs()->whereIn('id',$d['jobs']??[])->pluck('id')->all();
  return redirect('/pelamar/rencana-belajar'.($targets?'?'.http_build_query(['jobs'=>$targets]):''))->with('success','Pelatihan tersedia di rencana belajar Anda.');
 }
 public function trainingComplete(Request $r){$this->role($r,'pelamar');$d=$r->validate(['training_id'=>'required|exists:trainings,id']);$enroll=DB::table('training_enrollments')->where(['user_id'=>$r->user()->id,'training_id'=>$d['training_id']])->first();abort_unless($enroll,404);abort_unless(in_array($enroll->status,['planned','in_progress']),422,'Pelatihan sudah diproses.');
  $training=\App\Support\Publication::trainings()->findOrFail($d['training_id']);abort_unless(\App\Support\Publication::prerequisitesMet($r->user()->id,$training->id),422,'Selesaikan prasyarat pelatihan terlebih dahulu.');if($training->certification_body_id)abort_unless(DB::table('certification_bodies')->where('id',$training->certification_body_id)->whereIn('id',\App\Support\Publication::bodyIds())->exists(),422,'Lembaga tidak aktif. Penyelesaian tidak dapat diproses otomatis.');$hasActiveBody=(bool)$training->certification_body_id;$status=$hasActiveBody?'waiting_confirmation':'completed';
  DB::transaction(function()use($r,$training,$status,$enroll){DB::table('training_enrollments')->where(['user_id'=>$r->user()->id,'training_id'=>$training->id])->whereNotIn('status',['completed'])->update(['status'=>$status,'completed_at'=>$status==='completed'?now():null,'updated_at'=>now()]);
   if($status==='completed')$this->applyTrainingSkills($r->user()->id,$training->id,$enroll->outcomes_snapshot??null);});return redirect('/pelamar/pelatihan/aktivitas')->with('success',$status==='completed'?'Penyelesaian tersimpan.':'Menunggu konfirmasi lembaga.');}
 private function applyTrainingSkills(int $userId,int $trainingId,?string $snapshot=null):void {foreach($snapshot?json_decode($snapshot):DB::table('training_skills')->where('training_id',$trainingId)->get() as $skill){$before=DB::table('user_skills')->where(['user_id'=>$userId,'skill_id'=>$skill->skill_id])->first();$level=max((int)($before->level??0),(int)$skill->target_level);DB::table('user_skills')->updateOrInsert(['user_id'=>$userId,'skill_id'=>$skill->skill_id],['level'=>$level,'verification_status'=>($level > (int)($before->level??0) ? 'self_reported' : ($before->verification_status??'self_reported')),'created_at'=>now(),'updated_at'=>now()]);}}
 public function certificate(Request $r){$this->role($r,'pelamar');$d=$r->validate(['title'=>'required|string|max:200','certification_body_id'=>'required|exists:certification_bodies,id','training_id'=>'required|exists:trainings,id','skill_id'=>'required|exists:skills,id','level'=>'required|integer|between:1,4','file'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120']);
  $body=DB::table('certification_bodies')->where('id',$d['certification_body_id'])->whereIn('id',\App\Support\Publication::bodyIds())->firstOrFail();$training=\App\Support\Publication::trainings()->whereKey($d['training_id'])->where('certification_body_id',$body->id)->firstOrFail();
  $cap=DB::table('training_skills')->where(['training_id'=>$training->id,'skill_id'=>$d['skill_id']])->value('target_level');abort_unless($cap&&$d['level']<=$cap,422,'Level melebihi capaian pelatihan.');
  $enrolled=DB::table('training_enrollments')->where(['user_id'=>$r->user()->id,'training_id'=>$training->id])->whereIn('status',['completed','waiting_confirmation'])->exists();abort_unless($enrolled,422,'Pelatihan perlu ditandai selesai lebih dahulu.');
  $path=$r->file('file')->store('certificates','local');try {DB::table('certificates')->insert(['user_id'=>$r->user()->id,'certification_body_id'=>$body->id,'training_id'=>$training->id,'skill_id'=>$d['skill_id'],'title'=>$d['title'],'level'=>$d['level'],'file_path'=>$path,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);}catch(\Throwable $e){\Illuminate\Support\Facades\Storage::disk('local')->delete($path);throw $e;}
  return redirect('/pelamar/sertifikat')->with('success','Pengajuan dikirim ke lembaga.');}
 public function cancelCertificate(Request $r,int $id){$this->role($r,'pelamar');$count=DB::table('certificates')->where(['id'=>$id,'user_id'=>$r->user()->id,'status'=>'pending'])->update(['status'=>'cancelled','updated_at'=>now()]);abort_unless($count,422,'Pengajuan tidak dapat dibatalkan.');return $this->ok('Pengajuan dibatalkan.');}
 public function certificateFile(Request $r,int $id){$cert=Certificate::findOrFail($id);$u=$r->user();$allowed=($u->role==='pelamar'&&$cert->user_id===$u->id)||($u->role==='sertifikasi'&&$u->certificationBody?->status==='active'&&$cert->certification_body_id===$u->certificationBody?->id);abort_unless($allowed,403);$disk=\Illuminate\Support\Facades\Storage::disk('local');abort_unless($disk->exists($cert->file_path),404,'Dokumen tidak tersedia.');if($r->boolean('preview'))return $disk->response($cert->file_path,basename($cert->file_path),['X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"sandbox; frame-ancestors 'self'"],'inline');return $disk->download($cert->file_path,basename($cert->file_path));}
 public function graduation(Request $r,int $id){$this->role($r,'sertifikasi');abort_unless($r->user()->certificationBody?->status==='active',403);$body=$r->user()->certificationBody;$enroll=DB::table('training_enrollments as e')->join('trainings as t','t.id','=','e.training_id')->where('e.id',$id)->where('t.certification_body_id',$body->id)->whereIn('e.user_id',User::where('is_active',true)->whereNotNull('email_verified_at')->select('id'))->select('e.*')->first();abort_unless($enroll,403);
  abort_unless($enroll->status==='waiting_confirmation',422,'Kelulusan sudah diproses atau belum diminta.');DB::transaction(function()use($r,$enroll){$locked=DB::table('training_enrollments')->where('id',$enroll->id)->lockForUpdate()->first();abort_unless($locked && $locked->status==='waiting_confirmation',422,'Kelulusan sudah diproses.');DB::table('training_enrollments')->where('id',$enroll->id)->update(['status'=>'completed','completed_at'=>now(),'confirmed_by'=>$r->user()->id,'updated_at'=>now()]);$outcomes=$locked->outcomes_snapshot?json_decode($locked->outcomes_snapshot):DB::table('training_skills')->where('training_id',$enroll->training_id)->get();foreach($outcomes as $outcome)\App\Services\SkillEvidenceService::record($enroll->user_id,(int)$outcome->skill_id,(int)$outcome->target_level,'catalog_graduation',$enroll->id,$r->user()->id);});$this->audit($r,'graduation.confirm','training_enrollments',$id);return $this->ok('Kelulusan dikonfirmasi.');}
 public function verifyCertificate(Request $r,int $id){$this->role($r,'sertifikasi');abort_unless($r->user()->certificationBody?->status==='active',403);$d=$r->validate(['decision'=>'required|in:verified,rejected','reason'=>'required_if:decision,rejected|nullable|string|max:2000']);$cert=Certificate::whereKey($id)->where('certification_body_id',$r->user()->certificationBody->id)->firstOrFail();abort_unless(User::whereKey($cert->user_id)->where('is_active',true)->whereNotNull('email_verified_at')->exists(),403,'Akun pemohon tidak aktif atau belum terverifikasi.');abort_unless($cert->status==='pending',422,'Permintaan sudah diproses.');DB::transaction(function()use($r,$cert,$d){$cert=Certificate::whereKey($cert->id)->lockForUpdate()->firstOrFail();abort_unless($cert->status==='pending',422,'Permintaan sudah diproses.');$cert->update(['status'=>$d['decision'],'reason'=>$d['reason']??null,'processed_at'=>now()]);if($d['decision']==='verified'){
    $existing=DB::table('user_skills')->where(['user_id'=>$cert->user_id,'skill_id'=>$cert->skill_id])->first();
    $previousLevel=(int)($existing->level??0);
    $previousStatus=$existing->verification_status??'self_reported';
    $resultLevel=max($previousLevel,(int)$cert->level);
    // Lower-level certificates must not downgrade an already verified higher-level skill.
    $resultStatus=$previousLevel>(int)$cert->level ? $previousStatus : 'verified';
    DB::table('user_skills')->updateOrInsert(['user_id'=>$cert->user_id,'skill_id'=>$cert->skill_id],['level'=>$resultLevel,'verification_status'=>$resultStatus,'created_at'=>now(),'updated_at'=>now()]);
   }});$this->audit($r,'certificate.'.$d['decision'],'certificates',$id);return $this->ok('Keputusan verifikasi tersimpan.');}
 public function application(Request $r){$this->role($r,'pelamar');$d=$r->validate(['job_title'=>'required|string|max:200','company'=>'nullable|string|max:200','job_vacancy_id'=>'nullable|exists:job_vacancies,id','status'=>'required|in:saved,applied,processing,interview,accepted,rejected','applied_at'=>'nullable|date','note'=>'nullable|string|max:2000']);$id=$r->integer('id');if($id){$row=DB::table('job_applications')->where(['id'=>$id,'user_id'=>$r->user()->id])->first();abort_unless($row,404);DB::table('job_applications')->where('id',$id)->update([...$d,'updated_at'=>now()]);}else{$id=DB::table('job_applications')->insertGetId([...$d,'user_id'=>$r->user()->id,'created_at'=>now(),'updated_at'=>now()]);}DB::table('application_events')->insert(['job_application_id'=>$id,'status'=>$d['status'],'note'=>$d['note']??null,'created_at'=>now(),'updated_at'=>now()]);return redirect('/pelamar/lamaran')->with('success','Catatan lamaran disimpan.');}
 public function feedback(Request $r){$this->role($r,'pelamar');if($r->filled('job_vacancy_id'))\App\Support\Publication::jobs()->whereKey($r->integer('job_vacancy_id'))->firstOrFail();$d=$r->validate(['job_vacancy_id'=>'nullable|exists:job_vacancies,id','rating'=>'required|in:relevant,not_relevant','comment'=>'nullable|string|max:2000']);DB::table('feedback')->insert([...$d,'user_id'=>$r->user()->id,'weight_configuration_id'=>WeightConfiguration::where('is_active',true)->value('id'),'created_at'=>now(),'updated_at'=>now()]);return redirect('/pelamar/lowongan')->with('success','Umpan balik diterima.');}
 public function weight(Request $r){$this->role($r,'admin');$d=$r->validate(['required_weight'=>'required|integer|between:1,5','preferred_weight'=>'required|integer|between:1,5','related_factor'=>'required|numeric|between:0,1','threshold'=>'required|integer|between:0,100','reason'=>'required|string|min:5|max:1000']);DB::transaction(function()use($r,$d){DB::table('weight_configurations')->orderBy('id')->lockForUpdate()->get();$next=1+(int)DB::table('weight_configurations')->max('version');DB::table('weight_configurations')->where('is_active',true)->update(['is_active'=>false,'updated_at'=>now()]);$id=DB::table('weight_configurations')->insertGetId([...$d,'version'=>$next,'created_by'=>$r->user()->id,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);$this->audit($r,'weight.publish','weight_configurations',$id,$d);});return redirect('/admin/bobot')->with('success','Versi konfigurasi baru diterbitkan.');}
 public function account(Request $r,int $id){$this->role($r,'admin');$d=$r->validate(['decision'=>'required|in:activate,deactivate,verify,reject','reason'=>'required|string|min:5|max:500']);$user=User::findOrFail($id);abort_if($user->role==='admin',403,'Akun Admin tidak dapat diubah melalui pengelolaan akun.');abort_if(in_array($d['decision'],['verify','reject']) && $user->role!=='sertifikasi',422,'Verifikasi hanya untuk Badan Sertifikasi.');if($user->role==='sertifikasi'&&$d['decision']==='activate'){ $currentBody=DB::table('certification_bodies')->where('user_id',$user->id)->first(); if($currentBody && in_array($currentBody->status,['pending','rejected'],true))return back()->withErrors(['decision'=>'Lembaga perlu melalui verifikasi Admin sebelum dapat diaktifkan.']); }abort_if($user->id===$r->user()->id&&$d['decision']==='deactivate',422,'Tidak dapat menonaktifkan diri sendiri.');DB::transaction(function()use($r,$user,$d){$active=in_array($d['decision'],['activate','verify']);$user->update(['is_active'=>$active]);if($user->role==='sertifikasi'){$state=match($d['decision']){'verify'=>'active','reject'=>'rejected','activate'=>'active',default=>'disabled'};DB::table('certification_bodies')->where('user_id',$user->id)->update(['status'=>$state,'verified_at'=>$state==='active'?now():($d['decision']==='deactivate'?DB::table('certification_bodies')->where('user_id',$user->id)->value('verified_at'):null),'updated_at'=>now()]);}$this->audit($r,'account.'.$d['decision'],'users',$user->id,['reason'=>$d['reason']]);});return $this->ok('Status akun diperbarui.');}
 public function catalog(Request $r,string $type){$this->role($r,'admin');abort_unless(in_array($type,['skills','jobs_taxonomy','job_vacancies','trainings']),404);
  abort_if($type==='skills' && !$r->integer('id'),403,'Tambahkan skill melalui persyaratan lowongan atau tinjauan pengajuan lembaga.');
  abort_if($type==='trainings',403,'Perubahan pelatihan melalui pengajuan lembaga dan tinjauan Admin.');
  $data=$r->validate(match($type){
   'skills'=>['name'=>['required','string','max:150',Rule::unique('skills','name')->ignore($r->integer('id'))],'category'=>'required|string|max:150','aliases'=>'nullable|string|max:1000','is_active'=>'required|boolean'],
   'jobs_taxonomy'=>['name'=>'required|string|max:150','is_active'=>'required|boolean'],
   'job_vacancies'=>['title'=>'required|string|max:190','company'=>'required|string|max:190','description'=>'nullable|string','location'=>'nullable|string|max:150','employment_type'=>'nullable|string|max:100','source'=>'required|string|max:190','source_url'=>'required|url|max:255','fetched_at'=>'required|date','expires_at'=>'nullable|date','status'=>'required|in:draft,active,closed,expired,disabled'],
   'trainings'=>['title'=>'required|string|max:190','provider'=>'required|string|max:190','description'=>'nullable|string','mode'=>'required|in:online,offline,hybrid','duration_hours'=>'required|integer|min:1','weekly_hours'=>'required|integer|min:1','cost'=>'required|numeric|min:0','source'=>'required|string|max:190','source_url'=>'required|url|max:255','fetched_at'=>'required|date','status'=>'required|in:draft,active,disabled','certification_body_id'=>'nullable|exists:certification_bodies,id'],});
  if($type==='skills')$data['aliases']=json_encode(array_values(array_unique(array_filter(array_map('trim',explode(',',$data['aliases']??''))))),JSON_UNESCAPED_UNICODE);
  if($type==='job_vacancies'){
   $data['expires_at']??=\Carbon\Carbon::parse($data['fetched_at'])->addDays(30)->toDateString();
   if($data['status']==='active' && $r->integer('id')>0 && !DB::table('job_required_skills')->where('job_vacancy_id',$r->integer('id'))->exists())return back()->withErrors(['status'=>'Tambahkan minimal satu skill sebelum menerbitkan lowongan.']);
   if($data['status']==='active'&&$r->integer('id')===0)$data['status']='draft';
  }
  if($type==='trainings' && $data['status']==='active' && !empty($data['certification_body_id'])){abort_unless(DB::table('certification_bodies')->where('id',$data['certification_body_id'])->whereIn('id',\App\Support\Publication::bodyIds())->exists(),422,'Lembaga harus aktif dan email akunnya terverifikasi.');}
  if($type==='trainings'&&$data['status']==='active'){if($r->integer('id')===0)$data['status']='draft';elseif(!DB::table('training_skills')->where('training_id',$r->integer('id'))->exists())return back()->withErrors(['status'=>'Tambahkan minimal satu skill yang diajarkan sebelum mengaktifkan pelatihan.']);}
  $id=$r->integer('id');$isEdit=$id>0;
  $previous=$isEdit?DB::table($type)->where('id',$id)->first():null;
  if($isEdit)abort_unless($previous,404);
  if($isEdit)DB::table($type)->where('id',$id)->update([...$data,'updated_at'=>now()]);
  else $id=DB::table($type)->insertGetId([...$data,'created_at'=>now(),'updated_at'=>now()]);
  $auditDetails=[];
  if($type==='skills'){
    $auditDetails['name']=$data['name'];
    if($previous && (bool)$previous->is_active !== (bool)$data['is_active']){
      $auditDetails['status']=$data['is_active']?'Aktif':'Nonaktif';
    }
  }
  $this->audit($r,$isEdit?'catalog.save':'catalog.create',$type,$id,$auditDetails);
  if($r->boolean('continue')) {
   $next=match($type){'skills'=>'skill/detail','jobs_taxonomy'=>'jabatan/pemetaan','job_vacancies'=>'lowongan/persyaratan','trainings'=>'pelatihan/skill'};
   return redirect('/admin/'.$next.'?'.http_build_query(['id'=>$id,'job_taxonomy_id'=>$type==='jobs_taxonomy'?$id:null]))->with('success','Data tersimpan. Lanjutkan langkah berikutnya.');
  }
  return redirect('/admin/'.match($type){'skills'=>'skill','jobs_taxonomy'=>'jabatan','job_vacancies'=>'lowongan','trainings'=>'pelatihan'})->with('success','Data berhasil disimpan.');
 }
 public function catalogStatus(Request $r,string $type,int $id){
  $this->role($r,'admin');
  abort_unless(in_array($type,['jobs_taxonomy','job_vacancies','trainings'],true),404);
  $data=$r->validate(['is_active'=>'required|boolean']);
  $on=(bool)$data['is_active'];
  $row=DB::table($type)->where('id',$id)->first();abort_unless($row,404);
  if($type==='jobs_taxonomy'){$column='is_active';$value=$on;}
  elseif($type==='job_vacancies'){
   $column='status';$value=$on?'active':'disabled';
   if($on && !DB::table('job_required_skills')->where('job_vacancy_id',$id)->exists())return back()->withErrors(['status'=>'Lengkapi persyaratan keterampilan sebelum mengaktifkan lowongan.']);
   if($on && \Carbon\Carbon::parse($row->expires_at)->isBefore(now()->startOfDay()))return back()->withErrors(['status'=>'Perbarui tanggal kedaluwarsa sebelum mengaktifkan lowongan.']);
  }else{
   if($on && $row->status==='draft')return back()->withErrors(['status'=>'Draft pelatihan perlu disetujui melalui tinjauan pengajuan lembaga.']);
   $column='status';$value=$on?'active':'disabled';
   if($on && $row->certification_body_id && !DB::table('certification_bodies')->where('id',$row->certification_body_id)->whereIn('id',\App\Support\Publication::bodyIds())->exists())return back()->withErrors(['status'=>'Lembaga belum disetujui, tidak aktif, atau email belum diverifikasi.']);
   if($on && !DB::table('training_skills')->where('training_id',$id)->exists())return back()->withErrors(['status'=>'Tambahkan keterampilan yang diajarkan sebelum mengaktifkan pelatihan.']);
  }
  DB::table($type)->where('id',$id)->update([$column=>$value,'updated_at'=>now()]);
  $this->audit($r,'catalog.status',$type,$id,['status'=>$on?'Aktif':'Nonaktif']);
  return back()->with('success',$on?'Data berhasil diaktifkan.':'Data dinonaktifkan tanpa dihapus.');
 }
 public function skillStatus(Request $r, int $id){
  $this->role($r,'admin');
  $d=$r->validate(['is_active'=>'required|boolean']);
  $skill=Skill::findOrFail($id);
  $newStatus=(bool)$d['is_active'];
  if((bool)$skill->is_active===$newStatus)return redirect('/admin/skill')->with('success','Status keterampilan tidak berubah.');
  $skill->update(['is_active'=>$newStatus]);
  $this->audit($r,'skill.status','skills',$skill->id,['name'=>$skill->name,'status'=>$newStatus?'Aktif':'Nonaktif']);
  return redirect('/admin/skill')->with('success',$newStatus?'Keterampilan diaktifkan kembali.':'Keterampilan dinonaktifkan. Data tetap tersimpan.');
 }
 public function deleteSkill(Request $r,int $id){
  $this->role($r,'admin');
  $skill=Skill::findOrFail($id);
  // Jangan pernah menghapus skill yang telah menjadi bagian dari profil, lowongan,
  // pelatihan, pemetaan jabatan, relasi, atau sertifikat. Perubahan status aman digunakan.
  $references=[
   ['user_skills','skill_id'],['job_required_skills','skill_id'],
   ['training_skills','skill_id'],['job_skills_mapping','skill_id'],
   ['skill_relations','skill_id'],['skill_relations','related_skill_id'],
   ['certificates','skill_id'],['external_certificates','skill_id'],['skill_evidence','skill_id']
  ];
  foreach($references as [$table,$column]){
    if(DB::table($table)->where($column,$id)->exists()){
      return redirect('/admin/skill')->withErrors(['hapus'=>'Keterampilan "'.$skill->name.'" sudah digunakan oleh data lain sehingga tidak dapat dihapus permanen. Pilih Nonaktifkan agar tetap tersedia dalam riwayat.']);
    }
  }
  // Riwayat hasil pencocokan dan profil uji dapat menyimpan ID skill sebagai kunci JSON.
  // Cegah penghapusan fisik jika skill pernah dipakai di snapshot, walaupun relasi kini dihapus.
  foreach (['recommendation_histories'=>'profile_snapshot','evaluation_profiles'=>'skills_json'] as $table=>$column) {
    if (DB::table($table)->where($column,'like','%"'.$id.'"%')->exists()) {
      return redirect('/admin/skill')->withErrors(['hapus'=>'Keterampilan "'.$skill->name.'" masih digunakan oleh riwayat rekomendasi atau profil uji. Nonaktifkan agar riwayat tetap utuh.']);
    }
  }
  // Skill yang tersimpan sebagai persyaratan dalam hasil rekomendasi lama juga dilindungi.
  if (DB::table('recommendation_histories')->where('results','like','%"skill_id":'.$id.',%')->exists()) {
    return redirect('/admin/skill')->withErrors(['hapus'=>'Keterampilan "'.$skill->name.'" tercantum dalam hasil rekomendasi lama. Nonaktifkan agar hasil sebelumnya tetap dapat ditelusuri.']);
  }
  $name=$skill->name;
  DB::table('skill_name_registry')->where('skill_id',$skill->id)->delete();
  $skill->delete();
  $this->audit($r,'skill.delete','skills',$id,['name'=>$name]);
  return redirect('/admin/skill')->with('success','Keterampilan "'.$name.'" berhasil dihapus karena belum digunakan oleh data lain.');
 }
 public function createVacancySkill(Request $request, int $id, \App\Services\CatalogSkillService $catalog)
 {
     $this->role($request, 'admin');
     $data = $request->validate([
         'new_skill_name' => 'required|string|max:150',
         'new_skill_category' => 'required|string|max:150',
         'new_skill_level' => 'required|integer|between:1,4',
         'new_skill_importance' => 'required|in:required,preferred',
     ]);

     DB::transaction(function () use ($request, $id, $catalog, $data): void {
         JobVacancy::query()->lockForUpdate()->findOrFail($id);
         $config = WeightConfiguration::where('is_active', true)->latest('version')->firstOrFail();
         $skillId = $catalog->resolveName($data['new_skill_name'], $data['new_skill_category']);
         $skill = Skill::query()->lockForUpdate()->findOrFail($skillId);
         if (!$skill->is_active) {
             throw \Illuminate\Validation\ValidationException::withMessages([
                 'new_skill_name' => 'Skill ini sudah ada dan sedang nonaktif. Aktifkan melalui katalog atau pilih skill lain.',
             ]);
         }

         $created = DB::table('job_required_skills')->insertOrIgnore([
             'job_vacancy_id' => $id,
             'skill_id' => $skillId,
             'minimum_level' => $data['new_skill_level'],
             'importance' => $data['new_skill_importance'],
             'weight' => $data['new_skill_importance'] === 'required' ? $config->required_weight : $config->preferred_weight,
             'is_override' => false,
             'created_at' => now(),
             'updated_at' => now(),
         ]);
         if (!$created) {
             throw \Illuminate\Validation\ValidationException::withMessages([
                 'new_skill_name' => 'Skill ini sudah menjadi persyaratan. Ubah pengaturannya di daftar keterampilan tersimpan.',
             ]);
         }

         $this->audit($request, 'vacancy.requirement', 'job_vacancies', $id, ['skill_id' => $skillId, 'name' => $skill->name]);
     }, 3);

     return redirect('/admin/lowongan/persyaratan?id='.$id)->with('success', 'Skill aktif di katalog dan sudah menjadi persyaratan lowongan.');
 }

 public function attachJobSkill(Request $r,int $id){$this->role($r,'admin');$job=JobVacancy::findOrFail($id);$d=$r->validate(['skill_id'=>'required|exists:skills,id','minimum_level'=>'required|integer|between:1,4','importance'=>'required|in:required,preferred','weight'=>'required|integer|between:1,5','reason'=>'nullable|string|max:500']);$cfg=WeightConfiguration::where('is_active',true)->firstOrFail();$default=$d['importance']==='required'?$cfg->required_weight:$cfg->preferred_weight;
  if((int)$d['weight']!==(int)$default&&!filled($d['reason']??null))return back()->withErrors(['reason'=>'Perubahan bobot default memerlukan alasan.']);
  DB::table('job_required_skills')->updateOrInsert(['job_vacancy_id'=>$job->id,'skill_id'=>$d['skill_id']],['minimum_level'=>$d['minimum_level'],'importance'=>$d['importance'],'weight'=>$d['weight'],'is_override'=>(int)$d['weight']!==(int)$default,'override_reason'=>$d['reason']??null,'created_at'=>now(),'updated_at'=>now()]);$this->audit($r,'vacancy.requirement','job_vacancies',$id,$d);if($r->boolean('continue'))return redirect('/admin/lowongan/detail?id='.$id)->with('success','Persyaratan tersimpan. Tinjau sebelum publikasi.');return $this->ok();}
 public function attachTrainingSkill(Request $r,int $id){$this->role($r,'admin');abort(403,'Capaian diubah melalui pengajuan lembaga.');$training=Training::findOrFail($id);$d=$r->validate(['skill_id'=>'required|exists:skills,id','target_level'=>'required|integer|between:1,4']);DB::table('training_skills')->updateOrInsert(['training_id'=>$id,'skill_id'=>$d['skill_id']],['target_level'=>$d['target_level'],'created_at'=>now(),'updated_at'=>now()]);$this->audit($r,'training.skill','trainings',$id,$d);if($r->boolean('continue'))return redirect('/admin/pelatihan/prasyarat?id='.$id)->with('success','Capaian tersimpan. Atur prasyarat.');return $this->ok();}
 public function mapping(Request $r){$this->role($r,'admin');$d=$r->validate(['job_taxonomy_id'=>'required|exists:jobs_taxonomy,id','skill_id'=>'required|exists:skills,id']);DB::table('job_skills_mapping')->insertOrIgnore($d);$this->audit($r,'taxonomy.mapping','jobs_taxonomy',$d['job_taxonomy_id'],$d);return $this->ok();}
 public function relation(Request $r){$this->role($r,'admin');$d=$r->validate(['skill_id'=>'required|exists:skills,id','related_skill_id'=>'required|exists:skills,id|different:skill_id']);DB::table('skill_relations')->insertOrIgnore($d);$this->audit($r,'taxonomy.relation','skills',$d['skill_id'],$d);return $this->ok();}
 public function prerequisite(Request $r,int $id){$this->role($r,'admin');$d=$r->validate(['prerequisite_id'=>'required|exists:trainings,id']);abort_if($id===$d['prerequisite_id'],422,'Tidak boleh menjadi prasyarat dirinya sendiri.');
  // Stop circular training chains, including indirect A → B → C → A dependencies.
  $visited=[];$queue=[(int)$d['prerequisite_id']];
  while($queue){$current=array_shift($queue);if($current===$id)return back()->withErrors(['prerequisite_id'=>'Prasyarat tidak boleh membentuk siklus pelatihan.']);if(isset($visited[$current]))continue;$visited[$current]=true;
   foreach(DB::table('training_prerequisites')->where('training_id',$current)->pluck('prerequisite_id') as $next)$queue[]=(int)$next;
  }
  DB::table('training_prerequisites')->insertOrIgnore(['training_id'=>$id,'prerequisite_id'=>$d['prerequisite_id']]);$this->audit($r,'training.prerequisite','trainings',$id,$d);return $this->ok();}
 public function evaluation(Request $r,string $type){$this->role($r,'admin');abort_unless(in_array($type,['profile','pair']),404);if($type==='profile'){
    $d=$r->validate([
      'code'=>'required|string|max:40|unique:evaluation_profiles,code',
      'group'=>'nullable|string|max:100',
      'skill_ids'=>'required|array|min:1|max:150',
      'skill_ids.*'=>'required|integer|distinct|exists:skills,id',
      'skill_levels'=>'required|array|min:1',
      'skill_levels.*'=>'required|integer|between:1,4',
    ]);
    if(count($d['skill_ids'])!==count($d['skill_levels'])){
      return back()->withErrors(['skill_ids'=>'Lengkapi tingkat kemampuan setiap keterampilan.'])->withInput();
    }
    $levels=[];
    foreach($d['skill_ids'] as $index=>$skillId){
      $levels[(int)$skillId]=(int)$d['skill_levels'][$index];
    }
    $id=DB::table('evaluation_profiles')->insertGetId([
      'code'=>$d['code'], 'group'=>$d['group']??null,
      'skills_json'=>json_encode($levels), 'created_at'=>now(), 'updated_at'=>now(),
    ]);
  }else{$d=$r->validate(['evaluation_profile_id'=>'required|exists:evaluation_profiles,id','job_vacancy_id'=>'required|exists:job_vacancies,id','expert_rating'=>'nullable|integer|between:1,4']);$profile=DB::table('evaluation_profiles')->find($d['evaluation_profile_id']);$job=JobVacancy::findOrFail($d['job_vacancy_id']);$pairConfig=app(MatchingService::class)->config();$score=app(MatchingService::class)->score($job,json_decode($profile->skills_json,true)??[],$pairConfig)['score'];DB::table('evaluation_pairs')->updateOrInsert(['evaluation_profile_id'=>$d['evaluation_profile_id'],'job_vacancy_id'=>$d['job_vacancy_id']],['weight_configuration_id'=>$pairConfig->id,'expert_rating'=>$d['expert_rating']??null,'system_score'=>$score,'created_at'=>now(),'updated_at'=>now()]);$id=DB::table('evaluation_pairs')->where(['evaluation_profile_id'=>$d['evaluation_profile_id'],'job_vacancy_id'=>$d['job_vacancy_id']])->value('id');}$this->audit($r,'evaluation.'.$type,'evaluation_'.$type.'s',$id,$d);return $this->ok();}
 public function export(Request $r){$this->role($r,'admin');$r->validate(['from'=>'nullable|date','to'=>'nullable|date|after_or_equal:from','version'=>'nullable|integer|exists:weight_configurations,id']);$query=DB::table('evaluation_pairs as ep')->join('evaluation_profiles as p','p.id','=','ep.evaluation_profile_id')->join('job_vacancies as j','j.id','=','ep.job_vacancy_id')->select('p.code','p.group','j.title','ep.system_score','ep.expert_rating','ep.created_at','ep.weight_configuration_id');if($r->filled('version'))$query->where('ep.weight_configuration_id',$r->integer('version'));if($r->from)$query->whereDate('ep.created_at','>=',$r->from);if($r->to)$query->whereDate('ep.created_at','<=',$r->to);$rows=$query->get();if($rows->isEmpty())return back()->withErrors(['from'=>'Tidak ada data evaluasi untuk kriteria yang dipilih.']);return response()->streamDownload(function()use($rows){$f=fopen('php://output','w');fputcsv($f,['Profil','Kelompok','Lowongan','Skor Sistem','Penilaian Ahli (1–4)','Tanggal','ID Konfigurasi (kosong jika histori lama)']);foreach($rows as $row)fputcsv($f,array_values((array)$row));fclose($f);},'laporan-evaluasi-'.now()->format('Ymd').'.csv',['Content-Type'=>'text/csv']);}
 public function import(Request $r,string $type){$this->role($r,'admin');abort_unless(in_array($type,['job_vacancies','trainings']),404);$r->validate(['file'=>'required|file|mimes:csv,txt|max:3072']);$handle=fopen($r->file('file')->getRealPath(),'r');$header=array_map(fn($v)=>strtolower(trim($v)),fgetcsv($handle)?:[]);
  $required=$type==='job_vacancies'?['title','company','source','fetched_at']:['title','provider','cost','duration_hours','mode','fetched_at'];$missing=array_diff($required,$header);if($missing){fclose($handle);return back()->withErrors(['file'=>'Kolom wajib tidak ada: '.implode(', ',$missing)]);}
  $errors=[];$ok=0;$line=1;while(($values=fgetcsv($handle))!==false && $line<1001){$line++;if(count($values)!==count($header)){$errors[]="Baris {$line}: jumlah kolom tidak sesuai";continue;}$row=array_combine($header,$values);$missingValues=array_filter($required,fn($k)=>!trim($row[$k]??''));if($missingValues){$errors[]="Baris {$line}: data wajib kosong";continue;}
   try{if($type==='job_vacancies'){if(DB::table('job_vacancies')->where('title',$row['title'])->where('company',$row['company'])->exists())throw new \RuntimeException('duplicate');$date=\Carbon\Carbon::parse($row['fetched_at'])->toDateString();DB::table('job_vacancies')->insert(['title'=>$row['title'],'company'=>$row['company'],'source'=>$row['source'],'source_url'=>$row['source_url']??null,'fetched_at'=>$date,'expires_at'=>filled($row['expires_at']??null)?$row['expires_at']:\Carbon\Carbon::parse($date)->addDays(30),'status'=>'draft','created_at'=>now(),'updated_at'=>now()]);}else{if(DB::table('trainings')->where('title',$row['title'])->where('provider',$row['provider'])->exists())throw new \RuntimeException('duplicate');DB::table('trainings')->insert(['title'=>$row['title'],'provider'=>$row['provider'],'cost'=>(float)$row['cost'],'duration_hours'=>max(1,(int)$row['duration_hours']),'weekly_hours'=>max(1,(int)($row['weekly_hours']??1)),'mode'=>in_array($row['mode'],['online','offline','hybrid'])?$row['mode']:'online','fetched_at'=>\Carbon\Carbon::parse($row['fetched_at'])->toDateString(),'status'=>'draft','created_at'=>now(),'updated_at'=>now()]);}$ok++;}catch(\Throwable $e){$errors[]="Baris {$line}: data gagal divalidasi/disimpan";}
  }fclose($handle);$this->audit($r,'catalog.csv_import',$type,null,['success'=>$ok,'errors'=>count($errors)]);return back()->with('success',"Berhasil: {$ok}. Gagal: ".count($errors))->with('import_errors',$errors);}

 public function enrollmentStatus(Request $r,int $id){
  $this->role($r,'pelamar');$data=$r->validate(['status'=>'required|in:in_progress,planned']);
  if($data['status']==='in_progress'){$target=DB::table('training_enrollments')->where(['id'=>$id,'user_id'=>$r->user()->id])->firstOrFail();\App\Support\Publication::trainings()->whereKey($target->training_id)->firstOrFail();abort_unless(\App\Support\Publication::prerequisitesMet($r->user()->id,$target->training_id),422,'Selesaikan prasyarat sebelum mulai belajar.');}
  $affected=DB::table('training_enrollments')->where('id',$id)->where('user_id',$r->user()->id)->whereIn('status',['planned','in_progress'])->update(['status'=>$data['status'],'updated_at'=>now()]);
  abort_unless($affected,404,'Rencana belajar tidak dapat diubah.');return $this->ok('Status pelatihan diperbarui.');
 }
 public function removeRelation(Request $r,string $type,int $id){
  $this->role($r,'admin');
  abort_if($type==='training-skill',403,'Gunakan pengajuan revisi lembaga.');
  $map=['job-skill'=>['job_required_skills','job_vacancy_id'],'training-skill'=>['training_skills','training_id'],'training-prerequisite'=>['training_prerequisites','training_id'],'job-mapping'=>['job_skills_mapping','job_taxonomy_id'],'skill-relation'=>['skill_relations','skill_id']];
  abort_unless(isset($map[$type]),404);
  [$table,$parent]=$map[$type];$row=DB::table($table)->where('id',$id)->firstOrFail();
  if($type==='job-skill'){
   $job=DB::table('job_vacancies')->find($row->job_vacancy_id);
   if($job?->status==='active' && DB::table($table)->where('job_vacancy_id',$row->job_vacancy_id)->count()<=1)
    return back()->withErrors(['skill'=>'Lowongan aktif harus memiliki minimal satu persyaratan skill.']);
  }
  if($type==='training-skill'){
   $training=DB::table('trainings')->find($row->training_id);
   if($training?->status==='active' && DB::table($table)->where('training_id',$row->training_id)->count()<=1)
    return back()->withErrors(['skill'=>'Pelatihan aktif memerlukan minimal satu skill capaian.']);
  }
  DB::table($table)->where('id',$id)->delete();$this->audit($r,'relation.remove',$table,$id);
  return $this->ok('Relasi berhasil dihapus.');
 }
}
