<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <p class="section-description">Cari akun dan periksa status aksesnya. Persetujuan lembaga dikelola terpisah dari status aktif akun.</p>
    <form method="get" action="{{ route('admin.accounts.index') }}" class="card account-filters">
        <label class="field search-field" for="q">Cari akun<input id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Nama, email, atau nama lembaga"></label>
        <label class="field" for="role">Peran<select id="role" name="role"><option value="">Semua peran</option>@foreach (['pelamar' => 'Pelamar', 'admin' => 'Admin', 'sertifikasi' => 'Badan Sertifikasi'] as $value => $label)<option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="field" for="status">Status akun<select id="status" name="status"><option value="">Semua status</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option></select></label>
        <label class="field" for="verification">Verifikasi email<select id="verification" name="verification"><option value="">Semua kondisi</option><option value="verified" @selected(($filters['verification'] ?? '') === 'verified')>Terverifikasi</option><option value="unverified" @selected(($filters['verification'] ?? '') === 'unverified')>Belum terverifikasi</option></select></label>
        <div class="actions"><button type="submit" class="button button-primary">Cari</button><a href="{{ route('admin.accounts.index') }}" class="button button-outline">Atur ulang</a></div>
    </form>
    <section class="card">
        <div class="list-heading"><h2>Daftar akun</h2><p>{{ $accounts->total() }} akun ditemukan</p></div>
        @if ($accounts->isEmpty())
            <div class="small-empty"><x-icon name="user" /><h3>Tidak ada akun yang sesuai</h3><p>Coba kata pencarian lain atau atur ulang filter.</p><a class="button button-outline" href="{{ route('admin.accounts.index') }}">Atur ulang filter</a></div>
        @else
            <p class="form-hint" id="accounts-table-hint">Pada layar kecil, geser tabel untuk melihat seluruh kolom.</p>
            <div class="table-scroll" role="region" aria-label="Daftar akun pengguna" aria-describedby="accounts-table-hint" tabindex="0">
                <table class="accounts-table"><caption>Menampilkan {{ $accounts->firstItem() }}–{{ $accounts->lastItem() }} dari {{ $accounts->total() }} akun</caption><thead><tr><th scope="col">Nama dan email</th><th scope="col">Peran</th><th scope="col">Status akun</th><th scope="col">Email</th><th scope="col">Tindakan</th></tr></thead><tbody>
                @foreach ($accounts as $account)
                    <tr><th scope="row"><span class="table-name">{{ $account->name }}</span><span class="table-secondary">{{ $account->email }}</span></th><td>{{ \App\Support\WorkspaceNavigation::label($account->role) }}</td><td><span @class(['badge', 'badge-muted' => ! $account->is_active])>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span></td><td><span @class(['badge', 'badge-warning' => ! $account->hasVerifiedEmail()])>{{ $account->hasVerifiedEmail() ? 'Terverifikasi' : 'Belum terverifikasi' }}</span></td><td><a class="button button-outline" href="{{ route('admin.accounts.show', array_merge(['user' => $account->id], $filters)) }}" aria-label="Lihat detail akun {{ $account->name }}">Detail</a></td></tr>
                @endforeach
                </tbody></table>
            </div>
            <div class="pagination-wrap">{{ $accounts->links() }}</div>
        @endif
    </section>
</x-layouts.workspace>
