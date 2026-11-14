@php $suggestions=\Illuminate\Support\Facades\DB::table('user_experiences as e')->join('job_skills_mapping as m','m.job_taxonomy_id','=','e.job_taxonomy_id')->join('skills as s','s.id','=','m.skill_id')->where('s.is_active',true)->where('e.user_id',$u->id)->distinct()->select('s.id','s.name')->orderBy('s.name')->get(); 
@endphp
<section class="card"><h2>Saran berdasarkan riwayat Anda</h2><p>Saran berasal dari pemetaan jabatan ke skill. Tidak otomatis masuk profil sebelum Anda mengonfirmasi tingkat kemampuan.</p>
@if($suggestions->isNotEmpty())<div class="chips">
@foreach($suggestions as $suggestion)<span class="badge">{{ $suggestion->name }}</span>
@endforeach</div>
@else<div class="empty">Saran otomatis belum tersedia. Pilih jabatan taksonomi pada riwayat, atau tambahkan skill secara manual.</div>
@endif
<a class="btn primary" href="/pelamar/skill/konfirmasi">Konfirmasi Skill dan Level →</a></section>
