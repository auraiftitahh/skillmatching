@php
    $maximumLevel = (int) ($maximumLevel ?? 4);
    $minimumLevel = min((int) ($requirement['minimum_level'] ?? 2), $maximumLevel);
    $checked = !empty($requirement['selected'])
        || (isset($requirement['minimum_level']) && !old('vacancy.requirements'));
@endphp
<div class="vacancy-skill" data-outcome-index="{{ $index }}" @unless(filled($label)) hidden @endunless>
    <label class="vacancy-skill-choice">
        <input type="checkbox" name="vacancy[requirements][{{ $index }}][selected]" value="1"
            aria-controls="vacancy-skill-settings-{{ $index }}" @checked($checked)>
        <span data-outcome-label>{{ $label }}</span>
    </label>
    <div class="form-grid vacancy-skill-settings" id="vacancy-skill-settings-{{ $index }}">
        <label>Level minimum untuk lowongan
            <select name="vacancy[requirements][{{ $index }}][minimum_level]">
                @foreach([1=>'Dasar',2=>'Menengah',3=>'Mahir',4=>'Ahli'] as $level=>$text)
                    <option value="{{ $level }}" @selected($minimumLevel === $level) @if($level > $maximumLevel) hidden disabled @endif>{{ $text }}</option>
                @endforeach
            </select>
            <small data-minimum-adjusted role="status" hidden>Level minimum disesuaikan dengan level capaian terbaru.</small>
        </label>
        <label>Kepentingan untuk lowongan
            <select name="vacancy[requirements][{{ $index }}][importance]">
                <option value="required" @selected(($requirement['importance']??'required')==='required')>Wajib</option>
                <option value="preferred" @selected(($requirement['importance']??'')==='preferred')>Diutamakan</option>
            </select>
        </label>
    </div>
</div>
