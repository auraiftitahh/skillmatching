<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <p>Konfirmasi kelulusan setelah memeriksa penyelesaian pelatihan peserta.</p>
    <div class="skill-options">
        @forelse ($participants as $participant)
            <article class="card">
                <span class="badge">{{ ['in_progress' => 'Sedang diikuti', 'waiting_confirmation' => 'Menunggu konfirmasi', 'completed' => 'Selesai'][$participant->status] }}</span>
                <h2>{{ $participant->name }}</h2>
                <p>{{ $participant->title }} · Versi {{ $participant->training_version }}</p>
                @if ($participant->status === 'waiting_confirmation')
                    <form method="post" action="{{ route('institution.participants.confirm', $participant->id) }}">
                        @csrf
                        <button class="button button-primary">Konfirmasi kelulusan</button>
                    </form>
                @endif
            </article>
        @empty
            <section class="card small-empty">
                <h2>Belum ada peserta pelatihan</h2>
                <p>Peserta muncul setelah mulai mengikuti pelatihan lembaga Anda.</p>
            </section>
        @endforelse
    </div>
    {{ $participants->links() }}
</x-layouts.workspace>
