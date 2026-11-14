@include('components.data-filters', ['searchHint'=>'Cari tanggal hasil rekomendasi'])
<div class="list">
@forelse($recommendations as $h)<section class="card"><span class="badge">Hasil Tersimpan</span><h3>{{ \Carbon\Carbon::parse($h->created_at)->locale('id')->translatedFormat('d F Y, H.i') }}</h3><small>Versi aturan: {{ $configVersions[$h->weight_configuration_id] ?? 'Riwayat lama' }}</small><details><summary>Hasil tersimpan (tidak berubah)</summary><div class="table-wrap"><table><thead><tr><th>Lowongan</th><th>Skor saat dihitung</th></tr></thead><tbody>
@foreach(json_decode($h->results,true)??[] as $row)<tr><td>{{ $row['job']??'-' }}</td><td>{{ $row['display_score']??0 }}/100</td></tr>
@endforeach</tbody></table></div></details></section>
@empty<div class="empty">Belum ada riwayat. Gunakan tombol Simpan Riwayat pada rekomendasi lowongan.</div>
@endforelse</div>
