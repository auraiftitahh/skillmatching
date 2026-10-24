@props(['name', 'label', 'autocomplete' => 'new-password'])
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <div class="password-field">
        <input id="{{ $name }}" name="{{ $name }}" type="password" autocomplete="{{ $autocomplete }}" required @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif>
        <button type="button" class="password-toggle" data-password-toggle="{{ $name }}" aria-controls="{{ $name }}" aria-pressed="false" aria-label="Tampilkan {{ mb_strtolower($label) }}">Lihat</button>
    </div>
    @error($name)<span class="field-error" id="{{ $name }}-error">{{ $message }}</span>@enderror
</div>
