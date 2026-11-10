<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <div class="actions">
        <a class="button button-primary" href="{{ route('admin.evaluation.setup') }}">Profil uji dan penilaian ahli</a>
        <a class="button button-outline" href="{{ route('admin.evaluation.feedback') }}">Tinjau umpan balik</a>
    </div>
    <form method="get" action="{{ route('admin.evaluation.report') }}" class="card form-grid">
        <label class="field">Dari tanggal (WIB) <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label class="field">Sampai tanggal (WIB) <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <label class="field">Versi bobot
            <select name="version">
                <option value="">Semua versi</option>
                @foreach ($versions as $version)
                    <option value="{{ $version->id }}" @selected((string) ($filters['version'] ?? '') === (string) $version->id)>Versi {{ $version->version }}</option>
                @endforeach
            </select>
        </label>
        <div class="actions">
            <button class="button button-primary">Terapkan</button>
            <a class="button button-outline" href="{{ route('admin.evaluation.report') }}">Bersihkan filter</a>
        </div>
    </form>
    <section class="card">
        <h2>{{ $totals->total }} penilaian</h2>
        @if ($totals->total > 0)
            <p>Rata-rata skor sistem {{ number_format($totals->system_average, 2, ',', '.') }}/100 · Rata-rata penilaian ahli {{ number_format($totals->expert_average, 2, ',', '.') }}/4.</p>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Profil</th><th>Posisi</th><th>Skor sistem</th><th>Penilaian ahli</th><th>Versi</th><th>Tanggal</th></tr></thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr><td>{{ $record->code }} @if ($record->group) · {{ $record->group }} @endif</td><td>{{ $record->job_title }} · {{ $record->company }}</td><td>{{ $record->system_score }}/100</td><td>{{ $record->expert_rating }}/4</td><td>{{ $record->version }}</td><td>{{ \Illuminate\Support\Carbon::parse($record->created_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $records->links() }}
        @else
            <p>Belum ada penilaian yang sesuai dengan filter.</p>
        @endif
    </section>
</x-layouts.workspace>
