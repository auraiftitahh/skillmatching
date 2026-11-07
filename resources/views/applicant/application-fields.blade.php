<label class="field">Posisi <input name="job_title" maxlength="190" value="{{ old('job_title', $record?->job_title) }}" required></label>
<label class="field">Perusahaan <input name="company" maxlength="190" value="{{ old('company', $record?->company) }}" required></label>
<label class="field">Tanggal melamar <input type="date" name="applied_at" value="{{ old('applied_at', $record?->applied_at) }}"></label>
<label class="field">Status pribadi
    <select name="status">
        @foreach ($statuses as $code => $label)
            <option value="{{ $code }}" @selected(old('status', $record?->status) === $code)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label class="field">Catatan <textarea name="note" maxlength="2000">{{ old('note', $record?->note) }}</textarea></label>
