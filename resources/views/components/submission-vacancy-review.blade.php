<section class="card">
    <h2>3. Tinjauan lowongan terkait</h2>
    <span class="badge">{{ $statusText[$vacancy['status']] ?? $vacancy['status'] }}</span>
    <h3>{{ $vacancy['title'] }}</h3>
    <p>{{ $vacancy['company'] }} · {{ $vacancy['location'] }} · {{ $vacancy['employment_type'] }}</p>
    <p class="preserve-lines">{{ $vacancy['description'] }}</p>
    <p>Berlaku sampai {{ \App\Support\Display::date($vacancy['expires_at']) }}</p>
    <a href="{{ $vacancy['source_url'] }}" target="_blank" rel="noopener noreferrer">Buka sumber lowongan ↗</a>
    <h3>Persyaratan keterampilan</h3>
    <ul>
        @foreach($vacancy['requirements'] as $index=>$requirement)
            @php
                $outcome = $p['outcomes'][$index] ?? [];
                $skill = $skills->firstWhere('id',(int)($outcome['resolved_skill_id']??$outcome['skill_id']??0));
            @endphp
            <li>{{ $skill?->name ?? $outcome['new_name'] ?? 'Keterampilan' }} · {{ $levelText[$requirement['minimum_level']] }} · {{ $requirement['importance']==='required'?'Wajib':'Diutamakan' }}</li>
        @endforeach
    </ul>
    @if(!empty($vacancy['review_note']))<p class="note">{{ $vacancy['review_note'] }}</p>@endif
    @if($vacancy['status']==='pending' && $submission->status!=='approved')
        <p class="note">{{ match ($submission->status) {
            'rejected' => 'Lowongan tertahan karena pengajuan pelatihan ditolak.',
            'revision' => 'Selesaikan revisi pelatihan sebelum Admin dapat meninjau lowongan.',
            default => 'Lowongan bisa ditinjau setelah usulan skill dan pelatihan disetujui.',
        } }}</p>
    @elseif($vacancy['status']==='pending' && $role==='admin')
        <form method="post" action="{{ route('submissions.vacancy.review',$submission->id) }}">
            @csrf
            <label>Keputusan lowongan<select name="decision" required>
                <option value="">Pilih keputusan</option>
                <option value="approved">Setujui dan terbitkan lowongan</option>
                <option value="revision">Minta perbaikan lowongan</option>
                <option value="rejected">Tolak lowongan</option>
            </select></label>
            <label>Catatan tinjauan lowongan<textarea name="review_note" minlength="5" maxlength="2000" required></textarea></label>
            <button class="btn primary" type="submit">Simpan Tinjauan Lowongan</button>
        </form>
    @elseif($vacancy['status']==='revision' && $role==='sertifikasi' && $submission->status==='approved')
        <details open>
            <summary>Perbaiki lowongan</summary>
            <form method="post" action="{{ route('submissions.vacancy.resubmit',$submission->id) }}">
                @csrf
                @include('components.submission-vacancy-fields')
                <section class="vacancy-skills">
                <h3>Skill yang disyaratkan lowongan</h3>
                <p>Centang skill yang menjadi syarat lowongan dan sesuaikan level serta kepentingannya.</p>
                <div class="vacancy-skill-list">
                @foreach($p['outcomes'] as $index=>$outcome)
                    @php($skill = $skills->firstWhere('id',(int)($outcome['resolved_skill_id']??0)))
                    @if($skill)
                        @include('components.submission-vacancy-requirement', ['requirement'=>(array)(old('vacancy.requirements.'.$index)??$vacancy['requirements'][$index]??[]), 'label'=>$skill->name, 'maximumLevel'=>$outcome['target_level']])
                    @endif
                @endforeach
                </div>
                </section>
                <button class="btn primary" type="submit">Kirim Ulang Lowongan</button>
            </form>
        </details>
    @elseif(!empty($vacancy['job_vacancy_id']))
        <p>Lowongan telah dipublikasikan.</p>
        @if($role==='admin')<a class="btn outline" href="/admin/lowongan/detail?id={{ $vacancy['job_vacancy_id'] }}">Lihat Detail Lowongan</a>@endif
    @endif
</section>
