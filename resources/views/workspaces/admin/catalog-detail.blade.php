@php
    $kind = match ($page) {
        'skill/detail' => 'skills',
        'jabatan/detail' => 'jobs_taxonomy',
        'lowongan/detail' => 'job_vacancies',
        default => 'trainings',
    };
    $base = match ($kind) {
        'skills' => 'skill',
        'jobs_taxonomy' => 'jabatan',
        'job_vacancies' => 'lowongan',
        default => 'pelatihan',
    };
    $row = $catalogDetail ?? null;
    $relations = $catalogDetailRelations ?? collect();
    $prerequisites = $catalogDetailPrerequisites ?? collect();
    $heading = ['skills' => 'Keterampilan', 'jobs_taxonomy' => 'Jabatan', 'job_vacancies' => 'Lowongan', 'trainings' => 'Pelatihan'][$kind];
@endphp

@if(!$row)
    <section class="card">
        <div class="empty">
            <h2>{{ $heading }} tidak ditemukan</h2>
            <p>Data yang ingin dibuka tidak tersedia. Silakan kembali ke daftar.</p>
            <a class="btn outline" href="/admin/{{ $base }}">Kembali ke Daftar</a>
        </div>
    </section>
@else
    <section class="card">
        <div class="catalog-heading">
            <div>
                <span class="badge {{ (($row->is_active ?? false) || ($row->status ?? null) === 'active') ? 'badge-active' : 'badge-inactive' }}">
                    {{ isset($row->status) ? ($statusText[$row->status] ?? 'Nonaktif') : ($row->is_active ? 'Aktif' : 'Nonaktif') }}
                </span>
                <h2>{{ $row->name ?? $row->title }}</h2>
                @if($kind === 'skills')
                    <p>Kategori: {{ $row->category }}</p>
                @elseif($kind === 'job_vacancies')
                    <p>{{ $row->company }}{{ $row->location ? ' • '.$row->location : '' }}</p>
                @elseif($kind === 'trainings')
                    <p>{{ $row->provider }}</p>
                @endif
            </div>
            <div class="actions">
                @if($kind!=='trainings')<a class="btn primary" href="/admin/{{ $base }}/form?id={{ $row->id }}">Edit {{ $heading }}</a>@endif
                <a class="btn outline" href="/admin/{{ $base }}">Kembali ke Daftar</a>
            </div>
        </div>

        @if($kind === 'skills')
            @php
                $aliases = json_decode($row->aliases ?? '[]', true);
                $aliases = is_array($aliases) ? $aliases : [];
            @endphp
            <p><strong>Nama lain:</strong> {{ $aliases ? implode(', ', $aliases) : 'Belum ada' }}</p>
            <div class="catalog-toggle-row">
                <div><strong>Status keterampilan</strong><p>Menonaktifkan keterampilan tidak menghapus data atau riwayat.</p></div>
                <form action="{{ route('admin.skills.status', $row->id) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="is_active" value="{{ $row->is_active ? 0 : 1 }}">
                    <button type="submit" role="switch" aria-checked="{{ $row->is_active ? 'true' : 'false' }}" aria-label="{{ $row->is_active ? 'Nonaktifkan' : 'Aktifkan' }} keterampilan" class="ui-switch {{ $row->is_active ? 'is-on' : '' }}" data-confirm="{{ $row->is_active ? 'Nonaktifkan keterampilan ini?' : 'Aktifkan kembali keterampilan ini?' }}"><span></span></button>
                </form>
            </div>
            <form action="{{ route('admin.skills.destroy', $row->id) }}" method="post">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn outline danger-text" data-confirm="Hapus keterampilan ini secara permanen? Penghapusan ditolak jika sudah digunakan oleh data lain.">Hapus Keterampilan</button>
            </form>
        @elseif($kind === 'jobs_taxonomy')
            <div class="catalog-toggle-row">
                <div><strong>Status jabatan</strong><p>Jabatan nonaktif tetap tersimpan dan dapat diaktifkan kembali.</p></div>
                <form action="{{ route('admin.catalog.status', ['jobs_taxonomy', $row->id]) }}" method="post">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="is_active" value="{{ $row->is_active ? 0 : 1 }}">
                    <button type="submit" role="switch" aria-checked="{{ $row->is_active ? 'true' : 'false' }}" aria-label="{{ $row->is_active ? 'Nonaktifkan' : 'Aktifkan' }} jabatan" class="ui-switch {{ $row->is_active ? 'is-on' : '' }}" data-confirm="{{ $row->is_active ? 'Nonaktifkan jabatan ini?' : 'Aktifkan kembali jabatan ini?' }}"><span></span></button>
                </form>
            </div>
        @elseif($kind === 'job_vacancies')
            @if($row->description)
                <p>{{ $row->description }}</p>
            @endif
            <dl class="catalog-meta">
                <div><dt>Sumber</dt><dd>{{ $row->source ?? 'Tidak tersedia' }}</dd></div>
                <div><dt>Tanggal pengambilan</dt><dd>{{ $row->fetched_at ? \Carbon\Carbon::parse($row->fetched_at)->locale('id')->translatedFormat('d F Y') : 'Tidak tersedia' }}</dd></div>
                <div><dt>Berlaku hingga</dt><dd>{{ $row->expires_at ? \Carbon\Carbon::parse($row->expires_at)->locale('id')->translatedFormat('d F Y') : 'Tidak tersedia' }}</dd></div>
            </dl>
            @if($row->source_url)
                <a class="btn outline" href="{{ $row->source_url }}" rel="noopener noreferrer" target="_blank">Buka Sumber Lowongan</a>
            @endif
        @elseif($kind === 'trainings')
            @if($row->description)
                <p>{{ $row->description }}</p>
            @endif
            <dl class="catalog-meta">
                <div><dt>Biaya</dt><dd>Rp {{ number_format($row->cost, 0, ',', '.') }}</dd></div>
                <div><dt>Durasi</dt><dd>{{ $row->duration_hours }} jam</dd></div>
                <div><dt>Mode</dt><dd>{{ ['online' => 'Online', 'offline' => 'Tatap Muka', 'hybrid' => 'Hybrid'][$row->mode] ?? 'Tidak tersedia' }}</dd></div>
            </dl>
            @if($row->source_url)
                <a class="btn outline" href="{{ $row->source_url }}" rel="noopener noreferrer" target="_blank">Buka Sumber Pelatihan</a>
            @endif
        @endif
    </section>

    <section class="card">
        @if($kind === 'jobs_taxonomy')
            <div class="catalog-heading"><h3>Keterampilan yang Dipetakan</h3><a class="btn outline" href="/admin/jabatan/pemetaan?job_taxonomy_id={{ $row->id }}">Kelola Pemetaan</a></div>
            <div class="table-wrap"><table><thead><tr><th>Keterampilan</th><th>Kategori</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                @forelse($relations as $relation)
                    <tr><td>{{ $relation->name }}</td><td>{{ $relation->category }}</td><td>{{ $relation->is_active ? 'Aktif' : 'Nonaktif' }}</td><td><a href="/admin/skill/detail?id={{ $relation->id }}">Lihat Skill</a></td></tr>
                @empty
                    <tr><td colspan="4">Belum ada keterampilan yang dipetakan ke jabatan ini.</td></tr>
                @endforelse
            </tbody></table></div>
        @elseif($kind === 'skills')
            <div class="catalog-heading"><h3>Jabatan yang Menggunakan Keterampilan Ini</h3><a class="btn outline" href="/admin/jabatan/pemetaan">Kelola Pemetaan</a></div>
            <div class="table-wrap"><table><thead><tr><th>Jabatan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                @forelse($relations as $relation)
                    <tr><td>{{ $relation->name }}</td><td>{{ $relation->is_active ? 'Aktif' : 'Nonaktif' }}</td><td><a href="/admin/jabatan/detail?id={{ $relation->id }}">Lihat Jabatan</a></td></tr>
                @empty
                    <tr><td colspan="3">Belum ada jabatan yang dipetakan ke keterampilan ini.</td></tr>
                @endforelse
            </tbody></table></div>
        @elseif($kind === 'job_vacancies')
            <div class="catalog-heading"><h3>Persyaratan Keterampilan</h3><a class="btn outline" href="/admin/lowongan/persyaratan?id={{ $row->id }}">Atur Persyaratan</a></div>
            <div class="table-wrap"><table><thead><tr><th>Keterampilan</th><th>Minimal</th><th>Kepentingan</th><th>Bobot</th></tr></thead><tbody>
                @forelse($relations as $relation)
                    <tr><td>{{ $relation->name }}</td><td>{{ $levelText[$relation->minimum_level] ?? 'Tidak tersedia' }}</td><td>{{ $relation->importance === 'required' ? 'Wajib' : 'Diutamakan' }}</td><td>{{ $relation->weight }}</td></tr>
                @empty
                    <tr><td colspan="4">Belum ada persyaratan keterampilan. Tambahkan persyaratan sebelum menerbitkan lowongan.</td></tr>
                @endforelse
            </tbody></table></div>
        @else
            <div class="catalog-heading"><h3>Keterampilan yang Diajarkan</h3><a class="btn outline" href="/admin/pengajuan">Tinjauan Pengajuan</a></div>
            <div class="table-wrap"><table><thead><tr><th>Keterampilan</th><th>Tingkat Capaian</th></tr></thead><tbody>
                @forelse($relations as $relation)
                    <tr><td>{{ $relation->name }}</td><td>{{ $levelText[$relation->target_level] ?? 'Tidak tersedia' }}</td></tr>
                @empty
                    <tr><td colspan="2">Belum ada keterampilan yang diajarkan.</td></tr>
                @endforelse
            </tbody></table></div>
            <div class="catalog-heading"><h3>Prasyarat Pelatihan</h3><a class="btn outline" href="/admin/pelatihan/prasyarat?id={{ $row->id }}">Atur Prasyarat</a></div>
            @forelse($prerequisites as $prerequisite)
                <p><a href="/admin/pelatihan/detail?id={{ $prerequisite->id }}">{{ $prerequisite->title }}</a></p>
            @empty
                <p>Pelatihan ini tidak mempunyai prasyarat.</p>
            @endforelse
        @endif

        @if(in_array($kind,['job_vacancies','trainings']))
        <div class="note"><h3>Review & Publikasi</h3><p>Periksa metadata, persyaratan/capaian dan prasyarat. Draft tidak muncul kepada pelamar. Untuk pelatihan berlembaga, lembaga harus aktif dan emailnya terverifikasi.</p><form method="post" action="{{ route('admin.catalog.status',[$kind,$row->id]) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $row->status==='active'?0:1 }}"><button class="btn primary" data-confirm="{{ $row->status==='active'?'Nonaktifkan data ini dari katalog publik?':'Terbitkan setelah memeriksa kelengkapan data?' }}">{{ $row->status==='active'?'Nonaktifkan':'Terbitkan' }}</button></form></div>
        @endif
        @if($kind==='skills')
        <h3>Skill terkait dan faktor pencocokan</h3><p>Faktor konfigurasi aktif: {{ $config?->related_factor??0 }}. Sistem memilih kontribusi terbesar antara kemampuan langsung dan skill terkait.</p>
        @php $relatedRows=\Illuminate\Support\Facades\DB::table('skill_relations as r')->join('skills as s','s.id','=','r.related_skill_id')->where('r.skill_id',$row->id)->select('s.name')->get(); @endphp
        @forelse($relatedRows as $related)<span class="badge">{{ $related->name }}</span>@empty<p>Belum ada skill terkait.</p>@endforelse
        @endif
        <h3>Riwayat Perubahan Objek</h3>
        @php $objectEvents=\Illuminate\Support\Facades\DB::table('audit_logs as a')->leftJoin('users as actor','actor.id','=','a.user_id')->where('a.subject_type',$kind)->where('a.subject_id',$row->id)->select('a.*','actor.name as actor_name')->latest('a.created_at')->limit(20)->get(); @endphp
        @forelse(\App\Support\AuditPresenter::entries($objectEvents) as $event)<div class="timeline-item"><strong>{{ $event->label }}</strong><p>{{ $event->actor }} · {{ $event->when }}</p></div>@empty<p>Belum ada perubahan tercatat untuk objek ini. Data lama tidak diberi riwayat rekaan.</p>@endforelse
    </section>
@endif
