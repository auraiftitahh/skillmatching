<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\{Rule, ValidationException};
use App\Support\Publication;

class TrainingSubmissionController
{
    private function body(Request $r): int
    {
        abort_unless($r->user()->role==='sertifikasi',403);
        $id=$r->user()->certificationBody?->id;
        abort_unless($id && DB::query()->fromSub(Publication::bodyIds(),'eligible')->where('id',$id)->exists(),403);
        return (int)$id;
    }

    public function save(Request $r)
    {
        $body=$this->body($r);
        $d=$r->validate([
            'id'=>'nullable|integer|min:1','training_id'=>'nullable|integer|min:1',
            'title'=>'required|string|max:190','description'=>'required|string|max:5000',
            'mode'=>'required|in:online,offline,hybrid','duration_hours'=>'required|integer|min:1|max:10000',
            'weekly_hours'=>'required|integer|min:1|max:168','cost'=>'required|numeric|min:0|max:999999999',
            'source_url'=>'required|url:http,https|max:255','intent'=>'required|in:draft,submit',
            'outcomes'=>'required|array|min:1|max:20','outcomes.*'=>'array',
            'outcomes.*.skill_id'=>['nullable','integer',Rule::exists('skills','id')->where('is_active',true)],
            'outcomes.*.new_name'=>'nullable|string|max:150','outcomes.*.category'=>'nullable|string|max:150',
            'outcomes.*.description'=>'nullable|string|max:1500','outcomes.*.reference'=>'nullable|url:http,https|max:255',
            'outcomes.*.target_level'=>'nullable|integer|between:1,4',
        ]);
        $outcomes=[];
        foreach($d['outcomes'] as $row){
            if(empty($row['skill_id']) && empty(trim($row['new_name']??''))) continue;
            if(empty($row['target_level']))throw ValidationException::withMessages(['outcomes'=>'Tentukan level capaian setiap keterampilan.']);
            if(empty($row['skill_id']) && (empty(trim($row['category']??'')) || empty(trim($row['description']??''))))
                throw ValidationException::withMessages(['outcomes'=>'Usulan skill baru memerlukan kategori dan penjelasan kompetensi.']);
            if(!empty($row['skill_id']))$row=array_intersect_key($row,array_flip(['skill_id','target_level']));
            $outcomes[]=$row;
        }
        if(!$outcomes)throw ValidationException::withMessages(['outcomes'=>'Tambahkan minimal satu capaian keterampilan.']);
        $payload=array_intersect_key($d,array_flip(['title','description','mode','duration_hours','weekly_hours','cost','source_url']));
        $payload['outcomes']=$outcomes;
        $id=DB::transaction(function()use($r,$d,$body,$payload){
            // Lock the owner to serialize concurrent edits and submissions from this institution.
            DB::table('certification_bodies')->where('id',$body)->lockForUpdate()->first();
            $row=!empty($d['id'])?DB::table('training_submissions')->where('id',$d['id'])->where('certification_body_id',$body)->lockForUpdate()->first():null;
            if(!empty($d['id']))abort_unless($row,404);
            if($row)abort_unless(in_array($row->status,['draft','revision','rejected']),422,'Pengajuan sudah dikirim. Tunggu hasil tinjauan.');
            $trainingId=$row?->training_id??($d['training_id']??null);
            if($trainingId){
                $training=DB::table('trainings')->where('id',$trainingId)->where('certification_body_id',$body)->first();
                abort_unless($training,404);
                $pending=DB::table('training_submissions')->where('training_id',$trainingId)->whereIn('status',['draft','pending','revision']);
                if($row)$pending->where('id','!=',$row->id);
                abort_if($pending->exists(),422,'Pelatihan ini sudah memiliki pengajuan yang belum selesai. Buka pengajuan tersebut.');
            }
            $values=['certification_body_id'=>$body,'training_id'=>$trainingId,'payload'=>json_encode($payload),
                'status'=>$d['intent']==='submit'?'pending':'draft','review_note'=>null,'reviewed_by'=>null,'reviewed_at'=>null,
                'submitted_at'=>$d['intent']==='submit'?now():null,'updated_at'=>now()];
            if($row){DB::table('training_submissions')->where('id',$row->id)->update($values);return $row->id;}
            return DB::table('training_submissions')->insertGetId([...$values,'created_at'=>now()]);
        });
        return redirect('/sertifikasi/pengajuan/detail?id='.$id)->with('success',$d['intent']==='submit'?'Pengajuan terkirim. Pelatihan belum berubah sampai Admin menyetujuinya.':'Draft tersimpan.');
    }

    public function review(Request $r,int $id)
    {
        abort_unless($r->user()->role==='admin',403);
        $d=$r->validate(['decision'=>'required|in:approved,revision,rejected','review_note'=>'required|string|min:5|max:2000',
            'mapping'=>'nullable|array','mapping.*'=>['nullable','integer',Rule::exists('skills','id')->where('is_active',true)]]);
        DB::transaction(function()use($r,$id,$d){
            $s=DB::table('training_submissions')->where('id',$id)->lockForUpdate()->first();abort_unless($s,404);
            abort_unless($s->status==='pending',422,'Pengajuan sudah ditinjau atau belum dikirim.');
            $payload=json_decode($s->payload,true,512,JSON_THROW_ON_ERROR);
            $trainingId=$s->training_id;
            if($d['decision']==='approved'){
                abort_unless(DB::query()->fromSub(Publication::bodyIds(),'eligible')->where('id',$s->certification_body_id)->exists(),422,'Lembaga harus aktif dan emailnya terverifikasi.');
                $resolved=[];
                foreach($payload['outcomes'] as $index=>&$outcome){
                    $skillId=$outcome['skill_id']??($d['mapping'][$index]??null);
                    if(!$skillId){
                        $name=trim(preg_replace('/\s+/u',' ',$outcome['new_name']));$normalized=mb_strtolower($name);
                        $registry=DB::table('skill_name_registry')->where('normalized_name',$normalized)->lockForUpdate()->first();
                        if($registry)$skillId=$registry->skill_id;
                        else {
                            $existing=DB::table('skills')->whereRaw('LOWER(name) = ?',[$normalized])->first();
                            $skillId=$existing?->id??DB::table('skills')->insertGetId(['name'=>$name,'category'=>$outcome['category'],'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
                            DB::table('skill_name_registry')->insert(['normalized_name'=>$normalized,'skill_id'=>$skillId]);
                        }
                    }
                    abort_unless(DB::table('skills')->where('id',$skillId)->where('is_active',true)->exists(),422,'Skill hasil pemetaan harus aktif.');
                    $resolved[$skillId]=max($resolved[$skillId]??0,(int)$outcome['target_level']);
                    $outcome['resolved_skill_id']=(int)$skillId;
                }
                unset($outcome);
                $body=DB::table('certification_bodies')->find($s->certification_body_id);
                $trainingData=array_intersect_key($payload,array_flip(['title','description','mode','duration_hours','weekly_hours','cost','source_url']));
                $trainingData=[...$trainingData,'provider'=>$body->name,'source'=>$body->name,'fetched_at'=>now()->toDateString(),'status'=>'active','certification_body_id'=>$body->id,'updated_at'=>now()];
                if($trainingId){
                    $training=DB::table('trainings')->where('id',$trainingId)->where('certification_body_id',$body->id)->lockForUpdate()->first();abort_unless($training,404);
                    // Existing participants retain the exact outcomes they enrolled with (captured by enrollment service).
                    \App\Services\SkillEvidenceService::snapshotExistingEnrollments($trainingId);
                    DB::table('trainings')->where('id',$trainingId)->update($trainingData);
                    DB::table('training_skills')->where('training_id',$trainingId)->delete();
                }else $trainingId=DB::table('trainings')->insertGetId([...$trainingData,'created_at'=>now()]);
                foreach($resolved as $skill=>$level)DB::table('training_skills')->insert(['training_id'=>$trainingId,'skill_id'=>$skill,'target_level'=>$level,'created_at'=>now(),'updated_at'=>now()]);
            }
            DB::table('training_submissions')->where('id',$id)->update(['training_id'=>$trainingId,'payload'=>json_encode($payload),'status'=>$d['decision'],'review_note'=>$d['review_note'],'reviewed_by'=>$r->user()->id,'reviewed_at'=>now(),'updated_at'=>now()]);
            DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'training_submission.'.$d['decision'],'subject_type'=>'training_submissions','subject_id'=>$id,'details'=>json_encode(['note'=>$d['review_note']]),'created_at'=>now(),'updated_at'=>now()]);
        },3);
        return redirect('/admin/pengajuan/detail?id='.$id)->with('success','Hasil tinjauan tersimpan.');
    }
}
