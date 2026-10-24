<x-layouts.auth title="Verifikasi Email">
    <p class="eyebrow">Satu langkah lagi</p><h1>Verifikasi email Anda</h1>
    <p class="intro">Buka email dari Skill Matching dan klik tombol verifikasi. Tautan berlaku selama {{ config('auth.verification.expire', 60) }} menit.</p>
    @if (session('status'))<x-alert>{{ session('status') }}</x-alert>@endif
    <p class="form-hint">Belum menerima email? Periksa folder spam atau kirim ulang melalui form berikut. Verifikasi email tidak otomatis menyetujui akun lembaga.</p>
    <form action="{{ route('verification.send') }}" method="post" data-pending-form>
        @csrf
        <label class="field" for="email">Email akun<input id="email" name="email" type="email" value="{{ old('email', session('registration_email')) }}" autocomplete="email" maxlength="190" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<span class="field-error" id="email-error">{{ $message }}</span>@enderror</label>
        <button class="button button-primary full-width" type="submit" data-pending-label="Memproses permintaan…">Kirim ulang tautan</button>
        <p class="form-hint" aria-live="polite" data-pending-status></p>
    </form>
    <div class="auth-links"><a href="{{ route('register') }}">Kembali ke pendaftaran</a></div>
</x-layouts.auth>
