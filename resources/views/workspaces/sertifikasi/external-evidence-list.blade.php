@include('components.data-filters', ['searchHint'=>'Cari judul atau penerbit', 'filterOptions'=>[['name'=>'status','label'=>'Status','options'=>[''=>'Semua status','pending'=>'Menunggu','verified'=>'Terverifikasi','rejected'=>'Ditolak']]]])
<section class="card"><h2>Bukti dari pelatihan di luar katalog</h2><p>Hanya dokumen yang ditujukan kepada lembaga Anda sebagai penerbit. Tinjau dokumen sebelum mencatat keputusan.</p></section>
@include('components.external-evidence-list')
