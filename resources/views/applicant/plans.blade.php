<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
<x-target-context :job="$targetJob"/>
<p>Susun hingga tiga rencana aktif. Menyimpan rencana tidak mendaftarkan Anda ke penyelenggara dan tidak mengubah level keterampilan.</p><div class="actions"><a class="button button-primary" href="{{ route('applicant.trainings.index',['job_id'=>$targetJob?->id]) }}">Pilih pelatihan</a></div>
<div class="skill-options">@forelse($records as $record)
@php($training=$trainings->get($record->training_id))
<article class="card"><span class="badge">{{ ['planned'=>'Direncanakan','in_progress'=>'Sedang diikuti','waiting_confirmation'=>'Menunggu konfirmasi','completed'=>'Selesai','rejected'=>'Belum disetujui'][$record->status]??'Riwayat pelatihan' }}</span><h2>{{ $training?->title??'Pelatihan tidak tersedia' }}</h2><p>Capaian tersimpan dari versi {{ $record->training_version }} saat rencana dibuat.</p>
@if(in_array($record->training_id,$available))
<a class="button button-outline" href="{{ route('applicant.trainings.show',['training'=>$record->training_id,'job_id'=>$targetJob?->id]) }}">Lihat detail dan urutan prasyarat</a><a class="button button-outline" href="{{ route('applicant.simulations.create',['training_id'=>$record->training_id,'job_id'=>$targetJob?->id]) }}">Simulasikan pelatihan ini</a>
@if(!\App\Support\Publication::prerequisitesMet(auth()->id(),$record->training_id))<p class="form-hint">Selesaikan prasyarat pada urutan belajar lebih dahulu. Menambahkan prasyarat ke rencana belum berarti lulus.</p>@endif
@else<p class="notice notice-warning">Pelatihan saat ini tidak tersedia. Rencana dan capaian yang tersimpan tetap dipertahankan.</p>@endif
@if($record->status==='planned')<form method="post" action="{{ route('applicant.plans.destroy',$record->id) }}">@csrf
@method('DELETE')<button class="button button-outline">Hapus rencana ini</button></form>@endif
</article>@empty<section class="card small-empty"><h2>Belum ada rencana belajar</h2><p>Pilih pelatihan yang mendukung posisi tujuan Anda.</p></section>@endforelse</div>
</x-layouts.workspace>
