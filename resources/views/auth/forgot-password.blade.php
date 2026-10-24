<x-layouts.auth title="Lupa Kata Sandi">
    <a class="back-link" href="{{ route('login') }}"><x-icon name="arrow" />Kembali ke halaman masuk</a>
    <p class="eyebrow">Pemulihan akun</p><h1>Lupa kata sandi?</h1>
    <p class="intro">Masukkan email akun Anda. Kami akan mengirim tautan untuk membuat kata sandi baru.</p>
    @if (session('status'))<x-alert>{{ session('status') }}</x-alert>@endif
    <form action="{{ route('password.email') }}" method="post" data-pending-form>
        @csrf
        <label class="field" for="email">Email akun<input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="190" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<span class="field-error" id="email-error">{{ $message }}</span>@enderror</label>
        <button class="button button-primary full-width" type="submit" data-pending-label="Memproses permintaan…">Kirim tautan pemulihan</button>
        <p class="form-hint" aria-live="polite" data-pending-status></p>
    </form>
    <p class="form-hint">Tautan hanya berlaku satu kali selama {{ config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60) }} menit. Anda tetap perlu memverifikasi email dan memenuhi status kelayakan akun setelah reset.</p>
</x-layouts.auth>
