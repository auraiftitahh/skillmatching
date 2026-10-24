<x-layouts.auth title="Atur Ulang Kata Sandi">
    <a class="back-link" href="{{ route('password.request') }}"><x-icon name="arrow" />Kembali ke pemulihan akun</a>
    <p class="eyebrow">Akses kembali akun Anda</p><h1>Buat kata sandi baru</h1>
    <p class="intro">Gunakan kata sandi minimal 12 karakter. Setelah berhasil, masuk kembali dengan kata sandi baru.</p>
    @if ($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
    <form action="{{ route('password.update') }}" method="post" data-pending-form>
        @csrf<input type="hidden" name="token" value="{{ $token }}">
        <label class="field" for="email">Email akun<input id="email" name="email" type="email" value="{{ $email }}" autocomplete="username" readonly required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<span class="field-error" id="email-error">{{ $message }}</span>@enderror</label>
        <x-password-field name="password" label="Kata sandi baru" />
        <x-password-field name="password_confirmation" label="Konfirmasi kata sandi baru" />
        <button class="button button-primary full-width" type="submit" data-pending-label="Memperbarui kata sandi…">Simpan kata sandi baru</button>
        <p class="form-hint" aria-live="polite" data-pending-status></p>
    </form>
</x-layouts.auth>
