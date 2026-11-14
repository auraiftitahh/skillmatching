@php $e=$participants->firstWhere('id',(int)request('id')); 
@endphp
@if($e)<section class="card narrow"><h2>{{ $e->user_name }}</h2><p>Pelatihan: {{ $e->training_title }}</p><h3>Capaian pelatihan yang diproses</h3>@foreach(\App\Support\PageExperience::enrollmentOutcomes($e) as $o)<p>{{ $o->name }} · {{ $levelText[$o->target_level] }}</p>@endforeach<span class="badge">{{ $statusText[$e->status]??$e->status }}</span><p>Konfirmasi hanya boleh dilakukan setelah lembaga memeriksa kelulusan menurut prosedur eksternal.</p>
@if($e->status==='waiting_confirmation' && $page==='pelatihan/peserta/detail')<a class="btn primary" href="/sertifikasi/pelatihan/kelulusan?id={{ $e->id }}">Tinjau Konfirmasi Kelulusan</a>
@elseif($e->status==='waiting_confirmation')<form class="editor-grid" method="post" action="{{ route('graduations.confirm',$e->id) }}">
@csrf<button class="btn primary" data-confirm="Konfirmasi bahwa peserta ini dinyatakan lulus?">Konfirmasi Kelulusan</button></form>
@else<div class="note">Tidak tersedia tindakan konfirmasi untuk status ini.</div>
@endif</section>
@else<div class="empty">Peserta tidak berada dalam kewenangan lembaga atau tidak ditemukan. <a href="/sertifikasi/pelatihan/peserta">Kembali</a></div>
@endif
