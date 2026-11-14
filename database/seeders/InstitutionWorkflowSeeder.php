<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstitutionWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        if(!filter_var(env('DEMO_SEED',false),FILTER_VALIDATE_BOOLEAN))return;
        if(app()->environment('production') && !filter_var(env('ALLOW_DEMO_IN_PRODUCTION',false),FILTER_VALIDATE_BOOLEAN))return;
        $body=DB::table('certification_bodies')->where('status','active')->orderBy('id')->first();
        if(!$body)return;
        foreach(['pending'=>'Identifikasi Bahaya di Gudang','draft'=>'Praktik Pengemasan Aman','revision'=>'Pencatatan Inventori Digital','rejected'=>'Kompetensi Operasional Umum'] as $status=>$title){
            $name='Demo Pengajuan — '.$title;
            if(DB::table('training_submissions')->where('certification_body_id',$body->id)->where('payload','like','%'.$title.'%')->exists())continue;
            $payload=['title'=>$name,'description'=>'Contoh pengajuan untuk menguji alur tinjauan. Pelatihan dilaksanakan di luar aplikasi.','mode'=>'offline','duration_hours'=>8,'weekly_hours'=>4,'cost'=>100000,'source_url'=>'https://example.com/demo-training','outcomes'=>[['new_name'=>$title,'category'=>'Logistik','description'=>'Kemampuan operasional pada konteks gudang, memerlukan peninjauan makna sebelum diterima sebagai skill baku.','target_level'=>2]]];
            DB::table('training_submissions')->insert(['certification_body_id'=>$body->id,'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE),'status'=>$status,'review_note'=>in_array($status,['revision','rejected'])?'Perjelas batas kompetensi dan periksa padanan skill dalam katalog.':null,'submitted_at'=>$status!=='draft'?now():null,'created_at'=>now(),'updated_at'=>now()]);
        }
    }
}
