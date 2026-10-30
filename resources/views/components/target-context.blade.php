@props(['job'=>null])
@if($job)<section class="card target-context"><div><span class="form-hint">Posisi tujuan Anda</span><h2>{{ $job->title }}</h2><p>{{ $job->company }}</p></div><a class="button button-outline" href="{{ route('applicant.matching.show',$job->id) }}">Kembali ke kecocokan posisi</a></section>@endif
