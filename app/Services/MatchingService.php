<?php
namespace App\Services;
use App\Models\{User,JobVacancy,WeightConfiguration};
use Illuminate\Support\Facades\DB;
class MatchingService {
 public function config(): WeightConfiguration {return WeightConfiguration::where('is_active',true)->latest('version')->firstOrFail();}
 public function levels(User $user):array {return DB::table('user_skills')->where('user_id',$user->id)->pluck('level','skill_id')->map(fn($v)=>(int)$v)->all();}
 /** Return a computed, explainable, non-mutating result. */
 public function score(JobVacancy $job,array $levels,?WeightConfiguration $config=null):array {
  $config ??= $this->config();
  $rows=DB::table('job_required_skills as r')->join('skills as s','s.id','=','r.skill_id')
   ->where('r.job_vacancy_id',$job->id)->select('r.*','s.name as skill_name')->get();
  $requirements=[];$related=[];
  foreach($rows as $r){
   $requirements[]=['skill_id'=>(int)$r->skill_id,'skill'=>$r->skill_name,'target'=>(int)$r->minimum_level,
    'importance'=>$r->importance,'weight'=>(int)$r->weight,'is_override'=>(bool)$r->is_override];
   {
    $ids=DB::table('skill_relations')->where('skill_id',$r->skill_id)->pluck('related_skill_id');
    $related[$r->skill_id]=[];
    foreach($ids as $id){if(isset($levels[$id]))$related[$r->skill_id][]=(int)$levels[$id];}
   }
  }
  $result=MatchingCalculator::calculate($requirements,$levels,$related,[
   'required_weight'=>(int)$config->required_weight,'preferred_weight'=>(int)$config->preferred_weight,
   'related_factor'=>(float)$config->related_factor]);
  return ['job_id'=>$job->id,'job'=>$job->title,'company'=>$job->company,
   'score'=>$result['score'],'display_score'=>$result['display_score'],'items'=>$result['items'],
   'denominator'=>$result['denominator'],'configuration_version'=>$config->version,'method'=>'max-direct-related-v2'];
 }
 public function rank(User $user,bool $includeBelow=false):array {
  $levels=$this->levels($user);$cfg=$this->config();
  $rank=\App\Support\Publication::jobs()->get()->map(fn($job)=>$this->score($job,$levels,$cfg))->sortByDesc('score')->values()->all();
  return $includeBelow?$rank:array_values(array_filter($rank,fn($r)=>$r['score'] >= $cfg->threshold));
 }
 public function gap(array $jobIds,array $levels):array {
  $aggregated=[];
  foreach(\App\Support\Publication::jobs()->whereIn('id',$jobIds)->get() as $job){
   foreach($this->score($job,$levels)['items'] as $item){ if(!$item['gap'])continue; $id=$item['skill_id'];
    if(!isset($aggregated[$id])) $aggregated[$id]=['skill_id'=>$id,'skill'=>$item['skill'],'own'=>$item['own'],'target'=>$item['target'],'gap'=>$item['gap'],'priority'=>0,'jobs'=>[]];
    $aggregated[$id]['target']=max($aggregated[$id]['target'],$item['target']);
    $aggregated[$id]['gap']=max($aggregated[$id]['gap'],$item['gap']);
    $aggregated[$id]['priority']+=$item['priority'];$aggregated[$id]['jobs'][]=$job->title;
   }
  } usort($aggregated,fn($a,$b)=>$b['priority']<=>$a['priority']);return array_values($aggregated);
 }
 public function trainingOptions(array $gaps,array $levels,?float $budget=null,?int $weeklyHours=null,?string $mode=null):array {
  $total=max(1,array_sum(array_column($gaps,'priority')));$options=[];
  foreach(\App\Support\Publication::trainings()->get() as $t){
   if($budget!==null&&(float)$t->cost>$budget)continue;
   if($weeklyHours!==null&&(int)$t->weekly_hours>$weeklyHours)continue;
   if($mode && $mode!=='all' && $t->mode!==$mode)continue;
   $teaches=DB::table('training_skills')->where('training_id',$t->id)->pluck('target_level','skill_id')->all();
   $gain=0;$coverageItems=[]; foreach($gaps as $g){$improve=max(0,min($g['target'],(int)($teaches[$g['skill_id']]??0))-(int)($levels[$g['skill_id']]??0));
    if($g['gap']>0){$part=$g['priority']*$improve/$g['gap'];$gain+=$part;$coverageItems[]=[...$g,'improvement'=>$improve,'weighted_gain'=>$part];} }
   if($gain<=0)continue;
   $options[]=['id'=>$t->id,'title'=>$t->title,'provider'=>$t->provider,'coverage_items'=>$coverageItems,'coverage_total'=>$total,'coverage_gain'=>$gain,'cost'=>(float)$t->cost,'duration_hours'=>$t->duration_hours,'mode'=>$t->mode,'coverage'=>round(100*$gain/$total,2),'prerequisites'=>DB::table('training_prerequisites')->where('training_id',$t->id)->pluck('prerequisite_id')->all()];
  }
  usort($options,fn($a,$b)=>($b['coverage']<=>$a['coverage'])?:($a['cost']<=>$b['cost'])?:($a['duration_hours']<=>$b['duration_hours']));return $options;
 }
 public function learningPath(array $options,array $completedIds=[]):array {
  $selected=[];$available=$options;$done=array_map('intval',$completedIds);
  // Evaluate prerequisites again after each selection. Never place a dependent course before its prerequisite.
  while(count($selected)<3 && $available){
   $candidate=null;$key=null;
   foreach($available as $i=>$option){
    $required=array_map('intval',$option['prerequisites']);
    if(count(array_diff($required,$done))===0){$candidate=$option;$key=$i;break;}
   }
   if($candidate===null)break;
   $selected[]=$candidate;$done[]=(int)$candidate['id'];unset($available[$key]);
  }
  return $selected;
 }
 public function simulate(User $user,JobVacancy $job,array $trainingIds):array {
  abort_unless(\App\Support\Publication::jobs()->whereKey($job->id)->exists(),404);
  abort_unless(count($trainingIds)>0 && \App\Support\Publication::trainings()->whereIn('id',$trainingIds)->count()===count(array_unique($trainingIds)),404,'Pelatihan tidak tersedia untuk simulasi.');
  $actual=$this->levels($user);$hypothetical=$actual;$config=$this->config();
  $skills=DB::table('training_skills')->whereIn('training_id',$trainingIds)->get();
  foreach($skills as $skill){$hypothetical[$skill->skill_id]=max($actual[$skill->skill_id]??0,$hypothetical[$skill->skill_id]??0,(int)$skill->target_level);}
  $before=$this->score($job,$actual,$config);$after=$this->score($job,$hypothetical,$config);$changes=[];
  $names=DB::table('skills')->whereIn('id',array_keys($hypothetical))->pluck('name','id');
  foreach($hypothetical as $id=>$level){if($level>($actual[$id]??0))$changes[]=['skill'=>$names[$id]??'Skill','before'=>$actual[$id]??0,'after'=>$level];}
  return ['before'=>$before,'after'=>$after,'changes'=>$changes,'training_ids'=>$trainingIds,'delta'=>round($after['score']-$before['score'],2),'simulation'=>true,'profile_changed'=>false];
 }
 public function snapshot(User $user):int {
  $config=$this->config();$results=$this->rank($user,true);return DB::table('recommendation_histories')->insertGetId(['user_id'=>$user->id,'weight_configuration_id'=>$config->id,'profile_snapshot'=>json_encode($this->levels($user)),'results'=>json_encode($results),'created_at'=>now(),'updated_at'=>now()]);
 }
}
