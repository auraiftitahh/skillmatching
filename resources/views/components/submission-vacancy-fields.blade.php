<div class="form-grid">
    <label>Posisi<input name="vacancy[title]" maxlength="190" value="{{ old('vacancy.title', $vacancy['title'] ?? '') }}" required></label>
    <label>Perusahaan<input name="vacancy[company]" maxlength="190" value="{{ old('vacancy.company', $vacancy['company'] ?? '') }}" required></label>
    <label>Lokasi<input name="vacancy[location]" maxlength="150" value="{{ old('vacancy.location', $vacancy['location'] ?? '') }}" required></label>
    <label>Hubungan kerja<select name="vacancy[employment_type]">
        @foreach(['Tetap','Kontrak'] as $type)<option @selected(old('vacancy.employment_type', $vacancy['employment_type'] ?? '') === $type)>{{ $type }}</option>@endforeach
    </select></label>
    <label>Tanggal berlaku sampai<input type="date" name="vacancy[expires_at]" value="{{ old('vacancy.expires_at', $vacancy['expires_at'] ?? '') }}" required></label>
    <label>Tautan resmi lowongan<input type="url" name="vacancy[source_url]" maxlength="255" value="{{ old('vacancy.source_url', $vacancy['source_url'] ?? '') }}" placeholder="https://..." required></label>
    <label class="full-field">Deskripsi<textarea name="vacancy[description]" maxlength="5000" required>{{ old('vacancy.description', $vacancy['description'] ?? '') }}</textarea></label>
</div>
