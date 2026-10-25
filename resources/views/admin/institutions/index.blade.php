<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <p class="section-description">Tinjau pengajuan lembaga dan catat keputusan Anda. Daftar awal menampilkan lembaga yang menunggu persetujuan.</p>
    <form method="get" action="{{ route('admin.institutions.index') }}" class="card institution-filters">
        <label class="field" for="q">Cari lembaga<input id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Nama lembaga, penanggung jawab, atau email"></label>
        <label class="field" for="status">Status lembaga<select id="status" name="status"><option value="all" @selected($filters['status'] === 'all')>Semua status</option>@foreach (\App\Support\InstitutionReviewActions::LABELS as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></label>
        <div class="actions"><button class="button button-primary" type="submit">Cari</button><a class="button button-outline" href="{{ route('admin.institutions.index') }}">Atur ulang</a></div>
    </form>
    <section class="card"><div class="list-heading"><h2>Daftar lembaga</h2><p>{{ $bodies->total() }} lembaga ditemukan</p></div>
        @if ($bodies->isEmpty())<div class="small-empty"><x-icon name="badge" /><h3>Tidak ada lembaga yang sesuai</h3><p>Coba pencarian lain atau tampilkan semua status.</p><a class="button button-outline" href="{{ route('admin.institutions.index', ['status' => 'all']) }}">Tampilkan semua lembaga</a></div>
        @else
            <p class="form-hint" id="institution-table-hint">Status lembaga, status akun, dan verifikasi email diperiksa secara terpisah. Pada layar kecil, geser tabel untuk melihat seluruh kolom.</p>
            <div class="table-scroll" role="region" aria-label="Daftar pengajuan lembaga" aria-describedby="institution-table-hint" tabindex="0"><table class="institutions-table"><caption>Menampilkan {{ $bodies->firstItem() }}–{{ $bodies->lastItem() }} dari {{ $bodies->total() }} lembaga</caption><thead><tr><th scope="col">Lembaga</th><th scope="col">Penanggung jawab</th><th scope="col">Status lembaga</th><th scope="col">Status akun</th><th scope="col">Email</th><th scope="col">Tindakan</th></tr></thead><tbody>
            @foreach ($bodies as $body)<tr><th scope="row">{{ $body->name }}</th><td><span class="table-name">{{ $body->user?->name ?? 'Akun tidak tersedia' }}</span><span class="table-secondary">{{ $body->user?->email }}</span></td><td><span @class(['badge', 'badge-warning' => $body->status === 'pending', 'badge-muted' => in_array($body->status, ['rejected', 'disabled'])])>{{ \App\Support\InstitutionReviewActions::LABELS[$body->status] }}</span></td><td>{{ $body->user?->is_active ? 'Aktif' : 'Nonaktif' }}</td><td>{{ $body->user?->hasVerifiedEmail() ? 'Terverifikasi' : 'Belum terverifikasi' }}</td><td><a class="button button-outline" href="{{ route('admin.institutions.show', array_merge(['body' => $body->id], $filters)) }}" aria-label="Tinjau {{ $body->name }}">Tinjau</a></td></tr>@endforeach
            </tbody></table></div><div class="pagination-wrap">{{ $bodies->links() }}</div>
        @endif
    </section>
</x-layouts.workspace>
