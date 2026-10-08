@php
    $pwPromos = $workspace['promos'];
    $pwPromoTones = ['active' => 'status-active', 'upcoming' => 'status-warn', 'expired' => 'status-muted', 'inactive' => 'status-muted'];
@endphp
<div class="pw-card" data-pw-promo-section>
    <div class="pw-card-head">
        <div>
            <h2 class="pw-card-title">Promo</h2>
            <p class="pw-card-sub">Promo yang memakai Variant Product ini sebagai syarat atau reward. Promo dibuat dan diubah di halaman Promo; dari sini Anda kembali ke Product ini setelah menyimpan. Mengubah Product, Variant atau outlet tidak mengubah Promo secara otomatis.</p>
        </div>
        @if($pwPromos['create_url'])
            <div class="pw-card-actions">
                <a href="{{ $pwPromos['create_url'] }}" class="btn btn-primary" data-pw-promo-create>+ Buat Promo</a>
            </div>
        @endif
    </div>

    @if(count($pwPromos['variant_create']) > 1)
        <div class="pw-chip-row pw-mt-0" data-pw-promo-create-variants>
            <span class="pw-muted">Buat Promo dari Variant:</span>
            @foreach($pwPromos['variant_create'] as $pwCreate)
                <a href="{{ $pwCreate['url'] }}" class="pw-chip pw-chip-link">{{ $pwCreate['name'] }}</a>
            @endforeach
        </div>
    @endif

    @if(! $pwPromos['can_edit'])
        <div class="pw-alert pw-alert-info">Akun ini hanya dapat melihat Promo. Pengelolaan Promo hanya tersedia untuk role yang memiliki akses ke halaman Promo.</div>
    @endif

    @if($pwPromos['summary']['total'] > 0)
        <div class="pw-chip-row" data-pw-promo-summary>
            <span class="pw-chip">{{ $pwPromos['summary']['total'] }} Promo</span>
            @if($pwPromos['summary']['active'])<span class="pw-chip pw-chip-ok">{{ $pwPromos['summary']['active'] }} aktif</span>@endif
            @if($pwPromos['summary']['upcoming'])<span class="pw-chip pw-chip-warn">{{ $pwPromos['summary']['upcoming'] }} akan datang</span>@endif
            @if($pwPromos['summary']['expired'])<span class="pw-chip">{{ $pwPromos['summary']['expired'] }} berakhir</span>@endif
            @if($pwPromos['summary']['inactive'])<span class="pw-chip">{{ $pwPromos['summary']['inactive'] }} nonaktif</span>@endif
        </div>
    @endif

    @if($pwPromos['scope_note'])
        <p class="pw-note" data-pw-promo-scope>{{ $pwPromos['scope_note'] }}</p>
    @endif

    @forelse($pwPromos['promos'] as $pwPromo)
        <div class="pw-row-card" id="promo-{{ $pwPromo['id'] }}" data-pw-promo="{{ $pwPromo['id'] }}" data-pw-promo-state="{{ $pwPromo['state'] }}">
            <div class="pw-row-card-head">
                <strong>{{ $pwPromo['name'] }}</strong>
                <span class="status-badge {{ $pwPromoTones[$pwPromo['state']] }}">{{ $pwPromo['state_label'] }}</span>
            </div>
            @if($pwPromo['window_note'])<p class="pw-note pw-mt-0">{{ $pwPromo['window_note'] }}</p>@endif
            <dl class="pw-facts pw-facts-compact">
                <div class="full">
                    <dt>Mekanisme</dt>
                    <dd class="pw-dd-plain">
                        <div>
                            @if(count($pwPromo['requirements']))
                                Syarat{{ count($pwPromo['requirements']) > 1 ? ' ('.$pwPromo['logic'].')' : '' }}: {{ implode($pwPromo['logic'] === 'OR' ? ' ATAU ' : ' + ', $pwPromo['requirements']) }}
                            @else
                                Syarat: belum diatur
                            @endif
                        </div>
                        <div>Reward: {{ count($pwPromo['rewards']) ? implode(' + ', $pwPromo['rewards']) : 'belum diatur' }}</div>
                    </dd>
                </div>
                <div><dt>Dipakai oleh</dt><dd>{{ implode(', ', $pwPromo['usage']) }}</dd></div>
                <div>
                    <dt>Cakupan</dt>
                    <dd>{{ $pwPromo['variant_count'] }} Variant Product ini · {{ $pwPromo['outlet_count'] }} outlet{{ $pwPromo['hidden_outlet_count'] > 0 ? ' (+'.$pwPromo['hidden_outlet_count'].' outlet lain di luar akses)' : '' }}</dd>
                </div>
                <div class="full">
                    <dt>Outlet</dt>
                    <dd>{{ count($pwPromo['outlets']) ? implode(', ', $pwPromo['outlets']) : ($pwPromo['hidden_outlet_count'] > 0 ? 'Tidak ada outlet dalam akses akun ini' : 'Belum ada outlet') }}</dd>
                </div>
                <div class="full"><dt>Jadwal</dt><dd>{{ $pwPromo['schedule'] }}</dd></div>
            </dl>

            @if($pwPromo['state'] !== 'inactive' && ! count($pwPromo['outlets']) && $pwPromo['hidden_outlet_count'] === 0)
                <div class="pw-alert pw-alert-warn pw-mt-sm">Promo ini belum memiliki outlet sehingga tidak berlaku di Cashier mana pun.</div>
            @endif
            @if(count($pwPromo['conflicts']))
                <div class="pw-alert pw-alert-warn pw-mt-sm" data-pw-promo-conflicts>
                    Promo ini mencakup outlet yang tidak dapat menjual Variant-nya:
                    <ul>@foreach($pwPromo['conflicts'] as $pwConflict)<li>{{ $pwConflict }}</li>@endforeach</ul>
                    Promo tidak diubah otomatis; tinjau di editor Promo.
                </div>
            @endif

            @if($pwPromo['edit_url'])
                <div class="pw-row-actions"><a href="{{ $pwPromo['edit_url'] }}" class="btn btn-secondary btn-sm" data-pw-promo-edit>Kelola Promo</a></div>
            @elseif($pwPromo['read_only_reason'])
                <p class="pw-note">{{ $pwPromo['read_only_reason'] }}</p>
            @endif
        </div>
    @empty
        <div class="pw-empty">Belum ada Promo yang menggunakan Variant dari Product ini.</div>
    @endforelse
</div>
