<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <p>Skor dan kebutuhan keterampilan dicatat saat rekomendasi disimpan.</p>
    @forelse ($snapshots as $snapshot)
        <section class="card">
            <h2>{{ \Illuminate\Support\Carbon::parse($snapshot->created_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</h2>
            @foreach (json_decode($snapshot->results, true, flags: JSON_THROW_ON_ERROR) as $result)
                <details class="status-history">
                    <summary>{{ $result['job'] }} · {{ $result['company'] }} · {{ $result['display_score'] }}/100</summary>
                    <p>Konfigurasi versi {{ $result['configuration_version'] }}</p>
                    <ul>
                        @foreach ($result['items'] as $item)
                            <li>{{ $item['skill'] }} · Level Anda {{ $item['own'] }} · Kebutuhan {{ $item['target'] }}</li>
                        @endforeach
                    </ul>
                </details>
            @endforeach
        </section>
    @empty
        <section class="card small-empty"><h2>Belum ada rekomendasi tersimpan</h2><a href="{{ route('applicant.matching.index') }}">Buka rekomendasi</a></section>
    @endforelse
    {{ $snapshots->links() }}
</x-layouts.workspace>
