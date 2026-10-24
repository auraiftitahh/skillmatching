<x-layouts.auth title="Status Akun Lembaga">
    <p class="eyebrow">Badan Sertifikasi</p><h1>Status akun lembaga</h1>
    <x-alert>{{ $statusMessage }}</x-alert>
    <p class="intro">Akses layanan lembaga tersedia setelah email terverifikasi, akun aktif, dan lembaga disetujui Admin.</p>
    <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button button-outline full-width">Keluar dari akun</button></form>
</x-layouts.auth>
