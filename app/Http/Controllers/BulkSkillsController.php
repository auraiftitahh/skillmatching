<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Validator};
use App\Models\WeightConfiguration;
class BulkSkillsController
{
 public function save(Request $r,string $kind,int $id) {
        abort_if($kind==='training',403,'Gunakan pengajuan revisi lembaga.');
  abort_unless($r->user()->role==='admin',403);
  $map=['vacancy'=>['job_vacancies','job_required_skills','job_vacancy_id'],'training'=>['trainings','training_skills','training_id'],'mapping'=>['jobs_taxonomy','job_skills_mapping','job_taxonomy_id']];
  abort_unless(isset($map[$kind]),404);[$parent,$table,$foreign]=$map[$kind];abort_unless(DB::table($parent)->where('id',$id)->exists(),404);
  $r->validate(['items'=>'required|array|max:150','items.*'=>'array']);$selected=array_filter($r->input('items'),fn($x)=>($x['selected']??null)==='1');
  if(!$selected)return back()->withErrors(['items'=>'Pilih minimal satu keterampilan.']);
  $cfg=WeightConfiguration::where('is_active',true)->firstOrFail();$prepared=[];
  foreach($selected as $sid=>$item){
   abort_unless(ctype_digit((string)$sid)&&DB::table('skills')->where('id',$sid)->where('is_active',true)->exists(),422,'Skill tidak tersedia.');
   $rules=$kind==='mapping'?[]:['level'=>'required|integer|between:1,4'];
   if($kind==='vacancy')$rules+=['importance'=>'required|in:required,preferred','weight'=>'required|integer|between:1,5','reason'=>'nullable|string|max:500'];
   $v=Validator::make($item,$rules);if($v->fails())return back()->withErrors($v)->withInput();$values=[];
   if($kind==='training')$values=['target_level'=>(int)$item['level']];
   if($kind==='vacancy'){
    $default=$item['importance']==='required'?$cfg->required_weight:$cfg->preferred_weight;$override=(int)$item['weight']!==(int)$default;
    if($override&&!filled($item['reason']??null))return back()->withErrors(['reason'=>'Bobot berbeda dari konfigurasi memerlukan alasan untuk setiap skill.'])->withInput();
    $values=['minimum_level'=>(int)$item['level'],'importance'=>$item['importance'],'weight'=>(int)$item['weight'],'is_override'=>$override,'override_reason'=>$override?$item['reason']:null];
   }
   $prepared[(int)$sid]=$values;
  }
  DB::transaction(function()use($prepared,$table,$foreign,$id,$kind,$parent,$r){
   foreach($prepared as $sid=>$values){$key=[$foreign=>$id,'skill_id'=>$sid];if($kind==='mapping')DB::table($table)->insertOrIgnore($key);else DB::table($table)->updateOrInsert($key,[...$values,'created_at'=>now(),'updated_at'=>now()]);}
   DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'catalog.bulk_skills','subject_type'=>$parent,'subject_id'=>$id,'details'=>json_encode(['count'=>count($prepared)]),'created_at'=>now(),'updated_at'=>now()]);
  });
  return back()->with('success',count($prepared).' keterampilan berhasil disimpan bersama.');
 }
}
