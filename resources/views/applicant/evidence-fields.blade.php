<label>Judul bukti <input name="title" maxlength="190" value="{{ old('title', $record?->title) }}" required></label>
<label>Lembaga penerbit
    <select name="certification_body_id">
        <option value="">Simpan pribadi</option>
        @foreach ($issuers as $issuer)
            <option value="{{ $issuer->id }}" @selected((string) old('certification_body_id', $record?->certification_body_id) === (string) $issuer->id)>{{ $issuer->name }}</option>
        @endforeach
    </select>
</label>
<label>Nama penerbit untuk bukti pribadi <input name="issuer_name" maxlength="190" value="{{ old('issuer_name', $record?->issuer_name) }}"></label>
<label>Keterampilan
    <select name="skill_id" required>
        @foreach ($skills as $skill)
            <option value="{{ $skill->id }}" @selected((string) old('skill_id', $record?->skill_id) === (string) $skill->id)>{{ $skill->name }}</option>
        @endforeach
    </select>
</label>
<label>Level yang didukung <input type="number" name="level" min="1" max="4" value="{{ old('level', $record?->level ?? 1) }}" required></label>
<label>Dokumen PDF, JPG atau PNG, maks. 5 MB <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" @if(!$record) required @endif></label>
