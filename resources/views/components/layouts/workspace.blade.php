@props(['pageTitle', 'roleLabel', 'navigation', 'homeUrl', 'backUrl' => null, 'preview' => false])
<x-layouts.base :title="$pageTitle">
    <div class="app-shell">
        <aside class="sidebar" aria-label="Panel navigasi">
            <a class="brand" href="{{ $homeUrl }}"><span class="brand-mark"><x-icon name="spark" /></span><span>skillmatching<small>Ruang karier Anda</small></span></a>
            <p class="nav-heading">{{ $roleLabel }}</p>
            <x-navigation :items="$navigation" />
            @if ($preview)
                <div class="sidebar-bottom"><span class="badge">Pratinjau lokal</span><a href="{{ route('preview.index') }}">Pilih tampilan lain</a></div>
            @endif
        </aside>
        <div class="workspace">
            <header class="topbar">
                <span class="topbar-title">Ruang {{ $roleLabel }}</span>
                <span class="role-chip"><span class="avatar">{{ mb_substr($roleLabel, 0, 1) }}</span>{{ $roleLabel }}</span>
                <details class="mobile-navigation">
                    <summary><x-icon name="menu" /><span>Menu navigasi</span></summary>
                    <x-navigation :items="$navigation" label="Navigasi seluler" />
                    @if ($preview)<a class="mobile-preview-link" href="{{ route('preview.index') }}">Pilih tampilan lain</a>@endif
                </details>
            </header>
            <main id="main-content" tabindex="-1" class="main-content">
                <div class="page-heading">
                    <div><p class="eyebrow">{{ $roleLabel }}</p><h1>{{ $pageTitle }}</h1></div>
                    @if ($backUrl)<a class="button button-outline" href="{{ $backUrl }}"><x-icon name="arrow" />Kembali</a>@endif
                </div>
                @if ($preview)<x-alert>Pratinjau antarmuka. Navigasi ini belum menggunakan akun atau data operasional.</x-alert>@endif
                @if (session('success'))<x-alert type="success">{{ session('success') }}</x-alert>@endif
                @if ($errors->any())
                    <x-alert type="error"><p>Periksa kembali isian Anda.</p><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-alert>
                @endif
                {{ $slot }}
            </main>
            <footer class="app-footer"><span>© {{ date('Y') }} Skill Matching</span><span>Keterampilan hari ini, peluang esok hari.</span></footer>
        </div>
    </div>
</x-layouts.base>
