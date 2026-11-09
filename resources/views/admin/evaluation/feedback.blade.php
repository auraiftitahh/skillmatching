<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <div class="actions"><a class="button button-outline" href="{{ route('admin.evaluation.setup') }}">Profil uji dan penilaian ahli</a></div>
    <form method="get" class="card form-grid">
        <label class="field">Penilaian
            <select name="rating">
                <option value="">Semua penilaian</option>
                <option value="relevant" @selected($rating === 'relevant')>Relevan</option>
                <option value="not_relevant" @selected($rating === 'not_relevant')>Kurang relevan</option>
            </select>
        </label>
        <button class="button button-primary">Terapkan</button>
    </form>
    <div class="skill-options">
        @forelse ($records as $record)
            <article class="card">
                <span class="badge">{{ $record->rating === 'relevant' ? 'Relevan' : 'Kurang relevan' }}</span>
                <h2>{{ $record->title }}</h2>
                <p>{{ $record->company }} · Versi bobot {{ $record->version }}</p>
                @if ($record->comment)<p class="preserve-lines">{{ $record->comment }}</p>@endif
            </article>
        @empty
            <section class="card small-empty"><h2>Belum ada umpan balik pada pilihan ini</h2></section>
        @endforelse
    </div>
    {{ $records->links() }}
</x-layouts.workspace>
