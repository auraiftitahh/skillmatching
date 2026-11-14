@php $job=$allJobs->firstWhere('id',request()->integer('id')); @endphp
@if($job)
<div class="context-heading">
    <h2>{{ $job->title }}</h2>
    <p>Pilih keterampilan dari katalog atau tambahkan skill jika belum tersedia.</p>
    <a class="btn outline" href="#skill-baru">+ Tambah Skill Baru</a>
</div>
@include('components.bulk-skills',['bulkKind'=>'vacancy','bulkId'=>$job->id])
<details class="card help-disclosure" id="skill-baru" @if(old('new_skill_name') || $errors->hasAny(['new_skill_name','new_skill_category','new_skill_level','new_skill_importance'])) open @endif>
    <summary>Skill belum ada di katalog? Tambah skill baru</summary>
    <p>Skill langsung aktif dan menjadi persyaratan lowongan ini setelah disimpan.</p>
    <form method="post" action="{{ route('vacancy.skill.create', $job->id) }}" data-dirty-guard>
        @csrf
        <div class="form-grid">
            <label>Nama skill
                <input name="new_skill_name" maxlength="150" required value="{{ old('new_skill_name') }}">
            </label>
            <label>Kategori
                <input name="new_skill_category" maxlength="150" required value="{{ old('new_skill_category') }}">
            </label>
            <label>Level minimum untuk lowongan
                <select name="new_skill_level" required>
                    @foreach([1=>'Dasar',2=>'Menengah',3=>'Mahir',4=>'Ahli'] as $level=>$label)
                        <option value="{{ $level }}" @selected((int) old('new_skill_level', 2)===$level)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>Kepentingan
                <select name="new_skill_importance" required>
                    <option value="required" @selected(old('new_skill_importance','required')==='required')>Wajib</option>
                    <option value="preferred" @selected(old('new_skill_importance')==='preferred')>Diutamakan</option>
                </select>
            </label>
        </div>
        <div class="actions"><button class="btn primary" type="submit">Simpan Skill sebagai Persyaratan</button></div>
    </form>
</details>
<script>
    document.querySelector('a[href="#skill-baru"]').addEventListener('click', () => {
        document.getElementById('skill-baru').open = true;
    });
</script>
<div class="actions"><a class="btn outline" href="/admin/lowongan/detail?id={{ $job->id }}">Tinjau Lowongan →</a></div>
@else<div class="empty">Pilih lowongan dari daftar terlebih dahulu.</div>@endif
