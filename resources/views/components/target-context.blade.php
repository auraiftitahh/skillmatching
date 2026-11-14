<section class="context-banner" aria-label="Konteks perhitungan"><div><span class="eyebrow">LOWONGAN TARGET</span>
@if($contextJobs->isNotEmpty())<strong>{{ $contextJobs->pluck('title')->join(' · ') }}</strong><p>Analisis memakai {{ $contextJobs->count() }} lowongan ini. <a href="/pelamar/lowongan/kesenjangan?{{ http_build_query($contextQuery) }}">Ubah target</a></p>
@else<strong>Belum dipilih</strong><p>Pilih lowongan untuk memperoleh analisis dan cakupan gap personal.</p>@endif</div>
@if($contextJobs->isEmpty())<a href="/pelamar/lowongan/kesenjangan" class="btn outline">Pilih lowongan</a>@endif</section>
