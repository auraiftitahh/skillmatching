<details class="card target-picker" @if(!$selectedJobIds) open @endif><summary>{{ $selectedJobIds?'Ubah lowongan target':'Pilih lowongan target' }}</summary><form method="get"><h2>Pilih lowongan target</h2><div class="chips">
@foreach($allVacancies as $job)<label class="check"><input type="checkbox" name="jobs[]" value="{{ $job->id }}" @checked(in_array($job->id,$selectedJobIds))>{{ $job->title }}</label>
@endforeach</div><button class="btn primary">Hitung Gap</button></form></details>
<div class="cards">
@forelse($gaps as $g)<article class="card"><span class="badge orange">Prioritas {{ $g['priority']>=3?'Tinggi':'Sedang' }}</span><h3>{{ $g['skill'] }}</h3><div class="form-row"><p>Anda: <strong>{{ $levelText[$g['own']] }}</strong></p><p>Target: <strong>{{ $levelText[$g['target']] }}</strong></p></div><p>Selisih: {{ $g['gap'] }} level. Dibutuhkan oleh {{ count($g['jobs']) }} lowongan.</p><a class="btn primary" href="/pelamar/pelatihan?{{ http_build_query([...$contextQuery,'skill_id'=>$g['skill_id']]) }}">Cari Pelatihan →</a></article>
@empty<div class="empty">{{ $selectedJobIds?"Tidak ada gap untuk lowongan terpilih.":"Pilih minimal satu lowongan target untuk menghitung gap." }}</div>
@endforelse</div>
