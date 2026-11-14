@php
$kind=str_starts_with($page,'skill')?'skills':(str_starts_with($page,'jabatan')?'jobs_taxonomy':(str_starts_with($page,'lowongan')?'job_vacancies':'trainings'));
$record=request('id')?\Illuminate\Support\Facades\DB::table($kind)->where('id',request('id'))->first():null;
$base=match($kind){'skills'=>'skill','jobs_taxonomy'=>'jabatan','job_vacancies'=>'lowongan',default=>'pelatihan'};
@endphp
<section class="card narrow">
@if(in_array($kind,['job_vacancies','trainings']))<div class="note">Data baru disimpan sebagai <b>Draft</b>. Tambahkan skill terlebih dahulu sebelum menerbitkan.</div>
@endif
<form class="editor-grid" data-dirty-guard method="post" action="{{ route('catalog.save',$kind) }}">
@csrf
@if($record)<input type="hidden" name="id" value="{{ $record->id }}">
@endif
@if($kind==='skills')
<label>Nama skill<input name="name" required value="{{ old('name',$record->name??'') }}"></label><label>Kategori<input name="category" required value="{{ old('category',$record->category??'Umum') }}"></label><label>Alias (pisahkan koma)<input name="aliases" value="{{ old('aliases',$record?implode(', ',json_decode($record->aliases??'[]',true)??[]):'') }}"></label>
<div class="catalog-toggle-row"><strong>Keterampilan aktif</strong><label class="switch-label"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="toggle-checkbox" @checked((bool)old('is_active',$record->is_active??1))><span class="toggle-track" aria-hidden="true"></span><span class="sr-only">Aktifkan keterampilan</span></label></div><p class="soft-note">Keterampilan yang dinonaktifkan tidak hilang dari daftar dan masih dapat diaktifkan kembali.</p>
@elseif($kind==='jobs_taxonomy')<label>Nama jabatan<input name="name" required value="{{ old('name',$record->name??'') }}"></label><div class="catalog-toggle-row"><strong>Jabatan aktif</strong><label class="switch-label"><input type="hidden" name="is_active" value="0"><input type="checkbox" class="toggle-checkbox" name="is_active" value="1" @checked((bool)old('is_active',$record->is_active??1))><span class="toggle-track" aria-hidden="true"></span><span class="sr-only">Aktifkan jabatan</span></label></div>
@elseif($kind==='job_vacancies')
<label>Judul lowongan<input name="title" required value="{{ old('title',$record->title??'') }}"></label><label>Perusahaan<input name="company" required value="{{ old('company',$record->company??'') }}"></label><label>Deskripsi<textarea name="description">{{ old('description',$record->description??'') }}</textarea></label>
<div class="form-row"><label>Lokasi<input name="location" value="{{ old('location',$record->location??'') }}"></label><label>Jenis hubungan kerja<input name="employment_type" value="{{ old('employment_type',$record->employment_type??'') }}"></label></div>
<label>Sumber data<input name="source" required value="{{ old('source',$record->source??'') }}"></label><label>URL sumber<input name="source_url" type="url" required value="{{ old('source_url',$record->source_url??'') }}"></label>
<div class="form-row"><label>Tanggal pengambilan<input type="date" name="fetched_at" required value="{{ old('fetched_at',isset($record->fetched_at)?substr((string)$record->fetched_at,0,10):now()->toDateString()) }}"></label><label>Tanggal kedaluwarsa (opsional)<input type="date" name="expires_at" value="{{ old('expires_at',isset($record->expires_at)?substr((string)$record->expires_at,0,10):'') }}"></label></div>
<label>Status<select name="status">
@foreach(['draft'=>'Draft','active'=>'Aktif','closed'=>'Ditutup','expired'=>'Kedaluwarsa','disabled'=>'Dinonaktifkan'] as $status=>$text)<option value="{{ $status }}" @selected(old('status',$record->status??'draft')===$status)>{{ $text }}</option>
@endforeach</select></label>
@else
<label>Nama pelatihan<input name="title" required value="{{ old('title',$record->title??'') }}"></label><label>Penyedia<input name="provider" required value="{{ old('provider',$record->provider??'') }}"></label><label>Deskripsi<textarea name="description">{{ old('description',$record->description??'') }}</textarea></label>
<label>Mode<select name="mode">
@foreach(['online'=>'Online','offline'=>'Tatap Muka','hybrid'=>'Hybrid'] as $m=>$text)<option value="{{ $m }}" @selected(old('mode',$record->mode??'online')===$m)>{{ $text }}</option>
@endforeach</select></label>
<div class="form-row"><label>Biaya (Rp)<input name="cost" type="number" min="0" step="0.01" required value="{{ old('cost',$record->cost??0) }}"></label><label>Durasi total (jam)<input name="duration_hours" type="number" min="1" required value="{{ old('duration_hours',$record->duration_hours??8) }}"></label><label>Jam belajar per minggu<input name="weekly_hours" type="number" min="1" required value="{{ old('weekly_hours',$record->weekly_hours??2) }}"></label></div>
<label>Lembaga terkait (opsional)<select name="certification_body_id"><option value="">Tidak ada lembaga</option>
@foreach($allBodies as $b)<option value="{{ $b->id }}" @selected(old('certification_body_id',$record->certification_body_id??null)==$b->id)>{{ $b->name }}</option>
@endforeach</select></label>
<label>Sumber data<input name="source" required value="{{ old('source',$record->source??'') }}"></label><label>URL sumber<input name="source_url" type="url" required value="{{ old('source_url',$record->source_url??'') }}"></label>
<label>Tanggal pengambilan<input name="fetched_at" type="date" required value="{{ old('fetched_at',isset($record->fetched_at)?substr((string)$record->fetched_at,0,10):now()->toDateString()) }}"></label>
<label>Status<select name="status">
@foreach(['draft'=>'Draft','active'=>'Aktif','disabled'=>'Nonaktif'] as $status=>$text)<option value="{{ $status }}" @selected(old('status',$record->status??'draft')===$status)>{{ $text }}</option>
@endforeach</select></label>
@endif
<div class="actions"><button class="btn primary">Simpan Data</button><button class="btn outline" name="continue" value="1">Simpan & Lanjutkan</button><a class="btn outline" href="/admin/{{ $base }}">Batal</a></div></form></section>
