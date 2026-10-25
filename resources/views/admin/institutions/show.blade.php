<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <div class="account-detail-grid">
        <section class="card"><h2>{{ $body->name }}</h2><span class="badge">{{ \App\Support\InstitutionReviewActions::LABELS[$body->status] }}</span>
            <dl class="account-facts"><div><dt>Penanggung jawab</dt><dd>{{ $body->user?->name ?? 'Akun tidak tersedia' }}</dd></div><div><dt>Email akun</dt><dd>{{ $body->user?->email ?? 'Tidak tersedia' }}</dd></div><div><dt>Kontak lembaga</dt><dd>{{ $body->contact ?: 'Belum diisi' }}</dd></div><div><dt>Status akun</dt><dd>{{ $body->user?->is_active ? 'Aktif' : 'Nonaktif' }}</dd></div><div><dt>Verifikasi email</dt><dd>{{ $body->user?->hasVerifiedEmail() ? 'Terverifikasi' : 'Belum terverifikasi' }}</dd></div><div><dt>Tanggal pendaftaran</dt><dd>{{ $body->created_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i') }} WIB</dd></div><div><dt>Disetujui pada</dt><dd>{{ $body->verified_at?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i') ?? 'Belum tercatat' }}@if ($body->verified_at) WIB @endif</dd></div></dl>
            @if ($body->user)<a class="button button-outline" href="{{ route('admin.accounts.show', ['user' => $body->user_id]) }}">Lihat akun penanggung jawab</a>@endif
            <p class="form-hint">Keputusan lembaga tidak mengubah status aktif akun atau verifikasi email. Kelola status akun melalui Pengelolaan Akun.</p>
        </section>
        <section class="card"><h2>Keputusan Admin</h2>
            @if (empty($actions))<x-alert type="error">Akun pemilik lembaga perlu diperiksa. Keputusan belum dapat diproses.</x-alert>
            @else
                <p class="form-hint">Periksa informasi lembaga sebelum mengambil keputusan. Semua keputusan memerlukan alasan yang jelas.</p>
                @if ($body->status === 'disabled' && ! $body->verified_at)<x-alert>Catatan persetujuan sebelumnya belum tersedia. Lakukan tinjauan sebelum menyetujui lembaga ini.</x-alert>@endif
                @if (! $body->user->is_active || ! $body->user->hasVerifiedEmail())<x-alert>Akun pemilik belum memenuhi seluruh syarat akses. Persetujuan lembaga saja belum memberikan akses operasional.</x-alert>@endif
                <form method="post" action="{{ route('admin.institutions.decide', array_merge(['body' => $body->id], $filters)) }}" data-pending-form>
                    @csrf<input type="hidden" name="expected_status" value="{{ $body->status }}">
                    <label class="field" for="action">Keputusan<select id="action" name="action" required><option value="">Pilih keputusan</option>@foreach ($actions as $value => $label)<option value="{{ $value }}" @selected(old('action') === $value)>{{ $label }}</option>@endforeach</select>@error('action')<span class="field-error">{{ $message }}</span>@enderror</label>
                    <label class="field" for="reason">Alasan keputusan<textarea id="reason" name="reason" rows="4" minlength="5" maxlength="500" required aria-describedby="decision-reason-hint @error('reason') decision-reason-error @enderror" @error('reason') aria-invalid="true" @enderror>{{ array_key_exists(old('action', ''), $actions) ? old('reason') : '' }}</textarea><span id="decision-reason-hint" class="form-hint">Gunakan 5–500 karakter. Alasan dan nama pemeriksa akan tersimpan dalam riwayat.</span>@error('reason')<span id="decision-reason-error" class="field-error">{{ $message }}</span>@enderror</label>
                    <button class="button button-primary" type="submit" data-pending-label="Menyimpan keputusan…">Simpan keputusan</button><p class="form-hint" aria-live="polite" data-pending-status></p>
                </form>
            @endif
        </section>
    </div>
    <section class="card"><h2>Riwayat keputusan lembaga</h2>
        @forelse ($changes as $change)<article class="status-history"><div class="history-heading"><strong>{{ \App\Support\InstitutionReviewActions::ACTION_LABELS[$change->action] }}</strong><time datetime="{{ $change->created_at->toIso8601String() }}">{{ $change->created_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i') }} WIB</time></div><p class="history-actor">{{ \App\Support\InstitutionReviewActions::LABELS[$change->before_status] }} → {{ \App\Support\InstitutionReviewActions::LABELS[$change->after_status] }} · Oleh {{ $change->actor_name }}</p><p class="history-reason">{{ $change->reason }}</p></article>
        @empty<div class="small-empty"><p>Belum ada keputusan yang tercatat.</p></div>@endforelse
        <div class="pagination-wrap">{{ $changes->links() }}</div>
    </section>
</x-layouts.workspace>
