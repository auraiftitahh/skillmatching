<x-layouts.auth title="Tautan Pemulihan Tidak Berlaku">
    <p class="eyebrow">Pemulihan akun</p><h1>Tautan tidak berlaku</h1>
    <p class="intro">Tautan pemulihan sudah kedaluwarsa, pernah digunakan, atau tidak valid. Minta tautan baru untuk melanjutkan.</p>
    <a class="button button-primary full-width" href="{{ route('password.request') }}">Minta tautan baru</a>
    <div class="auth-links"><a href="{{ route('login') }}">Kembali ke halaman masuk</a></div>
</x-layouts.auth>
