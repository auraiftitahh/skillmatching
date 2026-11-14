@php $training=$trainings->firstWhere('id',(int)request('id')); 
@endphp
@if($training)<section class="card narrow"><h2>Prasyarat untuk {{ $training->title }}</h2><form method="post" action="{{ route('training.prerequisite',$training->id) }}">
@csrf<label>Pelatihan prasyarat<select name="prerequisite_id">
@foreach($trainings as $t)
@if($t->id!==$training->id)<option value="{{ $t->id }}">{{ $t->title }}</option>
@endif 
@endforeach</select></label><button class="btn primary">Tambah Prasyarat</button></form></section>
<section class="card"><h2>Prasyarat Tersimpan</h2>
@php $rows=\Illuminate\Support\Facades\DB::table('training_prerequisites as p')->join('trainings as t','t.id','=','p.prerequisite_id')->where('p.training_id',$training->id)->select('t.title','p.id')->get(); 
@endphp 
@forelse($rows as $row)<div class="subcard">{{ $row->title }}<form method="post" action="{{ route('admin.relation.remove',['training-prerequisite',$row->id]) }}">
@csrf @method('DELETE')<button class="btn danger" data-confirm="Hapus prasyarat?">Hapus</button></form></div>
@empty<div class="empty">Tidak ada prasyarat.</div>
@endforelse<div class="actions"><a class="btn outline" href="/admin/pelatihan/detail?id={{ request('id') }}">Tinjau & Publikasi →</a></div></section>
@else<div class="empty">Pilih pelatihan terlebih dahulu.</div>
@endif
