<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <section class="card">
        <h2>Tambahkan bukti dari luar katalog</h2>
        <p>Pilih lembaga penerbit untuk mengirim bukti, atau masukkan nama penerbit untuk menyimpannya secara pribadi.</p>
        <form method="post" action="{{ route('applicant.evidence.store') }}" enctype="multipart/form-data" class="form-grid">
            @csrf
            @include('applicant.evidence-fields', ['record' => null])
            <button class="button button-primary">Simpan bukti</button>
        </form>
    </section>
    <div class="skill-options">
        @forelse ($records as $record)
            <article class="card">
                <span class="badge">{{ ['pending' => 'Menunggu pemeriksaan', 'unverified' => 'Pribadi', 'cancelled' => 'Ditarik', 'verified' => 'Terverifikasi', 'rejected' => 'Ditolak'][$record->status] }}</span>
                <h2>{{ $record->title }}</h2>
                <p>{{ $record->issuer_name }} · {{ $record->skill }} · Level {{ $record->level }}</p>
                @if ($record->reason)<p>{{ $record->reason }}</p>@endif
                <a class="button button-outline" href="{{ route('applicant.evidence.download', $record->id) }}">Unduh dokumen</a>
            </article>
        @empty
            <section class="card small-empty"><h2>Belum ada bukti tersimpan</h2></section>
        @endforelse
    </div>
    {{ $records->links() }}
</x-layouts.workspace>
