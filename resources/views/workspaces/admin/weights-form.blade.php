<section class="card narrow"><h2>Terbitkan Versi Konfigurasi Baru</h2><p>Semua perubahan dicatat bersama pelaku, waktu, dan alasan perubahan.</p><form class="editor-grid" data-review="Terbitkan konfigurasi bobot" method="post" action="{{ route('weights.save') }}">
@csrf
<label>Bobot wajib (1–5)<input type="number" name="required_weight" min="1" max="5" required value="{{ old('required_weight',$config?->required_weight??3) }}"></label>
<label>Bobot diutamakan (1–5)<input type="number" name="preferred_weight" min="1" max="5" required value="{{ old('preferred_weight',$config?->preferred_weight??1) }}"></label>
<label>Faktor skill terkait (0–1)<input type="number" name="related_factor" step="0.001" min="0" max="1" required value="{{ old('related_factor',$config?->related_factor??0.5) }}"></label>
<label>Ambang skor (0–100)<input type="number" name="threshold" min="0" max="100" required value="{{ old('threshold',$config?->threshold??40) }}"></label>
<label>Alasan perubahan<textarea name="reason" required minlength="5">{{ old('reason') }}</textarea></label><button class="btn primary">Terbitkan Konfigurasi</button></form></section>
