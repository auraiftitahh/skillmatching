<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class EnsureActive {
 public function handle(Request $request, Closure $next) {
  if (!$request->user() || !$request->user()->is_active) {
   Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
   return redirect()->route('login')->withErrors(['email'=>'Akun tidak aktif.']);
  }
  if(!$request->user()->email_verified_at)return redirect('/verification-notice')->withErrors(['email'=>'Verifikasi email sebelum mengakses ruang kerja.']);
  if($request->user()->role==='sertifikasi' && $request->isMethod('POST') && !$request->is('logout') && $request->user()->certificationBody?->status!=='active')abort(403,'Lembaga belum disetujui atau tidak aktif.');
  return $next($request);
 }
}
