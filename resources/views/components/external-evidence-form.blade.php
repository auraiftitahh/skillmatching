@php
$editing=isset($evidenceEdit)&&$evidenceEdit;
$resubmitting=isset($evidenceResubmit)&&$evidenceResubmit;
$record=$editing?$evidenceEdit:($resubmitting?$evidenceResubmit:null);
$selectedIssuer=old('certification_body_id',$record?->certification_body_id??'');
$eligibleIssuer=$allBodies->contains('id',(int)$selectedIssuer);
if(!$eligibleIssuer)$selectedIssuer='';
@endphp
<section class="context-banner"><strong>Bukti pelatihan di luar katalog</strong><p>Untuk pelatihan katalog, gunakan <a href="/pelamar/pelatihan/aktivitas">Aktivitas Pelatihan</a>. Dokumen ini hanya diperiksa oleh lembaga penerbit yang sesuai.</p></section>
@if($resubmitting)<div class="note">Ini pengajuan baru untuk memperbaiki bukti yang ditolak. Dokumen dan keputusan pengajuan lama tetap tersimpan.</div>@endif
<form method="post" enctype="multipart/form-data" action="{{ $editing?route('external-evidence.update',$record->id):route('external-evidence.save') }}" class="card evidence-editor" data-dirty-guard data-issuer-form>
@csrf
@if($editing) @method('PUT') @endif
@if($resubmitting)<input type="hidden" name="source_certificate_id" value="{{ $record->id }}">@endif
<h2>{{ $editing?'Edit bukti eksternal':($resubmitting?'Perbaiki dan ajukan ulang':'Simpan bukti pelatihan eksternal') }}</h2>
<p>Unggahan tidak otomatis mengubah level. Pilih penerbit sebenarnya; jika belum tersedia, bukti disimpan secara pribadi.</p>
<div class="form-grid">
<label class="full-field">Judul sertifikat<input name="title" value="{{ old('title',$record?->title) }}" required maxlength="190"></label>
<label class="full-field">Penerbit sertifikat<select name="certification_body_id" data-issuer-select><option value="">Penerbit belum terdaftar</option>@foreach($allBodies as $issuer)<option value="{{ $issuer->id }}" @selected((int)$selectedIssuer===$issuer->id)>{{ $issuer->name }}</option>@endforeach</select><small>Jika dipilih, dokumen akan dikirim kepada lembaga ini untuk diperiksa.</small></label>
<label class="full-field" data-manual-issuer @if($selectedIssuer!=='') hidden @endif>Nama penerbit yang belum terdaftar<input name="issuer_name" value="{{ old('issuer_name',$record?->issuer_name) }}" maxlength="190" minlength="2" @required($selectedIssuer==='') @disabled($selectedIssuer!=='')><small>Bukti belum dikirim kepada lembaga mana pun.</small></label>
<label>Skill yang dibuktikan<select name="skill_id" required><option value="">Pilih skill</option>@foreach($skills as $skill)<option value="{{ $skill->id }}" @selected((int)old('skill_id',$record?->skill_id)===$skill->id)>{{ $skill->name }}</option>@endforeach</select></label>
<label>Level yang didukung dokumen<select name="level" required>@foreach([1=>'Dasar',2=>'Menengah',3=>'Mahir',4=>'Ahli'] as $level=>$text)<option value="{{ $level }}" @selected((int)old('level',$record?->level??1)===$level)>{{ $text }}</option>@endforeach</select></label>
<label class="full-field">{{ $record?'Ganti dokumen (opsional)':'Dokumen sertifikat' }}<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" @required(!$record)><small>PDF/JPG/PNG, maksimal 5 MB. {{ $record?'Kosongkan jika tetap menggunakan dokumen tersimpan.':'' }}</small></label>
</div>
<div class="actions"><button class="btn primary" data-confirm="Simpan bukti? Jika memilih penerbit terdaftar, dokumen akan dikirim ke lembaga tersebut untuk diperiksa.">{{ $editing?'Simpan Perubahan':'Simpan Bukti' }}</button><a class="btn outline" href="{{ $record?'/pelamar/bukti/detail?id='.$record->id:'/pelamar/sertifikat' }}">Batal</a></div>
</form>
