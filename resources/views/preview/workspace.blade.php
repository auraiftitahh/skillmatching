<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="request()->route('page') === 'dashboard' ? route('preview.index') : $homeUrl" :preview="true">
    <section class="card empty-state" aria-labelledby="placeholder-title">
        <span class="empty-icon"><x-icon name="spark" /></span>
        <span class="badge">{{ $roleLabel }}</span>
        <h2 id="placeholder-title">{{ $pageTitle }}</h2>
        <p>Anda sedang berada di halaman {{ $pageTitle }}.<br>Fitur halaman ini akan tersedia pada tahap pengembangan berikutnya.</p>
        <a class="button button-outline" href="{{ route('preview.index') }}">Pilih tampilan lain</a>
    </section>
</x-layouts.workspace>
