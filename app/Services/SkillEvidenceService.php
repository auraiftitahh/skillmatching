<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

final class SkillEvidenceService
{
    public static function snapshotExistingEnrollments(int $trainingId): void
    {
        $outcomes=DB::table('training_skills')->where('training_id',$trainingId)->get(['skill_id','target_level'])->toJson();
        DB::table('training_enrollments')->where('training_id',$trainingId)->whereNull('outcomes_snapshot')->update(['outcomes_snapshot'=>$outcomes]);
    }

    public static function record(int $userId,int $skillId,int $level,string $type,int $sourceId,int $actor): void
    {
        DB::table('skill_evidence')->updateOrInsert(['source_type'=>$type,'source_id'=>$sourceId,'skill_id'=>$skillId],
            ['user_id'=>$userId,'level'=>$level,'confirmed_by'=>$actor,'created_at'=>now(),'updated_at'=>now()]);
        $existing=DB::table('user_skills')->where('user_id',$userId)->where('skill_id',$skillId)->lockForUpdate()->first();
        $result=max((int)($existing->level??0),$level);
        // A level-2 proof does not verify a self-reported level-4 claim.
        $supported=(int)DB::table('skill_evidence')->where('user_id',$userId)->where('skill_id',$skillId)->max('level');
        $status=$supported>=$result?'verified':($existing->verification_status??'self_reported');
        DB::table('user_skills')->updateOrInsert(['user_id'=>$userId,'skill_id'=>$skillId],['level'=>$result,'verification_status'=>$status,'created_at'=>$existing->created_at??now(),'updated_at'=>now()]);
    }
}
