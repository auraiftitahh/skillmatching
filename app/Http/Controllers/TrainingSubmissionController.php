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
            'with_vacancy'=>'nullable|boolean',
            'outcomes'=>'required|array|min:1|max:20','outcomes.*'=>'array',
            'outcomes.*.skill_id'=>['nullable','integer','distinct',Rule::exists('skills','id')->where('is_active',true)],
            'outcomes.*.new_name'=>'nullable|string|max:150','outcomes.*.category'=>'nullable|string|max:150',
            'outcomes.*.description'=>'nullable|string|max:1500','outcomes.*.reference'=>'nullable|url:http,https|max:255',
            'outcomes.*.target_level'=>'nullable|integer|between:1,4',
        ], [
            'outcomes.*.skill_id.distinct'=>'Setiap skill dari katalog hanya boleh dipilih sekali. Hapus atau ganti baris yang duplikat di bagian 2.',
        ]);
        $outcomes=[];
        $outcomeIndexes=[];
        foreach($d['outcomes'] as $index=>$row){
            if(empty($row['skill_id']) && empty(trim($row['new_name']??''))) continue;
            if(empty($row['target_level']))throw ValidationException::withMessages(['outcomes'=>'Tentukan level capaian setiap keterampilan.']);
            if(empty($row['skill_id']) && (empty(trim($row['category']??'')) || empty(trim($row['description']??''))))
                throw ValidationException::withMessages(['outcomes'=>'Usulan skill baru memerlukan kategori dan penjelasan kompetensi.']);
            if(!empty($row['skill_id']))$row=array_intersect_key($row,array_flip(['skill_id','target_level']));
            $outcomeIndexes[$index]=count($outcomes);
            $outcomes[]=$row;
        }
        if(!$outcomes)throw ValidationException::withMessages(['outcomes'=>'Tambahkan minimal satu capaian keterampilan.']);
        $payload=array_intersect_key($d,array_flip(['title','description','mode','duration_hours','weekly_hours','cost','source_url']));
        $payload['outcomes']=$outcomes;
        if ($r->boolean('with_vacancy')) {
            $vacancy = $this->vacancyDetails($r, $d['intent'] === 'submit');
            $requirements = $this->vacancyRequirements($r, $outcomes, $outcomeIndexes);
            $payload['vacancy'] = [...$vacancy, 'requirements' => $requirements, 'status' => $d['intent'] === 'submit' ? 'pending' : 'draft'];
        }
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
            if ($row) {
                $previous = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
                if (isset($previous['demo_scenario'])) {
                    $payload['demo_scenario'] = $previous['demo_scenario'];
                }
                foreach ($payload['outcomes'] as &$outcome) {
                    foreach ($previous['outcomes'] as $reviewed) {
                        if (($reviewed['skill_review']['decision'] ?? null) === 'approved'
                            && array_diff_key($outcome, ['target_level'=>true]) == array_diff_key($reviewed, ['target_level'=>true, 'skill_review'=>true, 'resolved_skill_id'=>true])
                            && DB::table('skills')->where('id', $reviewed['resolved_skill_id'])->where('is_active', true)->exists()) {
                            $outcome['skill_review'] = $reviewed['skill_review'];
                            $outcome['resolved_skill_id'] = $reviewed['resolved_skill_id'];
                            break;
                        }
                    }
                }
                unset($outcome);
            }
            $values=['certification_body_id'=>$body,'training_id'=>$trainingId,'payload'=>json_encode($payload),
                'status'=>$d['intent']==='submit'?'pending':'draft','review_note'=>null,'reviewed_by'=>null,'reviewed_at'=>null,
                'submitted_at'=>$d['intent']==='submit'?now():null,'updated_at'=>now()];
            if($row){DB::table('training_submissions')->where('id',$row->id)->update($values);return $row->id;}
            return DB::table('training_submissions')->insertGetId([...$values,'created_at'=>now()]);
        });
        return redirect('/sertifikasi/pengajuan/detail?id='.$id)->with('success',$d['intent']==='submit'?'Pengajuan terkirim. Pelatihan belum berubah sampai Admin menyetujuinya.':'Draft tersimpan.');
    }

    private function vacancyRequirements(Request $request, array $outcomes, array $outcomeIndexes): array
    {
        $selection = $request->validate([
            'vacancy.requirements' => 'required|array|min:1|max:20',
            'vacancy.requirements.*.selected' => 'nullable|boolean',
            'vacancy.requirements.*.minimum_level' => 'nullable|integer|between:1,4',
            'vacancy.requirements.*.importance' => 'nullable|in:required,preferred',
        ]);
        $requirements = [];
        foreach ($selection['vacancy']['requirements'] as $index => $requirement) {
            if (empty($requirement['selected'])) {
                continue;
            }

            $outcomeIndex = $outcomeIndexes[$index] ?? null;
            $level = (int) ($requirement['minimum_level'] ?? 0);
            if ($outcomeIndex === null || $level < 1 || $level > (int) $outcomes[$outcomeIndex]['target_level'] || empty($requirement['importance'])) {
                throw ValidationException::withMessages(['vacancy.requirements' => 'Pilih capaian pelatihan yang valid dan tetapkan level minimum yang tidak melebihi capaian.']);
            }
            $requirements[$outcomeIndex] = ['minimum_level' => $level, 'importance' => $requirement['importance']];
        }
        if (!$requirements) {
            throw ValidationException::withMessages(['vacancy.requirements' => 'Pilih minimal satu capaian sebagai persyaratan lowongan.']);
        }

        return $requirements;
    }

    private function vacancyDetails(Request $request, bool $submitting): array
    {
        $rules = [
            'vacancy.title' => 'required|string|max:190',
            'vacancy.company' => 'required|string|max:190',
            'vacancy.location' => 'required|string|max:150',
            'vacancy.employment_type' => 'required|in:Tetap,Kontrak',
            'vacancy.description' => 'required|string|max:5000',
            'vacancy.source_url' => 'required|url:http,https|max:255',
            'vacancy.expires_at' => $submitting ? 'required|date_format:Y-m-d|after_or_equal:today' : 'required|date_format:Y-m-d',
        ];

        $fields = array_map(fn (string $key) => substr($key, strlen('vacancy.')), array_keys($rules));

        return array_intersect_key($request->validate($rules)['vacancy'], array_flip($fields));
    }

    public function reviewVacancy(Request $request, int $id)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate([
            'decision' => 'required|in:approved,revision,rejected',
            'review_note' => 'required|string|min:5|max:2000',
        ]);

        DB::transaction(function () use ($request, $id, $data): void {
            $submission = DB::table('training_submissions')->where('id', $id)->lockForUpdate()->first();
            abort_unless($submission, 404);
            $payload = json_decode($submission->payload, true, 512, JSON_THROW_ON_ERROR);
            $vacancy = $payload['vacancy'] ?? null;
            abort_unless($vacancy, 404);
            abort_unless($vacancy['status'] === 'pending', 422, 'Lowongan sudah ditinjau atau belum dikirim.');
            abort_unless($submission->status === 'approved', 422, 'Setujui pelatihan dan capaian skill sebelum meninjau lowongan.');

            if ($data['decision'] === 'approved') {
                $training = Publication::trainings()->where('certification_body_id', $submission->certification_body_id)
                    ->whereKey($submission->training_id)->lockForUpdate()->first();
                abort_unless($training, 422, 'Pelatihan terkait harus terbit dan lembaga harus aktif.');
                abort_if($vacancy['expires_at'] < today()->toDateString(), 422, 'Tanggal berlaku lowongan sudah lewat. Minta perbaikan.');
                $requirements = [];
                foreach ($vacancy['requirements'] as $index => $requirement) {
                    $skillId = $payload['outcomes'][$index]['resolved_skill_id'] ?? null;
                    $available = DB::table('training_skills')->join('skills', 'skills.id', '=', 'training_skills.skill_id')
                        ->where('training_id', $training->id)->where('skill_id', $skillId)
                        ->where('skills.is_active', true)->where('target_level', '>=', $requirement['minimum_level'])->exists();
                    abort_unless($available, 422, 'Capaian skill tidak lagi tersedia di pelatihan. Minta pengajuan baru.');
                    $current = $requirements[$skillId] ?? null;
                    $requirements[$skillId] = [
                        'minimum_level' => max($current['minimum_level'] ?? 0, $requirement['minimum_level']),
                        'importance' => ($current['importance'] ?? null) === 'required' ? 'required' : $requirement['importance'],
                    ];
                }
                abort_if(!$requirements, 422, 'Lowongan memerlukan minimal satu persyaratan keterampilan.');

                $config = \App\Models\WeightConfiguration::where('is_active', true)->latest('version')->firstOrFail();
                $jobId = DB::table('job_vacancies')->insertGetId([
                    ...array_intersect_key($vacancy, array_flip(['title', 'company', 'location', 'employment_type', 'description', 'source_url', 'expires_at'])),
                    'source' => $training->provider, 'fetched_at' => today()->toDateString(), 'status' => 'active',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($requirements as $skillId => $requirement) {
                    DB::table('job_required_skills')->insert([
                        ...$requirement, 'skill_id' => $skillId, 'job_vacancy_id' => $jobId,
                        'weight' => $requirement['importance'] === 'required' ? $config->required_weight : $config->preferred_weight,
                        'is_override' => false, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $vacancy['job_vacancy_id'] = $jobId;
            }

            $payload['vacancy'] = [...$vacancy, 'status' => $data['decision'], 'review_note' => $data['review_note']];
            DB::table('training_submissions')->where('id', $id)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id, 'action' => 'vacancy_submission.'.$data['decision'],
                'subject_type' => 'training_submissions', 'subject_id' => $id,
                'details' => json_encode(['note' => $data['review_note'], 'job_vacancy_id' => $vacancy['job_vacancy_id'] ?? null]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);

        return redirect('/admin/pengajuan/detail?id='.$id)->with('success', 'Hasil tinjauan lowongan tersimpan.');
    }

    public function resubmitVacancy(Request $request, int $id)
    {
        $body = $this->body($request);
        $details = $this->vacancyDetails($request, true);
        DB::transaction(function () use ($request, $body, $id, $details): void {
            $submission = DB::table('training_submissions')->where('id', $id)
                ->where('certification_body_id', $body)->lockForUpdate()->first();
            abort_unless($submission, 404);
            $payload = json_decode($submission->payload, true, 512, JSON_THROW_ON_ERROR);
            abort_unless($submission->status === 'approved' && ($payload['vacancy']['status'] ?? null) === 'revision', 422);
            $indexes = array_keys($payload['outcomes']);
            $requirements = $this->vacancyRequirements($request, $payload['outcomes'], array_combine($indexes, $indexes));
            $payload['vacancy'] = [...$payload['vacancy'], ...$details, 'requirements' => $requirements, 'status' => 'pending', 'review_note' => null];
            DB::table('training_submissions')->where('id', $id)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'updated_at' => now()]);
        }, 3);

        return redirect('/sertifikasi/pengajuan/detail?id='.$id)->with('success', 'Lowongan dikirim ulang untuk ditinjau.');
    }

    public function reviewSkill(Request $request, int $id, int $index)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate([
            'skill_decision' => 'required|in:approved,rejected',
            'skill_note' => 'required|string|min:5|max:2000',
            'mapping' => ['nullable', 'integer', Rule::exists('skills', 'id')->where('is_active', true)],
        ]);

        $returned = DB::transaction(function () use ($request, $id, $index, $data): bool {
            $submission = DB::table('training_submissions')->where('id', $id)->lockForUpdate()->first();
            abort_unless($submission, 404);
            abort_unless($submission->status === 'pending', 422, 'Pengajuan belum siap ditinjau.');
            $payload = json_decode($submission->payload, true, 512, JSON_THROW_ON_ERROR);
            $outcome = $payload['outcomes'][$index] ?? null;
            abort_unless($outcome && empty($outcome['skill_id']), 404);
            abort_if(isset($outcome['skill_review']), 422, 'Usulan skill ini sudah ditinjau.');

            $decision = $data['skill_decision'];
            if ($decision === 'approved') {
                abort_unless(DB::query()->fromSub(Publication::bodyIds(), 'eligible')->where('id', $submission->certification_body_id)->exists(), 422, 'Lembaga harus aktif dan emailnya terverifikasi.');
                $skillId = $data['mapping'] ?? app(\App\Services\CatalogSkillService::class)->resolveName($outcome['new_name'], $outcome['category']);
                abort_unless(DB::table('skills')->where('id', $skillId)->where('is_active', true)->exists(), 422, 'Skill hasil pemetaan harus aktif.');
                $outcome['resolved_skill_id'] = (int) $skillId;
            }
            $outcome['skill_review'] = [
                'decision' => $decision, 'note' => $data['skill_note'],
                'reviewed_by' => $request->user()->id, 'reviewed_at' => now()->toDateTimeString(),
            ];
            $payload['outcomes'][$index] = $outcome;

            $proposals = collect($payload['outcomes'])->whereNull('skill_id');
            $returned = $proposals->every(fn (array $proposal) => isset($proposal['skill_review']))
                && $proposals->contains(fn (array $proposal) => $proposal['skill_review']['decision'] === 'rejected');
            DB::table('training_submissions')->where('id', $id)->update([
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'status' => $returned ? 'revision' : 'pending',
                'updated_at' => now(),
            ]);
            DB::table('audit_logs')->insert([
                'user_id' => $request->user()->id, 'action' => 'skill_submission.'.$decision,
                'subject_type' => 'training_submissions', 'subject_id' => $id,
                'details' => json_encode(['skill' => $outcome['new_name'], 'note' => $data['skill_note']], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $returned;
        }, 3);

        return redirect('/admin/pengajuan/detail?id='.$id)->with('success', $returned
            ? 'Tinjauan skill selesai. Pengajuan dikembalikan ke lembaga untuk perbaikan skill.'
            : 'Hasil tinjauan skill tersimpan.');
    }

    public function review(Request $r,int $id)
    {
        abort_unless($r->user()->role==='admin',403);
        $d=$r->validate(['decision'=>'required|in:approved,revision,rejected','review_note'=>'required|string|min:5|max:2000']);
        DB::transaction(function()use($r,$id,$d){
            $s=DB::table('training_submissions')->where('id',$id)->lockForUpdate()->first();abort_unless($s,404);
            abort_unless($s->status==='pending',422,'Pengajuan sudah ditinjau atau belum dikirim.');
            $payload=json_decode($s->payload,true,512,JSON_THROW_ON_ERROR);
            $skillsReady = collect($payload['outcomes'])->every(fn (array $outcome) => !empty($outcome['skill_id'])
                || (($outcome['skill_review']['decision'] ?? null) === 'approved' && !empty($outcome['resolved_skill_id'])));
            abort_unless($skillsReady,422,'Selesaikan tinjauan setiap usulan skill sebelum meninjau pelatihan.');
            $trainingId=$s->training_id;
            if($d['decision']==='approved'){
                abort_unless(DB::query()->fromSub(Publication::bodyIds(),'eligible')->where('id',$s->certification_body_id)->exists(),422,'Lembaga harus aktif dan emailnya terverifikasi.');
                $resolved=[];
                foreach($payload['outcomes'] as $index=>&$outcome){
                    $skillId=$outcome['skill_id']??$outcome['resolved_skill_id'];
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
