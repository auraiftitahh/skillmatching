<x-layouts.auth :title="$pageTitle">
    <a class="back-link" href="{{ route('preview.index') }}"><x-icon name="arrow" />Kembali ke pratinjau</a>
    <p class="eyebrow">Ruang karier Anda</p><h1>{{ $pageTitle }}</h1>
    <p class="intro">{{ $mode === 'forgot-password' ? 'Tautan pemulihan akan membantu Anda mengakses akun kembali.' : 'Siapkan langkah selanjutnya bersama Skill Matching.' }}</p>
    <x-alert>Contoh tampilan. Isian dan pengiriman form belum diaktifkan.</x-alert>
    <fieldset disabled class="preview-fields">
        @if ($mode === 'register')<label class="field" for="preview-name">Nama lengkap<input id="preview-name" type="text" placeholder="Nama lengkap Anda" autocomplete="off"></label>@endif
        <label class="field" for="preview-email">Email<input id="preview-email" type="email" placeholder="nama@contoh.com" autocomplete="off"></label>
        @if ($mode !== 'forgot-password')<label class="field" for="preview-password">Kata sandi<input id="preview-password" type="password" placeholder="Kata sandi Anda" autocomplete="new-password"></label>@endif
        @if ($mode === 'register')<label class="field" for="preview-confirmation">Konfirmasi kata sandi<input id="preview-confirmation" type="password" placeholder="Ulangi kata sandi" autocomplete="new-password"></label>@endif
        <button type="button" class="button button-primary full-width" disabled>{{ $mode === 'login' ? 'Masuk' : ($mode === 'register' ? 'Daftar' : 'Kirim tautan pemulihan') }}</button>
    </fieldset>
    <div class="auth-links">
        @if ($mode === 'login')<a href="{{ route('preview.auth', 'forgot-password') }}">Lupa kata sandi?</a><p>Belum punya akun? <a href="{{ route('preview.auth', 'register') }}">Daftar</a></p>@else<a href="{{ route('preview.auth', 'login') }}">Kembali ke tampilan masuk</a>@endif
    </div>
</x-layouts.auth>
