@php($pwPromos = $workspace['promos'])
<div class="pw-card">
    <div class="pw-card-head">
        <div>
            <h2 class="pw-card-title">Promo</h2>
            <p class="pw-card-sub">Promo yang memakai Variant Product ini sebagai syarat atau reward. Hanya informasi; Promo diubah di halaman Promo.</p>
        </div>
    </div>

    @forelse($pwPromos['promos'] as $pwPromo)
        <div class="pw-row-card" data-pw-promo="{{ $pwPromo['id'] }}">
            <div class="pw-row-card-head">
                <strong>{{ $pwPromo['name'] }}</strong>
                <span class="status-badge {{ $pwPromo['live'] ? 'status-active' : 'status-muted' }}">{{ $pwPromo['live'] ? 'Active' : ucfirst($pwPromo['status']).($pwPromo['is_active'] ? '' : ' · nonaktif') }}</span>
            </div>
            <dl class="pw-facts pw-facts-compact">
                <div><dt>Dipakai oleh</dt><dd>{{ implode(', ', $pwPromo['usage']) }}</dd></div>
                <div><dt>Outlet</dt><dd>{{ count($pwPromo['outlets']) ? implode(', ', $pwPromo['outlets']) : 'Belum ada outlet' }}</dd></div>
                <div class="full"><dt>Jadwal</dt><dd>{{ $pwPromo['schedule'] }}</dd></div>
            </dl>
            @if($pwPromo['edit_url'])
                <div class="pw-row-actions"><a href="{{ $pwPromo['edit_url'] }}" class="btn btn-blue btn-sm">Kelola Promo</a></div>
            @endif
        </div>
    @empty
        <div class="pw-empty">Belum ada Promo yang memakai Variant Product ini.</div>
    @endforelse
</div>
