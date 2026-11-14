<section class="card narrow"><p>Informasi digunakan untuk melengkapi akun Anda. Status ketenagakerjaan opsional dan tidak memengaruhi skor.</p><form class="editor-grid" action="{{ route('profile.save') }}" method="post">
@csrf
<label>Nama lengkap<input name="name" required maxlength="150" value="{{ old('name',$u->name) }}"></label>
<label>Email<input value="{{ $u->email }}" readonly></label><label>Domisili (opsional)<input name="domicile" maxlength="150" value="{{ old('domicile',$u->domicile) }}"></label><label>Nomor telepon (opsional)<input name="phone" value="{{ old('phone',$u->phone) }}"></label>
<label>Status ketenagakerjaan (opsional)<select name="employment_status"><option value="">Tidak ingin mengisi</option>
@foreach(['working'=>'Bekerja','laid_off'=>'Tidak bekerja karena PHK','graduate'=>'Lulusan baru','other'=>'Lainnya'] as $key=>$label)<option value="{{ $key }}" @selected(old('employment_status',$u->employment_status)===$key)>{{ $label }}</option>
@endforeach</select></label>
<div class="actions"><button class="btn primary">Simpan Perubahan</button><a class="btn outline" href="/pelamar/riwayat">Lanjut ke Riwayat</a></div></form></section>
