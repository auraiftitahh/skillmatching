@php
$account = $accountDetail ?? null;
$accountBody = $accountDetailBody ?? null;
@endphp
@if($account && $account->role!=='admin')
<section class="card narrow">
    <h2>{{ $account->name }}</h2><p>{{ $account->email }}</p>
    <div class="chips"><span class="badge">{{ $account->role==='pelamar'?'Pelamar':'Badan Sertifikasi' }}</span>
        <span class="badge">Akun: {{ $account->is_active?'Aktif':'Nonaktif' }}</span><span class="badge">Email: {{ $account->email_verified_at?'Terverifikasi':'Belum verifikasi' }}</span>
        @if($accountBody)<span class="badge">{{ ['active'=>'Terverifikasi','pending'=>'Menunggu Verifikasi','rejected'=>'Ditolak','disabled'=>'Nonaktif'][$accountBody->status]??'Tidak tersedia' }}</span>@endif
    </div>
    @if($page==='akun/verifikasi' && $account->role==='sertifikasi' && $accountBody && $accountBody->status==='pending')
    <form class="editor-grid" action="{{ route('accounts.save',$account->id) }}" method="post">
        @csrf
        <input type="hidden" name="decision" value="verify">
        <label>Alasan keputusan<textarea name="reason" required minlength="5" maxlength="500"></textarea></label>
        <button type="submit" class="btn primary" data-confirm="Verifikasi lembaga ini?">Verifikasi Lembaga</button>
    </form>
    @endif
    @if($page==='akun/detail' && $accountBody?->status==='pending')<div class="actions"><a class="btn primary" href="/admin/akun/verifikasi?id={{ $account->id }}">Tinjau Persetujuan Lembaga</a></div>@endif
    <div class="catalog-toggle-row">
        <div><strong>Status akses akun</strong><p>Perubahan status tidak menghapus akun ataupun riwayatnya.</p></div>
        @if($page==='akun/status')<form method="post" action="{{ route('accounts.save',$account->id) }}" data-status-toggle data-subject="akun {{ $account->name }}">
            @csrf
            <input type="hidden" name="decision" value="{{ $account->is_active?'deactivate':'activate' }}">
            <input type="hidden" name="reason" value="">
            <button type="submit" role="switch" aria-checked="{{ $account->is_active?'true':'false' }}" aria-label="{{ $account->is_active?'Nonaktifkan':'Aktifkan' }} akun" class="ui-switch {{ $account->is_active?'is-on':'' }}"><span></span></button>
        </form>@else<a class="btn outline" href="/admin/akun/status?id={{ $account->id }}">Kelola Status Akun</a>@endif
    </div>
    <a class="btn outline" href="/admin/akun">Kembali ke Akun Pengguna</a>
</section>
@else
<section class="card"><div class="empty">Akun tidak ditemukan atau tidak dapat dikelola dari halaman ini. <a href="/admin/akun">Kembali ke daftar</a></div></section>
@endif
