<x-layouts.auth title="Masuk">
    <p class="eyebrow">Ruang karier Anda</p><h1>Selamat datang kembali</h1>
    <p class="intro">Masuk untuk melanjutkan perjalanan Anda bersama Skill Matching.</p>
    @if (session('status'))<x-alert>{{ session('status') }}</x-alert>@endif
    @if (isset($throttleMessage))<x-alert type="error">{{ $throttleMessage }}</x-alert>@endif
    @if ($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
    <form action="{{ route('login.store') }}" method="post" data-pending-form>
        @csrf
        <label class="field" for="email">Email<input id="email" name="email" type="email" value="{{ old('email', $emailValue ?? '') }}" autocomplete="username" maxlength="190" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<span id="email-error" class="field-error">{{ $message }}</span>@enderror</label>
        <x-password-field name="password" label="Kata sandi" autocomplete="current-password" />
        <button class="button button-primary full-width" type="submit" data-pending-label="Memeriksa akun…">Masuk</button>
        <p class="form-hint" aria-live="polite" data-pending-status></p>
    </form>
    <div class="auth-links"><p>Belum punya akun? <a href="{{ route('register') }}">Buat akun</a></p><p><a href="{{ route('verification.notice') }}">Kirim ulang tautan verifikasi email</a></p></div>
</x-layouts.auth>
