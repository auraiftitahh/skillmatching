<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <div class="skill-options">
        @forelse ($records as $record)
            <article class="card">
                <span class="badge">{{ ['pending' => 'Menunggu pemeriksaan', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'][$record->status] }}</span>
                <h2>{{ $record->title }}</h2>
                <p>{{ $record->applicant }} · {{ $record->skill }} · Level {{ $record->level }}</p>
                <a class="button button-outline" href="{{ route('institution.evidence.download', $record->id) }}">Unduh dokumen</a>
                @if ($record->status === 'pending')
                    <form class="form-grid" method="post" action="{{ route('institution.evidence.decide', $record->id) }}">
                        @csrf
                        <label>Catatan keputusan <textarea name="reason" minlength="5" maxlength="2000" required></textarea></label>
                        <div class="actions">
                            <button class="button button-primary" name="decision" value="verified">Setujui bukti</button>
                            <button class="button button-outline" name="decision" value="rejected">Tolak bukti</button>
                        </div>
                    </form>
                @else
                    <p>{{ $record->reason }}</p>
                @endif
            </article>
        @empty
            <section class="card small-empty"><h2>Belum ada bukti yang diajukan ke lembaga Anda</h2></section>
        @endforelse
    </div>
    {{ $records->links() }}
</x-layouts.workspace>
