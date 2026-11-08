<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <section class="card">
        <h2>Unggah CSV lowongan</h2>
        <p>Gunakan CSV dengan pemisah koma, maksimal 3 MB dan 1.000 baris data. Lowongan yang lolos disimpan sebagai draf setelah dikonfirmasi.</p>
        <p><code>{{ implode(',', $columns) }}</code></p>
        <p>Kolom <code>source_name</code> dan <code>expires_at</code> opsional. Format tanggal YYYY-MM-DD. Isi <code>employment_type</code> dengan <code>permanent</code> atau <code>contract</code>.</p>
        <form method="post" action="{{ route('admin.jobs.import.preview') }}" enctype="multipart/form-data">
            @csrf
            <label class="field">File CSV<input type="file" name="file" accept=".csv,text/csv" required></label>
            @error('file')<p class="field-error">{{ $message }}</p>@enderror
            <button class="button button-primary">Lihat pratinjau</button>
        </form>
    </section>
    @if(session('import_skipped'))
        <p>Baris dilewati: {{ implode(', ', session('import_skipped')) }}.</p>
    @endif
    @if($preview && $preview['owner'] === auth()->id() && $preview['expires'] > now()->timestamp)
        <section class="card">
            <h2>Pratinjau impor</h2>
            <div style="overflow-x: auto">
                <table>
                    <thead><tr><th scope="col">Baris</th><th scope="col">Lowongan</th><th scope="col">Hasil pemeriksaan</th></tr></thead>
                    <tbody>
                        @foreach($preview['rows'] as $row)
                            <tr><td>{{ $row['line'] }}</td><td>{{ $row['title'] ?: 'Judul kosong' }}</td><td>{{ $row['errors'] ? implode(' ', $row['errors']) : 'Siap disimpan' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="actions">
                <form method="post" action="{{ route('admin.jobs.import.confirm') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ $preview['token'] }}">
                    <button class="button button-primary">Simpan baris valid</button>
                </form>
                <form method="post" action="{{ route('admin.jobs.import.cancel') }}">
                    @csrf
                    @method('DELETE')
                    <button class="button button-outline">Batalkan pratinjau</button>
                </form>
            </div>
        </section>
    @endif
</x-layouts.workspace>
