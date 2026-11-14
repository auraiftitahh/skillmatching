@if(str_starts_with($page,'pelatihan'))<div class="context-banner">Pelatihan dibuat dan direvisi oleh lembaga. <a href="/admin/pengajuan">Buka tinjauan pengajuan</a></div>@endif
@php
    $kind = str_starts_with($page, 'skill') ? 'skills' : (str_starts_with($page, 'jabatan') ? 'jobs_taxonomy' : (str_starts_with($page, 'lowongan') ? 'job_vacancies' : 'trainings'));
    $listing = match($kind) {
        'skills' => $skillListing ?? collect(),
        'jobs_taxonomy' => $filteredTaxonomy ?? $jobsTax,
        'job_vacancies' => $filteredVacancies ?? $allJobs,
        default => $filteredTrainings ?? $trainings,
    };
    $base = match($kind) {
        'skills' => 'skill',
        'jobs_taxonomy' => 'jabatan',
        'job_vacancies' => 'lowongan',
        default => 'pelatihan',
    };
@endphp

@if($kind === 'skills')
    <section class="card catalog-toolbar">
        <div class="catalog-heading">
            <div>
                <h2>Daftar Keterampilan</h2>
                <p>Kelola keterampilan yang digunakan pada profil, lowongan, dan pelatihan. Keterampilan nonaktif tetap tersimpan.</p>
            </div>
            <a class="btn primary" href="/admin/pengajuan">Tinjau Usulan Skill</a>
        </div>
        <div class="skill-counts" aria-label="Ringkasan keterampilan">
            <span><strong>{{ $skillCounts['all'] }}</strong> Total</span>
            <span><strong>{{ $skillCounts['active'] }}</strong> Aktif</span>
            <span><strong>{{ $skillCounts['inactive'] }}</strong> Nonaktif</span>
        </div>
        <form action="/admin/skill" method="get" class="catalog-filters" role="search">
            <label for="skill-search">Cari keterampilan
                <input type="search" id="skill-search" name="q" value="{{ $skillSearch }}" placeholder="Nama, kategori, atau alias" maxlength="120" autocomplete="off">
            </label>
            <label for="skill-status">Status
                <select id="skill-status" name="status">
                    <option value="all" @selected($skillStatusFilter === 'all')>Semua status</option>
                    <option value="active" @selected($skillStatusFilter === 'active')>Aktif</option>
                    <option value="inactive" @selected($skillStatusFilter === 'inactive')>Nonaktif</option>
                </select>
            </label>
            <button class="btn primary" type="submit">Terapkan</button>
            <a class="btn outline" href="/admin/skill">Atur Ulang</a>
        </form>
    </section>
    <section class="card catalog-table">
        <div class="catalog-table-heading">
            <h3>Hasil Pencarian</h3>
            <span class="soft-note">{{ $skillListing->total() }} keterampilan ditemukan</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Nama Keterampilan</th><th>Kategori</th><th>Nama Lain (Alias)</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>
                @forelse($skillListing as $item)
                    @php
                        $aliases = is_array($item->aliases) ? $item->aliases : (json_decode($item->aliases ?: '[]', true) ?: []);
                    @endphp
                    <tr class="{{ $item->is_active ? '' : 'is-inactive-row' }}">
                        <td><strong>{{ $item->name }}</strong></td>
                        <td>{{ $item->category }}</td>
                        <td>{{ count($aliases) ? implode(', ', $aliases) : '—' }}</td>
                        <td><span class="badge {{ $item->is_active ? 'badge-active' : 'badge-inactive' }}">{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td>
                            <div class="row-actions">
                                <a href="/admin/skill/detail?id={{ $item->id }}">Detail</a>
                                <a href="/admin/skill/form?id={{ $item->id }}">Edit</a>
                                <form method="post" action="{{ route('admin.skills.status', $item->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $item->is_active ? 0 : 1 }}">
                                    <button type="submit" role="switch" aria-checked="{{ $item->is_active ? 'true' : 'false' }}" aria-label="{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $item->name }}" class="ui-switch {{ $item->is_active ? 'is-on' : '' }}" data-confirm="{{ $item->is_active ? 'Nonaktifkan keterampilan ini?' : 'Aktifkan keterampilan ini?' }}"><span></span></button>
                                </form>
                                <form method="post" action="{{ route('admin.skills.destroy', $item->id) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="link-button danger-text" data-confirm="Hapus keterampilan {{ $item->name }} secara permanen? Penghapusan hanya bisa dilakukan bila belum digunakan oleh data lain.">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty">Tidak ditemukan keterampilan dengan kriteria ini. <a href="/admin/skill">Lihat Semua</a></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($skillListing->hasPages())
            <nav class="catalog-pagination" aria-label="Halaman daftar keterampilan">
                <span>Halaman {{ $skillListing->currentPage() }} dari {{ $skillListing->lastPage() }}</span>
                <div class="actions">
                    @if($skillListing->onFirstPage())
                        <span class="btn outline disabled-link">Sebelumnya</span>
                    @else
                        <a class="btn outline" href="{{ $skillListing->previousPageUrl() }}" rel="prev">Sebelumnya</a>
                    @endif
                    @if($skillListing->hasMorePages())
                        <a class="btn outline" href="{{ $skillListing->nextPageUrl() }}" rel="next">Berikutnya</a>
                    @else
                        <span class="btn outline disabled-link">Berikutnya</span>
                    @endif
                </div>
            </nav>
        @endif
        <p class="catalog-hint">Nonaktifkan jika keterampilan tidak digunakan untuk data baru. Hapus permanen hanya tersedia untuk keterampilan yang belum terkait data lain.</p>
    </section>
@else
    <div class="section-actions"><div class="actions">
        @if($base!=='pelatihan')<a class="btn primary" href="/admin/{{ $base }}/form">+ Tambah {{ ['jabatan'=>'Jabatan','lowongan'=>'Lowongan','pelatihan'=>'Pelatihan'][$base] }}</a>@endif
        @if($base==='lowongan')<a class="btn outline" href="/admin/{{ $base }}/impor">Impor CSV</a>@endif
    </div></div>
    <section class="card">
        @php
            $filters = $kind==='jobs_taxonomy' ? [['name'=>'status','label'=>'Status','options'=>[''=>'Semua','active'=>'Aktif','inactive'=>'Nonaktif']]] :
                [['name'=>'status','label'=>'Status','options'=>$kind==='job_vacancies' ? [''=>'Semua','draft'=>'Draft','active'=>'Aktif','closed'=>'Ditutup','expired'=>'Kedaluwarsa','disabled'=>'Nonaktif'] : [''=>'Semua','draft'=>'Draft','active'=>'Aktif','disabled'=>'Nonaktif']]];
        @endphp
        @include('components.data-filters', ['searchHint'=>$kind==='jobs_taxonomy'?'Cari nama jabatan':($kind==='job_vacancies'?'Cari judul atau perusahaan':'Cari pelatihan atau penyedia'),'filterOptions'=>$filters])
        <div class="table-wrap"><table>
            <thead><tr><th>Nama</th><th>Informasi</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse($listing as $item)
                @php
                    $enabled=$kind==='jobs_taxonomy'?(bool)$item->is_active:$item->status==='active';
                @endphp
                <tr>
                    <td><strong>{{ $item->name ?? $item->title }}</strong></td>
                    <td>{{ $item->category ?? $item->company ?? $item->provider ?? '—' }}</td>
                    <td><span class="badge">{{ $kind==='jobs_taxonomy' ? ($enabled?'Aktif':'Nonaktif') : ($statusText[$item->status] ?? 'Nonaktif') }}</span></td>
                    <td><div class="row-actions">
                        <form method="post" action="{{ route('admin.catalog.status',[$kind,$item->id]) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_active" value="{{ $enabled ? '0' : '1' }}">
                            <button type="submit" class="ui-switch {{ $enabled?'is-on':'' }}" role="switch" aria-checked="{{ $enabled?'true':'false' }}" aria-label="{{ $enabled?'Nonaktifkan':'Aktifkan' }} {{ $item->name ?? $item->title }}" data-confirm="{{ $enabled?'Nonaktifkan data ini?':'Aktifkan data ini? Pastikan persyaratan sudah lengkap.' }}"><span></span></button>
                        </form>
                        <a href="/admin/{{ $base }}/detail?id={{ $item->id }}">Detail</a>
                        @if($base!=='pelatihan')<a href="/admin/{{ $base }}/form?id={{ $item->id }}">Edit</a>@endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="4">Tidak ada data yang sesuai pencarian.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        @include('components.list-pages', ['paginator'=>$listing])
    </section>
@endif
