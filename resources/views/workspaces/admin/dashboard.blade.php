<section class="journey-hero"><div><span class="eyebrow">TINDAKAN UTAMA</span><h2>Tinjau pengajuan dalam satu tempat.</h2><p>Lembaga menyusun pelatihan dan mengusulkan keterampilan. Periksa kelayakan serta petakan duplikat sebelum menyetujui.</p></div><a class="btn primary" href="/admin/pengajuan?status=pending">Tinjau Pengajuan</a></section>
<div class="stats">
@foreach($metrics as $k=>$value)<a class="stat" href="/admin/{{ ['skills'=>'skill','jobs'=>'lowongan','trainings'=>'pelatihan','pending'=>'akun?role=sertifikasi&body_status=pending'][$k] }}"><b>{{ $value }}</b>{{ ['skills'=>'Skill','jobs'=>'Lowongan','trainings'=>'Pelatihan','pending'=>'Lembaga menunggu'][$k] }}</a>
@endforeach</div>
<div class="cards"><section class="card"><h2>Perlu Tindakan</h2><div class="list"><a class="subcard" href="/admin/akun/verifikasi">Verifikasi akun lembaga →</a><a class="subcard" href="/admin/lowongan">Periksa lowongan draft/kedaluwarsa →</a></div></section><section class="card"><h2>Konfigurasi Aktif</h2><div class="score-text">V{{ $config?->version??'-' }}</div><p>Bobot wajib {{ $config?->required_weight }} • Diutamakan {{ $config?->preferred_weight }} • Ambang {{ $config?->threshold }}</p><a class="btn outline" href="/admin/bobot">Lihat Konfigurasi</a></section></div>
<section class="card"><h2>Aktivitas terakhir</h2>
@forelse($records->take(5) as $row)<div class="activity-row"><div><b>{{ $row->label }}</b><small>{{ $row->subject }} · Oleh {{ $row->actor }}</small></div><time>{{ $row->when }}</time></div>
@empty<div class="empty">Belum ada perubahan data.</div>
@endforelse</section>
