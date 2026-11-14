<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Mail\AccountActionMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Hash,Mail,Password,RateLimiter,URL};
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
class AuthController {
 public function loginForm(Request $request)
 {
  $direct = $this->allowsDirectDemoLogin($request);
  $host = $request->getHost();
  $isNgrok = Str::endsWith($host, ['.ngrok-free.dev', '.ngrok-free.app', '.ngrok.app', '.ngrok.io', '.ngrok.dev', '.ngrok.pizza']);
  $accounts = $this->demoLoginEnabled() && ($direct || $isNgrok) ? $this->demoAccounts() : [];
  $role = $request->query('demo');
  $selectedEmail = is_string($role) ? ($accounts[$role] ?? null) : null;

  return view('auth', [
   'mode' => 'login',
   'demoRoles' => array_keys($accounts),
   'directDemoLogin' => $direct,
   'demoEmail' => $selectedEmail,
  ]);
 }

 private function demoLoginEnabled(): bool
 {
  return app()->isLocal() && config('auth.demo_login.enabled');
 }

 private function allowsDirectDemoLogin(Request $request): bool
 {
  return $this->demoLoginEnabled()
   && in_array($request->getHost(), ['127.0.0.1', 'localhost', '::1', '[::1]'], true)
   && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
   && !$request->headers->has('X-Forwarded-For')
   && !$request->headers->has('X-Forwarded-Host')
   && !$request->headers->has('X-Forwarded-Proto')
   && !$request->headers->has('Forwarded');
 }

 private function demoAccounts(): array
 {
  return [
   'Pelamar' => 'raka@example.com',
   'Admin' => config('auth.demo_login.owner_email'),
   'Badan Sertifikasi' => 'kontak@logistiknusantara.id',
  ];
 }
 public function registerForm(Request $r){return view('auth',['mode'=>$r->query('role')==='sertifikasi'?'sertifikasi':'register']);}
 public function forgotForm(){return view('auth',['mode'=>'forgot']);}
 public function resetForm(Request $r,string $token){return view('auth',['mode'=>'reset','token'=>$token,'email'=>$r->query('email')]);}
 public function register(Request $r){
  $v=$r->validate(['name'=>'required|string|max:150','email'=>'required|email|max:190|unique:users,email','password'=>'required|string|min:12|confirmed','role'=>'nullable|in:pelamar,sertifikasi','body_name'=>'required_if:role,sertifikasi|nullable|string|max:190','contact'=>'nullable|string|max:190']);
  $role=($v['role']??'pelamar')==='sertifikasi'?'sertifikasi':'pelamar';
  $user=DB::transaction(function()use($v,$role){$u=User::create(['name'=>$v['name'],'email'=>$v['email'],'password'=>$v['password'],'role'=>$role,'is_active'=>true]);if($role==='sertifikasi')DB::table('certification_bodies')->insert(['user_id'=>$u->id,'name'=>$v['body_name'],'contact'=>$v['contact']??null,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);return $u;});
  event(new Registered($user));$this->sendVerification($user);return redirect('/verification-notice')->with('success','Akun terdaftar. Periksa email untuk tautan verifikasi.');
 }
 private function sendVerification(User $user):void {
  $url=URL::temporarySignedRoute('verification.verify',now()->addMinutes(60),['id'=>$user->id,'hash'=>sha1($user->email)]);
  Mail::to($user->email)->send(new AccountActionMail($user->name,$url,'verification',60));
 }
 public function resend(Request $r){$r->validate(['email'=>'required|email']);$user=User::where('email',$r->email)->first();if($user&&!$user->hasVerifiedEmail())$this->sendVerification($user);return back()->with('success','Jika akun memerlukan verifikasi, tautan telah dikirim.');}
 public function verify(Request $r,int $id,string $hash){abort_unless($r->hasValidSignature(),403);$u=User::findOrFail($id);abort_unless(hash_equals(sha1($u->email),$hash),403);if(!$u->hasVerifiedEmail()){$u->forceFill(['email_verified_at'=>now()])->save();event(new Verified($u));}return redirect()->route('login')->with('success','Email terverifikasi. Silakan masuk.');}
 public function login(Request $r){
  if ($r->has('demo_role')) {
   abort_unless($this->allowsDirectDemoLogin($r), 404);
   $role = $r->validate(['demo_role' => 'required|string|max:30'])['demo_role'];
   $email = $this->demoAccounts()[$role] ?? null;
   $password = config($role === 'Admin' ? 'auth.demo_login.owner_password' : 'auth.demo_login.password');
   abort_unless(filled($email) && filled($password), 404);
   $data = ['email' => $email, 'password' => $password];
  } else {
   $data = $r->validate(['email'=>'required|email','password'=>'required|string']);
  }

  $key='login:'.strtolower($data['email']).':'.$r->ip();
  if(RateLimiter::tooManyAttempts($key,5))return back()->withErrors(['email'=>'Terlalu banyak percobaan. Coba kembali nanti.']);
  if(!Auth::attempt($data)) {RateLimiter::hit($key,60);return back()->withErrors(['email'=>'Email atau kata sandi tidak sesuai.']);}
  $u=Auth::user();if(!$u->is_active){Auth::logout();return back()->withErrors(['email'=>'Akun tidak aktif.']);}
  if(!$u->email_verified_at){Auth::logout();return redirect('/verification-notice')->withErrors(['email'=>'Verifikasi email Anda sebelum login.']);}
  RateLimiter::clear($key);$r->session()->regenerate();$destination=$u->role==='admin'?'/admin/dashboard':($u->role==='sertifikasi'?'/sertifikasi/'.($u->certificationBody?->status==='active'?'dashboard':'menunggu-verifikasi'):'/pelamar/dashboard');return redirect($destination);
 }
 public function logout(Request $r){Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect()->route('login');}
 public function forgot(Request $r){$r->validate(['email'=>'required|email']);Password::sendResetLink($r->only('email'));return back()->with('success','Jika email terdaftar, tautan pemulihan akan dikirim.');}
 public function reset(Request $r){$r->validate(['token'=>'required','email'=>'required|email','password'=>'required|min:12|confirmed']);$result=Password::reset($r->only('email','password','password_confirmation','token'),function(User $user,string $password){$user->forceFill(['password'=>Hash::make($password),'remember_token'=>Str::random(60)])->save();event(new PasswordReset($user));});return $result===Password::PASSWORD_RESET?redirect()->route('login')->with('success','Kata sandi diperbarui.'):back()->withErrors(['email'=>'Tautan tidak valid atau kedaluwarsa.']);}
}
