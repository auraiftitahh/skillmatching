<section class="card narrow"><h2>Apakah rekomendasi relevan?</h2><p>Masukan Anda membantu evaluasi dan tidak langsung mengubah bobot pencocokan.</p><form class="editor-grid" action="{{ route('feedback.save') }}" method="post">
@csrf
<label>Lowongan<select name="job_vacancy_id"><option value="">Feedback umum</option>
@foreach($allVacancies as $j)<option value="{{ $j->id }}">{{ $j->title }}</option>
@endforeach</select></label>
<label>Penilaian<select name="rating" required><option value="relevant">Relevan</option><option value="not_relevant">Tidak relevan</option></select></label>
<label>Komentar<textarea name="comment" placeholder="Apa yang perlu diperbaiki?"></textarea></label><button class="btn primary">Kirim Umpan Balik</button></form></section>
