@include('components.data-filters', ['searchHint'=>'Cari peserta atau pelatihan', 'filterOptions'=>[['name'=>'status','label'=>'Status','options'=>[''=>'Semua status','planned'=>'Direncanakan','in_progress'=>'Sedang belajar','waiting_confirmation'=>'Menunggu konfirmasi','completed'=>'Selesai','rejected'=>'Ditolak']]]])
<section class="card"><div class="catalog-heading">
@if(request()->filled('training_id'))
<a class="btn outline" href="/sertifikasi/pelatihan/peserta">Lihat semua peserta</a>
@endif
</div><div class="table-wrap"><table><thead><tr><th>Peserta</th><th>Pelatihan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@forelse($participants as $e)<tr><td>{{ $e->user_name }}</td><td>{{ $e->training_title }}</td><td><span class="badge">{{ $statusText[$e->status]??$e->status }}</span></td><td><a href="/sertifikasi/pelatihan/peserta/detail?id={{ $e->id }}">Tinjau</a></td></tr>
@empty<tr><td colspan="4">Tidak ada peserta untuk filter ini.</td></tr>
@endforelse</tbody></table></div></section>
