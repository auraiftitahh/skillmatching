@include('components.data-filters', ['searchHint'=>'Cari kode profil atau kelompok uji'])
<details class="card"><summary>+ Tambah Profil Uji</summary>
    <h2>Tambah Profil Uji</h2>
    <p>Siapkan contoh kemampuan untuk menilai kecocokan dengan lowongan. Pilih keterampilan dan tingkatnya.</p>
    <form class="editor-grid" action="{{ route('evaluation.save', 'profile') }}" method="post">
        @csrf
        <label>Kode profil<input name="code" maxlength="40" required value="{{ old('code') }}" placeholder="Contoh: PROFIL-GUDANG-01"></label>
        <label>Kelompok uji<input name="group" maxlength="100" value="{{ old('group') }}" placeholder="Contoh: Pekerja berpengalaman"></label>
        <div class="catalog-table-heading"><h3>Keterampilan Profil Uji</h3></div>
        <div id="evaluation-skills" class="evaluation-skills">
            <div class="evaluation-skill-row form-row">
                <label>Keterampilan
                    <select name="skill_ids[]" required>
                        <option value="">Pilih keterampilan</option>
                        @foreach($skills as $skill)
                            <option value="{{ $skill->id }}">{{ $skill->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Tingkat kemampuan
                    <select name="skill_levels[]" required>
                        @foreach($levelText as $level=>$label)
                            @if($level > 0)
                                <option value="{{ $level }}">{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                </label>
                <button type="button" class="btn outline remove-skill" aria-label="Hapus baris keterampilan">Hapus</button>
            </div>
        </div>
        <button type="button" class="btn outline" id="add-evaluation-skill">+ Tambah Keterampilan</button>
        <div class="actions" style="margin-top:18px"><button class="btn primary" type="submit">Simpan Profil Uji</button></div>
    </form>
</details>
<section class="card">
    <h2>Profil Uji Tersimpan</h2>
    <div class="table-wrap"><table><thead><tr><th>Profil</th><th>Kelompok</th><th>Keterampilan</th></tr></thead>
        <tbody>
        @forelse($evalProfiles as $profile)
            @php $levels = json_decode($profile->skills_json ?? '{}', true) ?: []; @endphp
            <tr><td><strong>{{ $profile->code }}</strong></td><td>{{ $profile->group ?: '—' }}</td>
                <td>
                    @forelse($levels as $id=>$level)
                        <div>{{ $skillNames[$id] ?? 'Keterampilan lama' }} — {{ $levelText[(int)$level] ?? 'Tidak diketahui' }}</div>
                    @empty
                        <span class="soft-note">Belum ada keterampilan.</span>
                    @endforelse
                </td>
            </tr>
        @empty
            <tr><td colspan="3">Belum ada profil uji.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
<script>
(function(){
    const wrap=document.getElementById('evaluation-skills');
    const add=document.getElementById('add-evaluation-skill');
    if(!wrap||!add)return;
    const template=wrap.firstElementChild.cloneNode(true);
    add.addEventListener('click',()=>{
        if(wrap.children.length>=150)return;
        const row=template.cloneNode(true);
        row.querySelectorAll('select').forEach(select=>select.selectedIndex=0);
        wrap.appendChild(row);
        window.enhanceSearchableSelects?.(row);
    });
    wrap.addEventListener('click',e=>{
        if(!e.target.closest('.remove-skill'))return;
        if(wrap.children.length>1)e.target.closest('.evaluation-skill-row').remove();
    });
})();
</script>
