<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <section class="card">
        <h2>Aktivitas layanan lembaga</h2>
        @forelse ($events as $event)
            <article class="status-history">
                <h3>{{ ['review' => 'Tinjauan pengajuan', 'graduation' => 'Konfirmasi kelulusan', 'evidence' => 'Keputusan bukti'][$event->kind] }}</h3>
                <span class="badge">{{ ['approved' => 'Disetujui', 'revision' => 'Perlu perbaikan', 'rejected' => 'Ditolak', 'completed' => 'Selesai', 'verified' => 'Terverifikasi'][$event->status] ?? 'Diperbarui' }}</span>
                <p>{{ \Illuminate\Support\Carbon::parse($event->occurred_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>
                @if ($event->note)<p class="preserve-lines">{{ $event->note }}</p>@endif
                <a href="{{ match ($event->kind) {
                    'review' => route('institution.submissions.show', $event->record_id),
                    'graduation' => route('institution.participants.index'),
                    'evidence' => route('institution.evidence.index'),
                } }}">Lihat layanan</a>
            </article>
        @empty
            <p>Belum ada keputusan atau kelulusan yang tercatat.</p>
        @endforelse
        {{ $events->links() }}
    </section>
</x-layouts.workspace>
