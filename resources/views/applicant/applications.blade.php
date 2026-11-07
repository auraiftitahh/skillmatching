<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <p>Gunakan catatan pribadi ini untuk melacak perkembangan lamaran yang Anda kirim ke perusahaan.</p>
    <section class="card">
        <h2>Tambah catatan</h2>
        <form method="post" action="{{ route('applicant.applications.store') }}" class="form-grid">
            @csrf
            @include('applicant.application-fields', ['record' => null])
            <button class="button button-primary">Simpan catatan</button>
        </form>
    </section>
    <div class="skill-options">
        @forelse ($records as $record)
            <article class="card">
                <span class="badge">{{ $statuses[$record->status] }}</span>
                <h2>{{ $record->job_title }}</h2>
                <p>{{ $record->company }} @if ($record->applied_at) · {{ $record->applied_at }} @endif</p>
                <details>
                    <summary>Ubah catatan</summary>
                    <form method="post" action="{{ route('applicant.applications.update', $record->id) }}" class="form-grid">
                        @csrf
                        @method('PATCH')
                        @include('applicant.application-fields', ['record' => $record])
                        <button class="button button-primary">Simpan perubahan</button>
                    </form>
                </details>
                <details>
                    <summary>Riwayat perkembangan</summary>
                    @foreach ($events[$record->id] ?? [] as $event)
                        <p>{{ \Illuminate\Support\Carbon::parse($event->created_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · {{ $statuses[$event->status] }} {{ $event->note }}</p>
                    @endforeach
                </details>
            </article>
        @empty
            <section class="card small-empty"><h2>Belum ada catatan lamaran</h2></section>
        @endforelse
    </div>
    {{ $records->links() }}
</x-layouts.workspace>
