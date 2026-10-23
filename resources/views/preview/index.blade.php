<x-layouts.base title="Pratinjau Antarmuka">
    <main id="main-content" tabindex="-1" class="preview-hub">
        <a class="brand" href="{{ url('/') }}"><span class="brand-mark"><x-icon name="spark" /></span><span>skillmatching</span></a>
        <div class="hub-heading"><p class="eyebrow">Pratinjau lokal · SCRUM-71</p><h1>Satu sistem, tiga ruang kerja.</h1><p>Periksa layout, navigasi, dan tampilan seluler. Fitur operasional akan ditambahkan bertahap.</p></div>
        <div class="role-grid">
            @foreach (['pelamar' => ['Pelamar', 'Kenali kemampuan dan siapkan langkah menuju pekerjaan formal.', 'user'], 'admin' => ['Admin', 'Kelola akun, tinjau pengajuan, dan pantau layanan platform.', 'settings'], 'sertifikasi' => ['Badan Sertifikasi', 'Kelola layanan pelatihan dan dukung pengembangan kompetensi.', 'badge']] as $role => [$label, $description, $icon])
                <a class="role-card" href="{{ route('preview.workspace', ['role' => $role, 'page' => 'dashboard']) }}"><span class="role-card-icon"><x-icon :name="$icon" /></span><h2>{{ $label }}</h2><p>{{ $description }}</p><span class="text-link">Buka pratinjau →</span></a>
            @endforeach
        </div>
        <section class="card hub-tools"><div><h2>Tampilan pendukung</h2><p>Form autentikasi serta contoh tabel, tombol, dan pesan.</p></div><div class="actions"><a class="button button-outline" href="{{ route('preview.auth', 'login') }}">Autentikasi</a><a class="button button-outline" href="{{ route('preview.components') }}">Komponen UI</a></div></section>
    </main>
</x-layouts.base>
