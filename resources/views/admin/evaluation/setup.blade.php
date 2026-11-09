<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <div class="actions"><a class="button button-outline" href="{{ route('admin.evaluation.feedback') }}">Tinjau umpan balik</a></div>
    <div class="skill-options">
        <section class="card">
            <h2>Buat profil uji</h2>
            <p>Gunakan kode profil dan pilih level keterampilan untuk skenario evaluasi.</p>
            <form method="post" action="{{ route('admin.evaluation.profiles.store') }}" class="form-grid">
                @csrf
                <label class="field">Kode profil <input name="code" maxlength="40" value="{{ old('code') }}" required></label>
                <label class="field">Kelompok <input name="group" maxlength="100" value="{{ old('group') }}"></label>
                @foreach ($skills as $skill)
                    <label class="field">{{ $skill->name }}
                        <select name="levels[{{ $skill->id }}]">
                            <option value="">Tidak dipilih</option>
                            @foreach (range(1, 4) as $level)
                                <option value="{{ $level }}" @selected((string) old('levels.'.$skill->id) === (string) $level)>Level {{ $level }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
                <button class="button button-primary" @disabled($skills->isEmpty())>Simpan profil uji</button>
            </form>
        </section>
        <section class="card">
            <h2>Catat penilaian ahli</h2>
            @if ($profiles->isEmpty() || $jobs->isEmpty())
                <p>Buat profil uji dan pastikan ada lowongan terpublikasi sebelum menilai.</p>
            @else
                <form method="post" action="{{ route('admin.evaluation.ratings.store') }}" class="form-grid">
                    @csrf
                    <label class="field">Profil uji
                        <select name="evaluation_profile_id" required>
                            @foreach ($profiles as $profile)<option value="{{ $profile->id }}">{{ $profile->code }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field">Posisi tujuan
                        <select name="job_vacancy_id" required>
                            @foreach ($jobs as $job)<option value="{{ $job->id }}">{{ $job->title }} · {{ $job->company }}</option>@endforeach
                        </select>
                    </label>
                    <label class="field">Penilaian ahli
                        <select name="expert_rating" required>
                            <option value="">Pilih penilaian</option>
                            <option value="1">1 · Tidak cocok</option>
                            <option value="2">2 · Kurang cocok</option>
                            <option value="3">3 · Cocok</option>
                            <option value="4">4 · Sangat cocok</option>
                        </select>
                    </label>
                    <button class="button button-primary">Simpan penilaian</button>
                </form>
            @endif
        </section>
    </div>
</x-layouts.workspace>
