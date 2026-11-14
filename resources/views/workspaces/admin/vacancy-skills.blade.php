@php $job=$allJobs->firstWhere('id',request()->integer('id')); @endphp
@if($job)<div class="context-heading"><h2>{{ $job->title }}</h2><p>Pilih keterampilan, atur levelnya, lalu simpan perubahan bersama.</p></div>
@include('components.bulk-skills',['bulkKind'=>'vacancy','bulkId'=>$job->id])
<div class="actions"><a class="btn outline" href="/admin/lowongan/detail?id={{ $job->id }}">Tinjau Lowongan →</a></div>
@else<div class="empty">Pilih lowongan dari daftar terlebih dahulu.</div>@endif
