@include('components.data-filters', ['searchHint'=>'Cari pelaku, tindakan, atau objek'])
<section class="card">
    <div class="catalog-heading">
        <div>
            <h2>Riwayat Aktivitas Admin</h2>
            <p>Catatan perubahan data dan pengaturan yang dilakukan melalui sistem.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="audit-table">
            <thead><tr><th>Waktu</th><th>Admin</th><th>Aktivitas</th><th>Data Terkait</th><th>Keterangan</th></tr></thead>
            <tbody>
            @forelse($records as $activity)
                <tr>
                    <td>{{ $activity->when }}</td>
                    <td><strong>{{ $activity->actor }}</strong></td>
                    <td>{{ $activity->label }}</td>
                    <td>{{ $activity->subject }}</td>
                    <td>{{ $activity->description ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty">Belum ada aktivitas yang tercatat.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="catalog-hint">Catatan aktivitas disimpan untuk penelusuran perubahan. Waktu ditampilkan dalam format yang mudah dibaca.</p>
</section>
