<x-layouts.base title="Status Akun Lembaga">
    <div class="institution-status-page">
        <header class="status-page-header"><a class="brand" href="{{ route('institution.account-status') }}"><span class="brand-mark"><x-icon name="spark" /></span><span>skillmatching</span></a><form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="button button-outline">Keluar dari akun</button></form></header>
        <main id="main-content" tabindex="-1" class="status-page-main">
            <div class="page-heading"><div><p class="eyebrow">Badan Sertifikasi</p><h1>Status akun lembaga</h1></div>@if ($canOperate)<a class="button button-outline" href="{{ route('workspace', ['role' => 'sertifikasi', 'page' => 'dashboard']) }}"><x-icon name="arrow" />Kembali ke dashboard</a>@endif</div>
            @if ($errors->any())<x-alert type="error">{{ $errors->first() }}</x-alert>@endif
            <section class="card status-overview" aria-labelledby="institution-name">
                <div class="status-overview-heading"><div><h2 id="institution-name">{{ $body?->name ?? 'Profil lembaga belum tersedia' }}</h2><p class="account-email">{{ $user->email }}</p></div><span @class(['badge', 'badge-warning' => ! $canOperate])>{{ $statusLabel }}</span></div>
                <p class="status-message">{{ $statusMessage }}</p>
                <div class="eligibility-grid" aria-label="Syarat akses layanan">
                    <div class="eligibility-item"><x-icon :name="$user->is_active ? 'check' : 'info'" /><div><span>Status akun</span><strong>{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</strong></div></div>
                    <div class="eligibility-item"><x-icon :name="$user->hasVerifiedEmail() ? 'check' : 'info'" /><div><span>Email</span><strong>{{ $user->hasVerifiedEmail() ? 'Terverifikasi' : 'Belum terverifikasi' }}</strong></div></div>
                    <div class="eligibility-item"><x-icon :name="$canOperate ? 'check' : 'history'" /><div><span>Persetujuan lembaga</span><strong>{{ $statusLabel }}</strong></div></div>
                </div>
                <div class="status-next-step"><h3>Langkah selanjutnya</h3><p>{{ $nextStep }}</p><div class="actions">@if ($canOperate)<a class="button button-primary" href="{{ route('workspace', ['role' => 'sertifikasi', 'page' => 'dashboard']) }}">Buka dashboard</a>@endif<a class="button button-outline" href="{{ route('institution.account-status') }}">Cek status terbaru</a></div></div>
                <p class="form-hint status-access-note">Status persetujuan ditentukan oleh Admin. Anda tidak dapat mengubah status ini sendiri.</p>
            </section>
            <section class="card" aria-labelledby="latest-decision-title"><h2 id="latest-decision-title">Keputusan terakhir yang tercatat</h2>
                @if ($latestDecision)
                    @if ($body->status !== $latestDecision->after_status)<x-alert>Catatan keputusan terakhir belum sesuai dengan status saat ini. Hubungi Admin untuk konfirmasi.</x-alert>@endif
                    <div class="history-heading"><strong>{{ \App\Support\InstitutionReviewActions::ACTION_LABELS[$latestDecision->action] }}</strong><time datetime="{{ $latestDecision->created_at->toIso8601String() }}">{{ $latestDecision->created_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i') }} WIB</time></div>
                    <p class="history-actor">Oleh {{ $latestDecision->actor_name }}</p><p class="history-reason">{{ $latestDecision->reason }}</p>
                @else
                    <p class="empty-decision-note">Belum ada catatan keputusan untuk lembaga Anda. Jika status memerlukan penjelasan, hubungi Admin.</p>
                @endif
            </section>
            @if ($changes && $changes->total() > 0)
                <details class="card status-history-panel" @if ($changes->currentPage() > 1) open @endif><summary>Riwayat keputusan lembaga <span class="badge">{{ $changes->total() }}</span></summary>
                    @foreach ($changes as $change)<article class="status-history"><div class="history-heading"><strong>{{ \App\Support\InstitutionReviewActions::ACTION_LABELS[$change->action] }}</strong><time datetime="{{ $change->created_at->toIso8601String() }}">{{ $change->created_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i') }} WIB</time></div><p class="history-actor">{{ \App\Support\InstitutionReviewActions::LABELS[$change->before_status] }} → {{ \App\Support\InstitutionReviewActions::LABELS[$change->after_status] }} · Oleh {{ $change->actor_name }}</p><p class="history-reason">{{ $change->reason }}</p></article>@endforeach
                    <div class="pagination-wrap">{{ $changes->links() }}</div>
                </details>
            @endif
        </main>
        <footer class="status-page-footer">© {{ date('Y') }} Skill Matching · Keterampilan hari ini, peluang esok hari.</footer>
    </div>
</x-layouts.base>
