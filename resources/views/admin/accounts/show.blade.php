<x-layouts.workspace :page-title="$pageTitle" :role-label="$roleLabel" :navigation="$navigation" :home-url="$homeUrl" :back-url="$backUrl" :user-name="$userName" :logout-url="route('logout')">
    <div class="account-detail-grid">
        <section class="card"><h2>{{ $account->name }}</h2><p class="account-email">{{ $account->email }}</p>
            <dl class="account-facts"><div><dt>Peran</dt><dd>{{ \App\Support\WorkspaceNavigation::label($account->role) }}</dd></div><div><dt>Status akun</dt><dd><span @class(['badge', 'badge-muted' => ! $account->is_active])>{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span></dd></div><div><dt>Verifikasi email</dt><dd>{{ $account->hasVerifiedEmail() ? 'Terverifikasi' : 'Belum terverifikasi' }}</dd></div><div><dt>Terdaftar pada</dt><dd><time datetime="{{ $account->created_at->toIso8601String() }}">{{ $account->created_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i') }} WIB</time></dd></div></dl>
            @if ($account->role === 'sertifikasi')
                <div class="institution-summary"><h3>Informasi lembaga</h3>@if ($account->certificationBody)<p><strong>{{ $account->certificationBody->name }}</strong></p><p>Status lembaga: {{ match ($account->certificationBody->status) { 'pending' => 'Menunggu persetujuan', 'active' => 'Disetujui', 'rejected' => 'Ditolak', 'disabled' => 'Dinonaktifkan' } }}</p>@else<p>Profil lembaga belum tersedia.</p>@endif<p class="form-hint">Mengaktifkan akun tidak memverifikasi email atau menyetujui lembaga.</p></div>
            @endif
        </section>
        <section class="card"><h2>Pengaturan status akun</h2>
            @if ($account->role === 'admin')
                <x-alert>Status akun Admin tidak dapat diubah melalui halaman ini untuk menjaga akses pengelola platform.</x-alert>
            @else
                <p class="form-hint">{{ $account->is_active ? 'Menonaktifkan akun menghentikan akses pengguna pada permintaan berikutnya.' : 'Akun yang diaktifkan tetap harus memenuhi verifikasi email dan persetujuan lembaga bila berlaku.' }}</p>
                <form method="post" action="{{ route('admin.accounts.status', array_merge(['user' => $account->id], $filters)) }}" data-pending-form>
                    @csrf @method('PATCH')<input type="hidden" name="action" value="{{ $account->is_active ? 'deactivate' : 'activate' }}"><input type="hidden" name="expected_is_active" value="{{ $account->is_active ? '1' : '0' }}">
                    <label class="field" for="reason">Alasan perubahan<textarea id="reason" name="reason" rows="4" minlength="5" maxlength="500" required aria-describedby="reason-hint @error('reason') reason-error @enderror" @error('reason') aria-invalid="true" @enderror>{{ old('reason') }}</textarea><span id="reason-hint" class="form-hint">Jelaskan alasan dalam 5–500 karakter. Alasan dan nama petugas akan tercatat.</span>@error('reason')<span class="field-error" id="reason-error">{{ $message }}</span>@enderror</label>
                    <button type="submit" @class(['button', 'button-danger' => $account->is_active, 'button-primary' => ! $account->is_active]) data-pending-label="Menyimpan perubahan…">{{ $account->is_active ? 'Nonaktifkan akun' : 'Aktifkan akun' }}</button><p class="form-hint" aria-live="polite" data-pending-status></p>
                </form>
            @endif
        </section>
    </div>
    <section class="card"><h2>Riwayat perubahan status</h2>
        @forelse ($changes as $change)
            <article class="status-history"><div class="history-heading"><strong>{{ $change->before_is_active ? 'Aktif' : 'Nonaktif' }} → {{ $change->after_is_active ? 'Aktif' : 'Nonaktif' }}</strong><time datetime="{{ $change->created_at->toIso8601String() }}">{{ $change->created_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i') }} WIB</time></div><p class="history-actor">Oleh {{ $change->actor_name }}</p><p class="history-reason">{{ $change->reason }}</p></article>
        @empty
            <div class="small-empty"><p>Belum ada perubahan status akun.</p></div>
        @endforelse
        <div class="pagination-wrap">{{ $changes->links() }}</div>
    </section>
</x-layouts.workspace>
