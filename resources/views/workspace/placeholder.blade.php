<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <section class="card empty-state" aria-labelledby="placeholder-title">
        <span class="empty-icon"><x-icon name="spark" /></span><span class="badge">{{ $roleLabel }}</span>
        <h2 id="placeholder-title">{{ $pageTitle }}</h2>
        <p>Anda sedang berada di halaman {{ $pageTitle }}.<br>Fitur halaman ini akan tersedia pada tahap pengembangan berikutnya.</p>
    </section>
</x-layouts.workspace>
