<x-layouts.auth title="Buat Akun">
    <p class="eyebrow">Mulai bersama Skill Matching</p>
    <h1>Buat akun Anda</h1>
    <p class="intro">Pilih jenis akun dan gunakan email yang dapat Anda akses untuk verifikasi.</p>
    @if ($errors->any())<x-alert type="error">Pendaftaran belum berhasil. Periksa isian yang ditandai di bawah.</x-alert>@endif
    <form action="{{ route('register.store') }}" method="post" data-pending-form>
        @csrf
        <label class="field" for="role">Jenis akun
            <select id="role" name="role" data-registration-role aria-controls="institution-fields" required @error('role') aria-invalid="true" aria-describedby="role-error" @enderror>
                <option value="pelamar" @selected(old('role', $selectedRole) === 'pelamar')>Pelamar</option>
                <option value="sertifikasi" @selected(old('role', $selectedRole) === 'sertifikasi')>Badan Sertifikasi</option>
            </select>
            @error('role')<span id="role-error" class="field-error">{{ $message }}</span>@enderror
        </label>
        <label class="field" for="name">Nama lengkap<input id="name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="150" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>@error('name')<span id="name-error" class="field-error">{{ $message }}</span>@enderror</label>
        <label class="field" for="email">Email<input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="190" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>@error('email')<span id="email-error" class="field-error">{{ $message }}</span>@enderror</label>
        <fieldset id="institution-fields" class="institution-fields" @if (old('role', $selectedRole) !== 'sertifikasi') hidden disabled @endif>
            <legend>Informasi Badan Sertifikasi</legend>
            <label class="field" for="body_name">Nama Badan Sertifikasi<input id="body_name" name="body_name" value="{{ old('body_name') }}" maxlength="190" data-required-for-institution @if (old('role', $selectedRole) === 'sertifikasi') required @endif @error('body_name') aria-invalid="true" aria-describedby="body_name-error" @enderror>@error('body_name')<span id="body_name-error" class="field-error">{{ $message }}</span>@enderror</label>
            <label class="field" for="contact">Kontak lembaga <span class="optional-label">(opsional)</span><input id="contact" name="contact" value="{{ old('contact') }}" maxlength="190" @error('contact') aria-invalid="true" aria-describedby="contact-error" @enderror>@error('contact')<span id="contact-error" class="field-error">{{ $message }}</span>@enderror</label>
            <p class="form-hint">Setelah verifikasi email, akun lembaga masih memerlukan persetujuan Admin.</p>
        </fieldset>
        <p class="form-hint" id="password-hint">Gunakan kata sandi minimal 12 karakter.</p>
        <x-password-field name="password" label="Kata sandi" />
        <x-password-field name="password_confirmation" label="Konfirmasi kata sandi" />
        <button class="button button-primary full-width" type="submit" data-pending-label="Membuat akun…">Buat akun</button>
        <p class="form-hint" aria-live="polite" data-pending-status></p>
    </form>
    <div class="auth-links"><a href="{{ route('verification.notice') }}">Sudah mendaftar? Kirim ulang tautan verifikasi</a></div>
    <noscript><p class="form-hint">Untuk mendaftar sebagai lembaga tanpa JavaScript, <a href="{{ route('register', ['role' => 'sertifikasi']) }}">buka form Badan Sertifikasi</a>.</p></noscript>
</x-layouts.auth>
