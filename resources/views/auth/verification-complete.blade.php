<x-layouts.auth title="Email Terverifikasi">
    <span class="empty-icon"><x-icon name="check" /></span>
    <p class="eyebrow">Verifikasi selesai</p><h1>Email berhasil diverifikasi</h1>
    <p class="intro">Terima kasih. Alamat email akun Anda sudah terkonfirmasi.</p>
    @if ($isInstitution)<x-alert>Persetujuan Badan Sertifikasi diproses terpisah oleh Admin. Verifikasi email tidak mengubah status persetujuan lembaga.</x-alert>@endif
</x-layouts.auth>
