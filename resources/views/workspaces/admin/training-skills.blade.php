@php $training=$trainings->firstWhere('id',request()->integer('id')); @endphp
@if($training)<div class="context-heading"><h2>{{ $training->title }}</h2><p>Pilih keterampilan, atur levelnya, lalu simpan perubahan bersama.</p></div>
@include('components.bulk-skills',['bulkKind'=>'training','bulkId'=>$training->id])
<div class="actions"><a class="btn outline" href="/admin/pelatihan/prasyarat?id={{ $training->id }}">Lanjut ke Prasyarat →</a></div>
@else<div class="empty">Pilih pelatihan dari daftar terlebih dahulu.</div>@endif
