<section class="card">
    <h2>Apakah posisi ini relevan?</h2>
    <form method="post" class="form-grid" action="{{ route('applicant.matching.feedback', $job->id) }}">
        @csrf
        <label class="field">Penilaian
            <select name="rating" required>
                <option value="relevant">Relevan</option>
                <option value="not_relevant">Kurang relevan</option>
            </select>
        </label>
        <label class="field">Catatan opsional <textarea name="comment" maxlength="2000">{{ old('comment') }}</textarea></label>
        <button class="button button-primary">Kirim umpan balik</button>
    </form>
</section>
