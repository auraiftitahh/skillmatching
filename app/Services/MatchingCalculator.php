<?php
namespace App\Services;
/** Pure, independently testable domain calculation without database or session access. */
final class MatchingCalculator {
 /**
  * @param array<int,array{skill_id:int,skill:string,target:int,importance:string,weight:int,is_override:bool}> $requirements
  * @param array<int,int> $levels Skill ID => level (0-4)
  * @param array<int,list<int>> $related Related skill levels keyed by required skill ID
  * @param array{required_weight:int,preferred_weight:int,related_factor:float} $config
  */
 public static function calculate(array $requirements,array $levels,array $related,array $config):array {
  $sum=0.;$total=0.;$items=[];
  foreach($requirements as $r){
   $id=(int)$r['skill_id'];$own=max(0,min(4,(int)($levels[$id]??0)));
   $target=max(1,min(4,(int)$r['target']));$relatedUsed=false;
   $match=$own>0?min(1.,$own/$target):0.;
   $directMatch=$match;
   if(!empty($related[$id])){
    $best=max(array_map(fn($level)=>max(0,min(4,(int)$level))/$target,$related[$id]));
    $relatedMatch=min(1.,$best)*max(0.,min(1.,(float)$config['related_factor']));
    $relatedUsed=$relatedMatch>$directMatch;
    $match=max($directMatch,$relatedMatch);
   }
   $weight=($r['is_override']??false)?(int)$r['weight']:(($r['importance']??'required')==='required'?(int)$config['required_weight']:(int)$config['preferred_weight']);
   $weight=max(1,min(5,$weight));$sum+=$weight*$match;$total+=$weight;
   $gap=max(0,$target-$own);
   $items[]=['skill_id'=>$id,'skill'=>$r['skill'],'own'=>$own,'target'=>$target,'weight'=>$weight,
    'is_override'=>(bool)($r['is_override']??false),'importance'=>$r['importance'],'match'=>round($match,4),'related'=>$relatedUsed,
    'gap'=>$gap,'priority'=>$gap*$weight,'contribution'=>round($weight*$match,4)];
  }
  $score=$total>0?round(100*$sum/$total,2):0.;
  return ['score'=>$score,'display_score'=>(int)round($score),'denominator'=>$total,'items'=>$items];
 }
}
