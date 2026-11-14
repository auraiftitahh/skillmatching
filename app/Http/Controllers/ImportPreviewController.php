<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Validator,Cache};
use Illuminate\Support\Str;

class ImportPreviewController
{
    private function authorize(Request $r,string $type): void {
        abort_unless($r->user()?->role==='admin',403);
        abort_unless(in_array($type,['job_vacancies','trainings'],true),404);
    }
    private function destination(string $type): string {return '/admin/'.($type==='job_vacancies'?'lowongan':'pelatihan').'/impor';}
    private function rules(string $type): array {
        return $type==='job_vacancies'
            ? ['title'=>'required|string|max:190','company'=>'required|string|max:190','source'=>'required|string|max:190','source_url'=>'nullable|url|max:255','fetched_at'=>'required|date_format:Y-m-d','expires_at'=>'nullable|date_format:Y-m-d|after_or_equal:fetched_at']
            : ['title'=>'required|string|max:190','provider'=>'required|string|max:190','cost'=>'required|numeric|min:0','duration_hours'=>'required|integer|min:1|max:10000','weekly_hours'=>'nullable|integer|min:1|max:168','mode'=>'required|in:online,offline,hybrid','fetched_at'=>'required|date_format:Y-m-d','source_url'=>'nullable|url|max:255'];
    }
    private function duplicate(string $type,array $row): bool {
        $key=$type==='job_vacancies'?'company':'provider';
        return DB::table($type)->where('title',$row['title'])->where($key,$row[$key])->exists();
    }
    public function preview(Request $r,string $type) {
        abort_if($type==='trainings',403,'Pelatihan berasal dari pengajuan lembaga.');
        $this->authorize($r,$type);$r->session()->forget('import_preview.'.$type);$r->validate(['file'=>'required|file|mimes:csv,txt|max:3072']);
        $h=fopen($r->file('file')->getRealPath(),'r');
        $header=array_map(fn($v)=>strtolower(trim(ltrim($v,"\xEF\xBB\xBF"))),fgetcsv($h)?:[]);
        if(count($header)!==count(array_unique($header))){fclose($h);return back()->withErrors(['file'=>'Nama kolom tidak boleh duplikat.']);}
        $required=$type==='job_vacancies'?['title','company','source','fetched_at']:['title','provider','cost','duration_hours','mode','fetched_at'];
        if($missing=array_diff($required,$header)){fclose($h);return back()->withErrors(['file'=>'Kolom wajib tidak ada: '.implode(', ',$missing)]);}
        $rows=[];$seen=[];$line=1;
        while(($values=fgetcsv($h))!==false){
            $line++;if($line>1001){fclose($h);return back()->withErrors(['file'=>'Maksimal 1.000 baris data. Tidak ada data disimpan.']);}
            if($values===[null])continue;
            $row=count($values)===count($header)?array_combine($header,array_map('trim',$values)):[];
            $row=array_intersect_key($row,$this->rules($type));foreach($row as $k=>$v)if($v==='')$row[$k]=null;
            $v=Validator::make($row,$this->rules($type));$errors=$v->errors()->all();
            if(!$errors){$identity=mb_strtolower($row['title'].'|'.$row[$type==='job_vacancies'?'company':'provider']);
                if(isset($seen[$identity])||$this->duplicate($type,$row))$errors[]='Duplikat judul dan perusahaan/penyedia; data lama tidak ditimpa.';
                $seen[$identity]=true;
            }
            $rows[]=['line'=>$line,'data'=>$row,'errors'=>$errors];
        }
        fclose($h);if(!$rows)return back()->withErrors(['file'=>'CSV tidak memiliki baris data.']);
        $r->session()->put('import_preview.'.$type,['token'=>(string)Str::uuid(),'user_id'=>$r->user()->id,'expires'=>now()->addMinutes(30)->timestamp,'rows'=>$rows]);
        return redirect($this->destination($type))->with('success','Pratinjau siap. Belum ada data katalog yang disimpan.');
    }
    public function cancel(Request $r,string $type) {
        $this->authorize($r,$type);$r->session()->forget('import_preview.'.$type);
        return redirect($this->destination($type))->with('success','Pratinjau dibatalkan. Katalog tidak berubah.');
    }
    public function commit(Request $r,string $type) {
        abort_if($type==='trainings',403,'Pelatihan berasal dari pengajuan lembaga.');
        $this->authorize($r,$type);$r->validate(['token'=>'required|uuid']);$preview=$r->session()->get('import_preview.'.$type);
        abort_unless($preview && $preview['user_id']===$r->user()->id && $preview['expires']>=now()->timestamp && hash_equals($preview['token'],$r->token),422,'Pratinjau kedaluwarsa atau telah diproses. Unggah ulang file.');
        $lock=Cache::lock('import-commit:'.$type,60);abort_unless($lock->get(),409,'Impor katalog ini sedang diproses. Coba kembali.');
        try {
            abort_if(Cache::has('import-done:'.$preview['token']),422,'Pratinjau telah diproses.');
            $result=DB::transaction(function()use($preview,$type,$r){
                $ok=0;$errors=[];
                foreach($preview['rows'] as $entry){
                    if($entry['errors']){$errors[]='Baris '.$entry['line'].': '.implode(' ',$entry['errors']);continue;}
                    $row=$entry['data'];$v=Validator::make($row,$this->rules($type));
                    if($v->fails()||$this->duplicate($type,$row)){$errors[]='Baris '.$entry['line'].': tidak valid atau duplikat saat penyimpanan.';continue;}
                    if($type==='job_vacancies')$row['expires_at']??=\Carbon\Carbon::parse($row['fetched_at'])->addDays(30)->toDateString();else $row['weekly_hours']??=1;
                    DB::table($type)->insert([...$row,'status'=>'draft','created_at'=>now(),'updated_at'=>now()]);$ok++;
                }
                DB::table('audit_logs')->insert(['user_id'=>$r->user()->id,'action'=>'catalog.csv_import','subject_type'=>$type,'details'=>json_encode(['success'=>$ok,'errors'=>count($errors)]),'created_at'=>now(),'updated_at'=>now()]);
                return compact('ok','errors');
            });
            Cache::put('import-done:'.$preview['token'],true,now()->addHour());$r->session()->forget('import_preview.'.$type);
        } finally {$lock->release();}
        return redirect($this->destination($type))->with('success',"Tersimpan sebagai draft: {$result['ok']}. Tidak disimpan: ".count($result['errors']))->with('import_errors',$result['errors']);
    }
}
