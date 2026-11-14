@php $p=old()?:$submissionPayload; $rows=($p['outcomes']??[])?:[['skill_id'=>'','target_level'=>2]]; $vacancy=$p['vacancy']??[]; @endphp
<section class="context-banner"><strong>1. Informasi pelatihan → 2. Capaian skill → 3. Lowongan terkait → 4. Kirim untuk ditinjau</strong><p>Pelatihan dilakukan di luar website. Gunakan keterampilan yang tersedia sebelum mengusulkan yang baru.</p></section>
<form method="post" action="{{ route('submissions.save') }}" class="submission-editor" data-dirty-guard>@csrf
@if($submission)<input type="hidden" name="id" value="{{ $submission->id }}">@endif
@if($submission?->training_id || request('training_id'))<input type="hidden" name="training_id" value="{{ $submission?->training_id??request('training_id') }}">@endif
<section class="card"><h2>1. Informasi pelatihan</h2><div class="form-grid"><label class="full-field">Nama pelatihan<input name="title" required maxlength="190" value="{{ $p['title']??'' }}"></label><label class="full-field">Deskripsi<textarea name="description" required maxlength="5000">{{ $p['description']??'' }}</textarea></label><label>Mode<select name="mode">@foreach(['online'=>'Daring','offline'=>'Tatap muka','hybrid'=>'Hybrid'] as $m=>$label)<option value="{{ $m }}" @selected(($p['mode']??'online')===$m)>{{ $label }}</option>@endforeach</select></label><label>Biaya (Rp)<input type="number" name="cost" min="0" max="999999999" required value="{{ $p['cost']??0 }}"></label><label>Durasi total (jam)<input type="number" name="duration_hours" min="1" max="10000" required value="{{ $p['duration_hours']??8 }}"></label><label>Beban per pekan (jam)<input type="number" name="weekly_hours" min="1" max="168" required value="{{ $p['weekly_hours']??4 }}"></label><label class="full-field">Tautan informasi/pendaftaran eksternal<input type="url" name="source_url" required maxlength="255" placeholder="https://..." value="{{ $p['source_url']??'' }}"></label></div></section>
<section class="card"><h2>2. Capaian skill peserta yang lulus</h2><p>Capaian ini digunakan untuk mencatat hasil peserta. Level: Dasar, Menengah, Mahir, atau Ahli.</p><div id="submission-outcomes">@foreach($rows as $index=>$row)@include('components.submission-outcome-row')@endforeach</div><button class="btn outline" type="button" id="add-outcome">+ Tambahkan Capaian</button></section>
<section class="card">
    <h2>3. Lowongan terkait (opsional)</h2>
    <label class="vacancy-skill-choice vacancy-toggle">
        <input type="checkbox" name="with_vacancy" value="1" id="with-vacancy" aria-controls="vacancy-fields" @checked(old() ? old('with_vacancy') : $vacancy)>
        <span>Tambahkan lowongan terkait pelatihan ini</span>
    </label>
    <fieldset id="vacancy-fields" @unless(old() ? old('with_vacancy') : $vacancy) hidden disabled @endunless>
        <legend>Informasi lowongan</legend>
        <p>Admin meninjau lowongan setelah menyetujui pelatihan dan capaian skill.</p>
        @include('components.submission-vacancy-fields')
        <section class="vacancy-skills">
            <h3>Skill yang disyaratkan lowongan</h3>
            <p>Centang minimal satu skill dari bagian 2. Pilih <strong>Wajib</strong> jika perusahaan mensyaratkannya atau <strong>Diutamakan</strong> jika menjadi nilai tambah. Level minimum paling tinggi sama dengan level capaian pelatihan.</p>
            <p id="vacancy-skills-empty" class="note" hidden>Pilih atau isi nama skill di <a href="#submission-outcomes">bagian 2. Capaian skill</a> agar bisa dipilih di sini.</p>
            <div id="vacancy-requirements" class="vacancy-skill-list">
                @foreach($rows as $index=>$row)
                    @include('components.submission-vacancy-requirement', [
                        'requirement'=>(array)($vacancy['requirements'][$index]??[]),
                        'maximumLevel'=>$row['target_level'] ?? 2,
                        'label'=>$skills->firstWhere('id',(int)($row['skill_id']??0))?->name ?? $row['new_name'] ?? null,
                    ])
                @endforeach
            </div>
        </section>
    </fieldset>
</section>
<section class="card"><h2>4. Simpan atau kirim</h2><p>Draft hanya terlihat oleh lembaga Anda. Setelah dikirim, Admin meninjau usulan skill satu per satu, lalu pelatihan dan lowongan jika disertakan.</p><div class="actions"><button class="btn outline" name="intent" value="draft">Simpan Draft</button><button class="btn primary" name="intent" value="submit" data-confirm="Kirim pelatihan, capaian skill, dan lowongan yang ditambahkan untuk ditinjau Admin?">Kirim Pengajuan</button></div></section></form>
<template id="outcome-template">@php $index='__INDEX__'; $row=[]; @endphp @include('components.submission-outcome-row')</template>
<template id="vacancy-requirement-template">
    @include('components.submission-vacancy-requirement', ['index'=>'__INDEX__', 'requirement'=>[], 'label'=>null])
</template>
<script>
(() => {
    const outcomes = document.getElementById('submission-outcomes');
    const requirements = document.getElementById('vacancy-requirements');
    const emptyMessage = document.getElementById('vacancy-skills-empty');
    const toggle = document.getElementById('with-vacancy');
    const fields = document.getElementById('vacancy-fields');
    let index = {{ max(array_map('intval',array_keys($rows)))+1 }};

    function syncRequirements() {
        const editors = Array.from(outcomes.querySelectorAll('.outcome-editor'));
        const skillSelects = editors.map(editor => editor.querySelector('select[name$="[skill_id]"]'));
        const selectedSkills = new Set(skillSelects.map(select => select.value).filter(Boolean));

        editors.forEach((editor, position) => {
            const skill = skillSelects[position];
            for (const option of skill.options) {
                option.hidden = option.disabled = option.value !== skill.value && selectedSkills.has(option.value);
            }

            const name = editor.querySelector('input[name$="[new_name]"]');
            const target = editor.querySelector('select[name$="[target_level]"]');
            const key = skill.name.match(/\[(\d+)\]/)[1];
            const item = requirements.querySelector('[data-outcome-index="' + key + '"]');
            const skillLabel = skill.value ? skill.selectedOptions[0].textContent.trim() : name.value.trim();
            item.querySelector('[data-outcome-label]').textContent = skillLabel;
            item.hidden = !skillLabel;
            if (!skillLabel) item.querySelector('input[type="checkbox"]').checked = false;
            const minimum = item.querySelector('select[name$="[minimum_level]"]');
            const maximumLevel = Number(target.value);
            const previousMinimum = Number(minimum.value);
            for (const option of minimum.options) {
                option.hidden = option.disabled = Number(option.value) > maximumLevel;
            }
            if (previousMinimum > maximumLevel) {
                minimum.value = target.value;
                minimum.dispatchEvent(new Event('change'));
                item.querySelector('[data-minimum-adjusted]').hidden = false;
            }

            editor.querySelector('[data-remove-outcome]').disabled = editors.length <= 1;
        });
        emptyMessage.hidden = Boolean(requirements.querySelector('.vacancy-skill:not([hidden])'));
    }

    function syncVacancy() {
        fields.hidden = !toggle.checked;
        fields.disabled = !toggle.checked;
    }

    document.getElementById('add-outcome').addEventListener('click', () => {
        if (outcomes.children.length >= 20) return;
        for (const [list, template] of [[outcomes, 'outcome-template'], [requirements, 'vacancy-requirement-template']]) {
            list.insertAdjacentHTML('beforeend', document.getElementById(template).innerHTML.replaceAll('__INDEX__', String(index)));
        }
        index++;
        window.enhanceSearchableSelects?.(outcomes);
        window.enhanceSearchableSelects?.(requirements);
        syncRequirements();
    });

    outcomes.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-outcome]');
        if (!button || outcomes.children.length <= 1) return;
        const editor = button.closest('.outcome-editor');
        const key = editor.querySelector('select[name$="[skill_id]"]').name.match(/\[(\d+)\]/)[1];
        requirements.querySelector('[data-outcome-index="' + key + '"]').remove();
        editor.remove();
        syncRequirements();
    });

    requirements.addEventListener('change', (event) => {
        if (event.target.matches('select[name$="[minimum_level]"]')) {
            event.target.closest('.vacancy-skill').querySelector('[data-minimum-adjusted]').hidden = true;
        }
    });
    outcomes.addEventListener('change', syncRequirements);
    outcomes.addEventListener('input', syncRequirements);
    toggle.addEventListener('change', syncVacancy);
    syncRequirements();
    syncVacancy();
})();
</script>
