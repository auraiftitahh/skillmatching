@php $app=$applications->firstWhere('id',(int)request('id')); 
@endphp
<section class="card narrow"><h2>{{ $app?'Perbarui':'Buat' }} Catatan Lamaran</h2><form class="editor-grid" data-dirty-guard action="{{ route('applications.save') }}" method="post">
@csrf
@if($app)<input name="id" type="hidden" value="{{ $app->id }}">
@endif
<label>Lowongan yang tersimpan (opsional)<select name="job_vacancy_id"><option value="">Lamaran dari sumber lain</option>
@foreach($allVacancies as $j)<option value="{{ $j->id }}" @selected(old('job_vacancy_id',$app->job_vacancy_id??null)==$j->id)>{{ $j->title }}</option>
@endforeach</select></label>
<label>Posisi pekerjaan<input required name="job_title" value="{{ old('job_title',$app->job_title??'') }}"></label><label>Perusahaan<input name="company" value="{{ old('company',$app->company??'') }}"></label>
<label>Status<select name="status">
@foreach(['saved'=>'Disimpan','applied'=>'Sudah Dilamar','processing'=>'Diproses','interview'=>'Wawancara','accepted'=>'Diterima','rejected'=>'Tidak Lanjut'] as $s=>$label)<option value="{{ $s }}" @selected(old('status',$app->status??'saved')===$s)>{{ $label }}</option>
@endforeach</select></label>
<label>Tanggal melamar<input type="date" name="applied_at" value="{{ old('applied_at',$app->applied_at??'') }}"></label>
<label>Catatan<textarea name="note">{{ old('note',$app->note??'') }}</textarea></label>
<div class="actions"><button class="btn primary">Simpan Catatan</button><a class="btn outline" href="/pelamar/lamaran">Batal</a></div></form></section>
