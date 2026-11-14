@if($page==='pelatihan/selesaikan')
@include('workspaces.pelamar.training-completion')
@else
@include('components.data-filters', ['searchHint'=>'Cari nama pelatihan', 'filterOptions'=>[['name'=>'status','label'=>'Status','options'=>[''=>'Semua status','planned'=>'Direncanakan','in_progress'=>'Sedang belajar','waiting_confirmation'=>'Menunggu konfirmasi','completed'=>'Selesai']]]])
<div class="list">
@forelse($enrollments as $e)<section class="card"><span class="badge">{{ $statusText[$e->status]??$e->status }}</span><h3>{{ $e->training_title }}</h3><p>Pelatihan dilakukan di luar aplikasi. Konfirmasi hanya setelah benar-benar selesai. Pelatihan berlembaga menunggu keputusan lembaga; pelatihan mandiri mencatat penyelesaian sendiri dan kemampuan berstatus belum terverifikasi.</p>
@if(in_array($e->status,['planned','in_progress']) && \App\Support\Publication::trainings()->whereKey($e->training_id)->exists())<form method="post" action="{{ route('enrollments.status',$e->id) }}">
@csrf<input type="hidden" name="status" value="{{ $e->status==='planned'?'in_progress':'planned' }}"><button class="btn outline">{{ $e->status==='planned'?'Mulai Belajar':'Kembali Direncanakan' }}</button></form><a class="btn primary" href="/pelamar/pelatihan/selesaikan?training_id={{ $e->training_id }}">Konfirmasi Penyelesaian</a>
@elseif(in_array($e->status,['planned','in_progress']))<div class="note">Pelatihan atau lembaga tidak tersedia. Riwayat tetap disimpan; tindakan baru dibatasi.</div>@endif</section>
@empty<div class="empty">Belum ada pelatihan dalam rencana belajar. <a href="/pelamar/pelatihan">Cari Pelatihan</a></div>
@endforelse</div>

@endif
