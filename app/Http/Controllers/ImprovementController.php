<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class ImprovementController
{
    public function password(Request $r)
    {
        $d=$r->validate(['current_password'=>'required|current_password','password'=>'required|string|min:12|confirmed|different:current_password']);
        $r->user()->forceFill(['password'=>Hash::make($d['password']),'remember_token'=>\Illuminate\Support\Str::random(60)])->save();
        $r->session()->regenerate();
        return back()->with('success','Kata sandi diperbarui. Gunakan kata sandi baru untuk masuk berikutnya.');
    }
    public function bookmark(Request $r,int $id)
    {
        abort_unless($r->user()->role==='pelamar',403);
        \App\Support\Publication::jobs()->whereKey($id)->firstOrFail();
        $r->validate(['saved'=>'required|boolean']);
        $q=\Illuminate\Support\Facades\DB::table('vacancy_bookmarks');
        if($r->boolean('saved'))$q->insertOrIgnore(['user_id'=>$r->user()->id,'job_vacancy_id'=>$id,'created_at'=>now(),'updated_at'=>now()]);
        else $q->where(['user_id'=>$r->user()->id,'job_vacancy_id'=>$id])->delete();
        return back()->with('success',$r->boolean('saved')?'Lowongan disimpan.':'Penanda lowongan dihapus.');
    }
}
