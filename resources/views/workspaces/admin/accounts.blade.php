<section class="card">
    <div class="catalog-heading"></div>
    @include('components.data-filters', ['searchHint'=>'Cari nama atau email', 'filterOptions'=>[
        ['name'=>'role','label'=>'Jenis akun','options'=>[''=>'Semua akun','pelamar'=>'Pelamar','sertifikasi'=>'Badan Sertifikasi']],
        ['name'=>'status','label'=>'Status akun','options'=>[''=>'Semua status','active'=>'Aktif','inactive'=>'Nonaktif','disabled'=>'Nonaktif']]
    ]])
    <div class="table-wrap"><table><thead><tr><th>Nama</th><th>Email</th><th>Jenis Akun</th><th>Status akun</th><th>Verifikasi email</th><th>Persetujuan lembaga</th><th>Aksi</th></tr></thead><tbody>
    @forelse($users as $account)
    <tr>
        <td><strong>{{ $account->name }}</strong></td><td>{{ $account->email }}</td>
        <td>{{ $account->role==='pelamar'?'Pelamar':'Badan Sertifikasi' }}</td>
        <td><span class="badge {{ $account->is_active?'badge-active':'badge-inactive' }}">{{ $account->is_active?'Aktif':'Nonaktif' }}</span></td><td><span class="badge {{ $account->email_verified_at?'green':'orange' }}">{{ $account->email_verified_at?'Terverifikasi':'Belum verifikasi' }}</span></td><td>@if($account->role==='sertifikasi')@php $approval=$bodies->firstWhere('user_id',$account->id); @endphp<span class="badge {{ $approval?->status==='active'?'green':'orange' }}">{{ ['active'=>'Disetujui','pending'=>'Menunggu persetujuan','rejected'=>'Ditolak','inactive'=>'Nonaktif','disabled'=>'Nonaktif'][$approval?->status]??'Belum terdaftar' }}</span>@else<span aria-label="Tidak berlaku">—</span>@endif</td>
        @php $institution=$account->role==='sertifikasi'?$bodies->firstWhere('user_id',$account->id):null; $requiresReview=$institution&&in_array($institution->status,['pending','rejected']); @endphp
        <td><div class="row-actions">
            @if($requiresReview && !$account->is_active)
                <a href="/admin/akun/detail?id={{ $account->id }}">Periksa lembaga</a>
            @else
            <form method="post" action="{{ route('accounts.save',$account->id) }}" data-status-toggle data-subject="akun {{ $account->name }}">
                @csrf
                <input type="hidden" name="decision" value="{{ $account->is_active?'deactivate':'activate' }}">
                <input type="hidden" name="reason" value="">
                <button type="submit" class="ui-switch {{ $account->is_active?'is-on':'' }}" role="switch" aria-checked="{{ $account->is_active?'true':'false' }}" aria-label="{{ $account->is_active?'Nonaktifkan':'Aktifkan' }} akun {{ $account->name }}"><span></span></button>
            </form>
            @endif
            <a href="/admin/akun/detail?id={{ $account->id }}">Detail</a>
        </div></td>
    </tr>
    @empty<tr><td colspan="7">Tidak ada akun yang sesuai pencarian.</td></tr>@endforelse
    </tbody></table></div>
    @include('components.list-pages', ['paginator'=>$users])
</section>
