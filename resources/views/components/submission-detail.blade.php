@php $p=$submissionPayload; @endphp
<section class="card"><div class="section-actions"><span class="badge">{{ $statusText[$submission->status]??$submission->status }}</span><span>{{ $submission->body_name }}</span></div><h2>{{ $p['title'] }}</h2><p class="preserve-lines">{{ $p['description'] }}</p><div class="chips"><span>{{ \App\Support\Display::mode($p['mode']) }}</span><span>{{ $p['duration_hours'] }} jam</span><span>{{ $p['weekly_hours'] }} jam/pekan</span><span>Rp {{ number_format($p['cost'],0,',','.') }}</span></div><a href="{{ $p['source_url'] }}" target="_blank" rel="noopener noreferrer">Buka Halaman Pelatihan ↗</a>
</section>
@php
    $skillsReady = collect($p['outcomes'])->every(fn (array $outcome) => !empty($outcome['skill_id'])
        || (($outcome['skill_review']['decision'] ?? null) === 'approved' && !empty($outcome['resolved_skill_id'])));
@endphp
<section class="card">
    <h2>1. Tinjauan keterampilan</h2>
    <p>Tinjau setiap usulan skill baru. Skill dari katalog bisa langsung digunakan.</p>
    <div class="list">
        @foreach($p['outcomes'] as $index=>$outcome)
            @php
                $proposal = empty($outcome['skill_id']);
                $skill = $skills->firstWhere('id', (int) ($outcome['resolved_skill_id'] ?? $outcome['skill_id'] ?? 0));
                $review = $outcome['skill_review'] ?? null;
            @endphp
            <article class="outcome-review">
                <div>
                    <strong>{{ $outcome['new_name'] ?? $skill?->name ?? 'Skill tersimpan' }}</strong>
                    <span class="badge">{{ $levelText[$outcome['target_level']] }}</span>
                    @if($proposal)
                        <p>{{ $outcome['category'] }} · {{ $review ? ($review['decision']==='approved'?'Disetujui':'Ditolak') : ($skill ? 'Disetujui pada tinjauan sebelumnya' : ($submission->status==='draft'?'Usulan dalam draft':'Menunggu tinjauan')) }}</p>
                        <p>{{ $outcome['description'] }}</p>
                        @if($skill)<p>Skill katalog: {{ $skill->name }}</p>@endif
                        @if($review)<p>Catatan Admin: {{ $review['note'] }}</p>@endif
                        @if(!empty($outcome['reference']))
                            <a target="_blank" rel="noopener noreferrer" href="{{ $outcome['reference'] }}">Buka acuan ↗</a>
                        @endif
                    @endif
                </div>
                @if($proposal && !$review && $role==='admin' && $submission->status==='pending')
                    <form method="post" action="{{ route('submissions.skills.review', [$submission->id,$index]) }}">
                        @csrf
                        <label>Padanan katalog (opsional)
                            <select name="mapping">
                                <option value="">Daftarkan skill jika nama belum ada</option>
                                @foreach($skills as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->name }} · {{ $candidate->category }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Catatan tinjauan skill
                            <textarea name="skill_note" required minlength="5" maxlength="2000"></textarea>
                        </label>
                        <div class="actions">
                            <button class="btn primary" name="skill_decision" value="approved">Setujui Skill</button>
                            <button class="btn outline" name="skill_decision" value="rejected">Tolak Skill</button>
                        </div>
                    </form>
                @endif
            </article>
        @endforeach
    </div>
</section>
<section class="card">
    <h2>2. Tinjauan pelatihan</h2>
    @if(in_array($submission->status, ['pending','revision']) && !$skillsReady)
            <p class="note">{{ $submission->status==='revision'
                ? 'Pelatihan menunggu perbaikan skill dari lembaga.'
                : 'Tinjauan pelatihan tersedia setelah semua usulan skill diperiksa dan disetujui.' }}</p>
        @elseif($role==='admin' && $submission->status==='pending')
            <form method="post" action="{{ route('submissions.review',$submission->id) }}">
                @csrf
                <label>Keputusan pelatihan
                    <select required name="decision">
                        <option value="">Pilih keputusan</option>
                        <option value="approved">Setujui dan terbitkan pelatihan</option>
                        <option value="revision">Minta perbaikan pelatihan</option>
                        <option value="rejected">Tolak pelatihan</option>
                    </select>
                </label>
                <label>Catatan tinjauan pelatihan
                    <textarea name="review_note" required minlength="5" maxlength="2000"></textarea>
                </label>
                <button class="btn primary" data-confirm="Simpan hasil tinjauan pelatihan?">Simpan Tinjauan Pelatihan</button>
            </form>
        @elseif($submission->status==='pending')
            <p class="note">Pelatihan menunggu tinjauan Admin.</p>
        @else
            <span class="badge">{{ $statusText[$submission->status] ?? $submission->status }}</span>
        @endif
        @if($submission->review_note)
            <p class="note">{{ $submission->review_note }}</p>
        @endif
</section>
@if($role==='sertifikasi' && in_array($submission->status,['draft','revision','rejected']))<a class="btn primary" href="/sertifikasi/pengajuan/form?id={{ $submission->id }}">{{ $submission->status==='draft'?'Lanjutkan Draft':'Perbaiki Pengajuan' }}</a>@endif
@if(!empty($p['vacancy']))
    @include('components.submission-vacancy-review', ['vacancy'=>$p['vacancy']])
@endif
@if($submission->status==='pending')<p class="note">Sedang menunggu tinjauan. Versi yang sudah terbit tetap berlaku sampai revisi disetujui.</p>@endif
