@extends('layouts.base')
@section('content')
<div class="auth-header"><a class="brand" href="/login"><span class="logo-symbol">◈</span> skillmatching</a><span>PLATFORM KARIER · SKILL MATCHING</span></div>
<div class="auth-layout"><div class="auth-hero"><span>SKILL MATCHING</span><h1>Keterampilan hari ini.<br>Peluang esok hari.</h1><p>Kenali potensi, temukan kecocokan, dan susun langkah karier berikutnya.</p><div class="hero-orbit">◎</div></div>
<div class="auth-panel">
@if(session('success'))<div class="alert-success">{{session('success')}}</div>
@endif
@if($errors->any())<div class="alert-error">
@foreach($errors->all() as $error)<div>{{ $error }}</div>
@endforeach</div>
@endif
@if($mode==='login')
<h2>Selamat datang kembali</h2><p>Masuk ke akun Skill Matching Anda.</p><form action="/login" method="post">
@csrf<label>Email<input name="email" type="email" value="{{ $demoEmail ?? old('email') }}" required autocomplete="username"></label><label>Kata sandi<input name="password" type="password" required autocomplete="current-password" @if($demoEmail ?? null) autofocus @endif></label><button class="btn primary full">Masuk <span>→</span></button></form><div class="subnav"><a href="/verification-notice">Belum verifikasi email?</a><a href="/forgot-password">Lupa kata sandi?</a><a href="/register">Belum punya akun? Daftar</a><a href="/register?role=sertifikasi">Daftar sebagai lembaga</a></div>
@if(!empty($demoRoles))
<section class="demo-login" aria-labelledby="demo-login-title">
    <h3 id="demo-login-title">Masuk akun demo</h3>
    @if($directDemoLogin)
        <form class="demo-login-actions" action="{{ route('login', absolute: false) }}" method="post">
            @csrf
            @foreach($demoRoles as $role)
                <button class="btn outline" type="submit" name="demo_role" value="{{ $role }}">{{ $role }}</button>
            @endforeach
        </form>
    @else
        <p>Pilih peran untuk mengisi email. Masukkan kata sandi akun demo untuk masuk.</p>
        <div class="demo-login-actions">
            @foreach($demoRoles as $role)
                <a class="btn outline" href="{{ route('login', ['demo' => $role], absolute: false) }}">{{ $role }}</a>
            @endforeach
        </div>
    @endif
</section>
@endif
@elseif(in_array($mode,['register','sertifikasi']))
<h2>{{ $mode==='sertifikasi'?'Daftar Badan Sertifikasi':'Mulai perjalanan Anda' }}</h2><p>{{ $mode==='sertifikasi'?'Akun lembaga memerlukan persetujuan Admin Platform.':'Daftar dan mulai memetakan kemampuan Anda.' }}</p><form action="/register" method="post">
@csrf<input type="hidden" name="role" value="{{ $mode==='sertifikasi'?'sertifikasi':'pelamar' }}"><label>Nama lengkap<input name="name" value="{{old('name')}}" required maxlength="150"></label>
@if($mode==='sertifikasi')<label>Nama lembaga<input name="body_name" value="{{old('body_name')}}" required></label><label>Kontak resmi<input name="contact" value="{{old('contact')}}"></label>
@endif<label>Email<input name="email" type="email" value="{{old('email')}}" required></label><label>Kata sandi (minimal 12 karakter)<input name="password" type="password" required minlength="12"></label><label>Konfirmasi kata sandi<input name="password_confirmation" type="password" required minlength="12"></label><button class="btn primary full">Daftar</button></form><div class="subnav"><a href="/login">Sudah punya akun? Masuk</a></div>
@elseif($mode==='forgot')<h2>Pemulihan kata sandi</h2><p>Masukkan email Anda untuk menerima tautan pemulihan.</p><form method="post" action="/forgot-password">
@csrf<label>Email<input type="email" name="email" required></label><button class="btn primary full">Kirim tautan</button></form><a href="/login">Kembali ke login</a>
@elseif($mode==='verify')<h2>Verifikasi email Anda</h2><p>Buka tautan verifikasi pada email. Periksa folder spam jika belum diterima. Lembaga juga memerlukan persetujuan Admin setelah verifikasi email.</p><form method="post" action="{{ route('verification.send') }}">@csrf<label>Email terdaftar<input type="email" name="email" required autocomplete="email"></label><button class="btn primary">Kirim Ulang Tautan</button></form><a href="/login">Kembali ke login</a>
@elseif($mode==='reset')<h2>Buat kata sandi baru</h2><form method="post" action="/reset-password">
@csrf<input type="hidden" name="token" value="{{ $token }}"><label>Email<input type="email" name="email" value="{{ $email }}" required></label><label>Kata sandi baru<input type="password" name="password" minlength="12" required></label><label>Konfirmasi<input type="password" name="password_confirmation" minlength="12" required></label><button class="btn primary full">Simpan kata sandi</button></form><a href="/login">Kembali ke login</a>
@endif
</div></div><footer class="public-footer">© {{ date('Y') }} Skill Matching · Setiap keterampilan, sebuah peluang.</footer>
@endsection
