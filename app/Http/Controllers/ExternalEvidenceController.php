<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\Rule;
use App\Support\Publication;
use App\Services\SkillEvidenceService;

class ExternalEvidenceController
{
    private function owner(Request $r): void { abort_unless($r->user()->role==='pelamar',403); }

    private function audit(Request $r,string $action,int $id,array $details=[]): void
    {
        DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'external_certificate.'.$action,'subject_type'=>'external_certificates','subject_id'=>$id,'details'=>json_encode($details),'created_at'=>now(),'updated_at'=>now()]);
    }

    private function data(Request $r,bool $fileRequired): array
    {
        return $r->validate(['title'=>'required|string|max:190',
            'certification_body_id'=>'nullable|integer|exists:certification_bodies,id',
            'issuer_name'=>[Rule::excludeIf($r->filled('certification_body_id')),'required_without:certification_body_id','nullable','string','min:2','max:190'],
            'skill_id'=>['required','integer',Rule::exists('skills','id')->where('is_active',true)],
            'level'=>'required|integer|between:1,4','file'=>[$fileRequired?'required':'nullable','file','mimes:pdf,jpg,jpeg,png','max:5120']]);
    }

    private function issuer(array $data): ?object
    {
        if(empty($data['certification_body_id']))return null;
        return DB::table('certification_bodies')->whereIn('id',Publication::bodyIds())->where('id',$data['certification_body_id'])->first()??abort(422,'Penerbit belum aktif atau belum disetujui.');
    }

    public function store(Request $r)
    {
        $this->owner($r);
        $r->validate(['source_certificate_id'=>'nullable|integer|min:1']);
        $source=$r->integer('source_certificate_id')?DB::table('external_certificates')->where('id',$r->integer('source_certificate_id'))->where('user_id',$r->user()->id)->where('status','rejected')->first():null;
        if($r->integer('source_certificate_id'))abort_unless($source,404);
        $d=$this->data($r,!$source);$body=$this->issuer($d);
        $newPath=$r->hasFile('file')?$r->file('file')->store('external-certificates','local'):null;
        $path=$newPath??$source?->file_path;abort_unless($path && Storage::disk('local')->exists($path),422,'Dokumen lama tidak tersedia. Unggah dokumen baru.');
        try{
            $id=DB::transaction(function()use($r,$d,$body,$path,$source){
                $id=DB::table('external_certificates')->insertGetId(['user_id'=>$r->user()->id,'certification_body_id'=>$body?->id,
                    'issuer_name'=>$body?->name??trim($d['issuer_name']),'title'=>$d['title'],'skill_id'=>$d['skill_id'],'level'=>$d['level'],
                    'file_path'=>$path,'status'=>$body?'pending':'unverified','created_at'=>now(),'updated_at'=>now()]);
                $this->audit($r,'submit',$id,['source_certificate_id'=>$source?->id]);return $id;
            });
        }catch(\Throwable $e){if($newPath)Storage::disk('local')->delete($newPath);throw $e;}
        return redirect('/pelamar/bukti/detail?id='.$id)->with('success',$body?'Bukti dikirim kepada penerbit yang dipilih.':'Bukti tersimpan belum terverifikasi. Unggahan tidak mengubah level kemampuan.');
    }

    public function update(Request $r,int $id)
    {
        $this->owner($r);$d=$this->data($r,false);$body=$this->issuer($d);
        $newPath=$r->hasFile('file')?$r->file('file')->store('external-certificates','local'):null;
        try{
            DB::transaction(function()use($r,$id,$d,$body,$newPath){
                $e=DB::table('external_certificates')->where('id',$id)->where('user_id',$r->user()->id)->lockForUpdate()->first()??abort(404);
                abort_unless(in_array($e->status,['unverified','cancelled']),422,'Bukti yang sedang diperiksa atau sudah diputuskan tidak dapat diedit.');
                $path=$newPath??$e->file_path;abort_unless(Storage::disk('local')->exists($path),422,'Dokumen tidak tersedia. Unggah ulang dokumen.');
                DB::table('external_certificates')->where('id',$id)->update(['title'=>$d['title'],'issuer_name'=>$body?->name??trim($d['issuer_name']),
                    'certification_body_id'=>$body?->id,'skill_id'=>$d['skill_id'],'level'=>$d['level'],'file_path'=>$path,
                    'status'=>$body?'pending':'unverified','reason'=>null,'processed_at'=>null,'processed_by'=>null,'updated_at'=>now()]);
                $this->audit($r,'edit',$id,['status'=>$body?'Menunggu pemeriksaan':'Belum terverifikasi']);
            });
        }catch(\Throwable $e){if($newPath)Storage::disk('local')->delete($newPath);throw $e;}
        return redirect('/pelamar/bukti/detail?id='.$id)->with('success',$body?'Perubahan tersimpan dan bukti dikirim kepada penerbit.':'Perubahan bukti tersimpan.');
    }

    public function linkIssuer(Request $r,int $id)
    {
        $this->owner($r);
        $d=$r->validate(['certification_body_id'=>'required|integer|exists:certification_bodies,id','confirm_issuer'=>'accepted']);$body=$this->issuer($d);
        DB::transaction(function()use($r,$id,$body){
            $e=DB::table('external_certificates')->where('id',$id)->where('user_id',$r->user()->id)->lockForUpdate()->first()??abort(404);
            abort_unless(in_array($e->status,['unverified','cancelled']),422,'Status bukti tidak dapat dihubungkan ulang.');
            abort_unless(Storage::disk('local')->exists($e->file_path),422,'Dokumen tidak tersedia. Edit bukti dan unggah ulang terlebih dahulu.');
            DB::table('external_certificates')->where('id',$id)->update(['certification_body_id'=>$body->id,'issuer_name'=>$body->name,'status'=>'pending','reason'=>null,'updated_at'=>now()]);
            $this->audit($r,'link_issuer',$id,['previous_issuer'=>$e->issuer_name,'issuer'=>$body->name]);
        });
        return redirect('/pelamar/bukti/detail?id='.$id)->with('success','Bukti dikirim kepada penerbit. Statusnya menunggu pemeriksaan, belum terverifikasi.');
    }

    public function withdraw(Request $r,int $id)
    {
        $this->owner($r);
        DB::transaction(function()use($r,$id){
            $e=DB::table('external_certificates')->where('id',$id)->where('user_id',$r->user()->id)->lockForUpdate()->first()??abort(404);
            abort_unless($e->status==='pending',422,'Pengajuan sudah diproses atau tidak sedang menunggu.');
            DB::table('external_certificates')->where('id',$id)->update(['status'=>'cancelled','certification_body_id'=>null,'updated_at'=>now()]);
            $this->audit($r,'withdraw',$id,['issuer'=>$e->issuer_name]);
        });
        return redirect('/pelamar/bukti/detail?id='.$id)->with('success','Pengajuan ditarik. Dokumen tetap tersimpan secara pribadi dan dapat diperbaiki.');
    }

    private function evidence(Request $r,int $id)
    {
        $q=DB::table('external_certificates')->where('id',$id);
        if($r->user()->role==='pelamar')$q->where('user_id',$r->user()->id);
        elseif($r->user()->role==='sertifikasi'){
            $body=$r->user()->certificationBody;abort_unless($body && $body->status==='active',403);
            $q->where('certification_body_id',$body->id);
        }else abort(403);
        return $q->firstOrFail();
    }

    public function file(Request $r,int $id)
    {
        $e=$this->evidence($r,$id);abort_unless(Storage::disk('local')->exists($e->file_path),404);
        return Storage::disk('local')->download($e->file_path,'bukti-'.$e->id.'.'.pathinfo($e->file_path,PATHINFO_EXTENSION),['X-Content-Type-Options'=>'nosniff']);
    }

    public function decision(Request $r,int $id)
    {
        abort_unless($r->user()->role==='sertifikasi',403);$e=$this->evidence($r,$id);
        $d=$r->validate(['decision'=>'required|in:verified,rejected','reason'=>'required|string|min:5|max:2000']);
        abort_unless(DB::table('users')->where('id',$e->user_id)->where('is_active',true)->whereNotNull('email_verified_at')->exists(),403);
        DB::transaction(function()use($r,$e,$d){
            $locked=DB::table('external_certificates')->where('id',$e->id)->lockForUpdate()->first();
            abort_unless($locked->status==='pending' && $locked->certification_body_id===$r->user()->certificationBody->id,422,'Bukti sudah diproses atau telah ditarik oleh pemiliknya.');
            DB::table('external_certificates')->where('id',$e->id)->update(['status'=>$d['decision'],'reason'=>$d['reason'],'processed_by'=>$r->user()->id,'processed_at'=>now(),'updated_at'=>now()]);
            if($d['decision']==='verified')SkillEvidenceService::record($e->user_id,$e->skill_id,$e->level,'external_certificate',$e->id,$r->user()->id);
            $this->audit($r,$d['decision'],$e->id,['reason'=>$d['reason']]);
        },3);
        return redirect('/sertifikasi/bukti')->with('success','Keputusan bukti tersimpan.');
    }
}
