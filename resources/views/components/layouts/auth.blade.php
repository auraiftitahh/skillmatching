@props(['title'])
<x-layouts.base :title="$title">
    <div class="auth-page">
        <header class="auth-header"><a class="brand" href="{{ url('/') }}"><span class="brand-mark"><x-icon name="spark" /></span><span>skillmatching</span></a></header>
        <main id="main-content" tabindex="-1" class="auth-grid">
            <section class="auth-story" aria-labelledby="auth-story-title">
                <span class="badge">Langkah baru untuk karier Anda</span>
                <h2 id="auth-story-title">Kenali kemampuan.<br>Temukan peluang.<br>Bangun masa depan.</h2>
                <p>Siapkan langkah kembali ke dunia kerja melalui pemetaan keterampilan dan pelatihan yang relevan.</p>
                <div class="auth-decoration" aria-hidden="true"><x-icon name="spark" /></div>
                <p class="auth-story-bottom">Keterampilan hari ini, peluang esok hari.</p>
            </section>
            <section class="auth-panel" aria-label="{{ $title }}">{{ $slot }}</section>
        </main>
        <footer class="auth-footer">© {{ date('Y') }} Skill Matching</footer>
    </div>
</x-layouts.base>
