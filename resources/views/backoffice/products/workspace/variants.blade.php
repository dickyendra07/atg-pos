<div class="pw-card">
    <div class="pw-card-head">
        <div>
            <h2 class="pw-card-title">Variants &amp; Pricing</h2>
            <p class="pw-card-sub">Harga dijual per Variant (Dine In &amp; Delivery). Product tidak memiliki harga dasar.</p>
        </div>
        <div class="pw-card-actions">
            @if($workspace['links']['variants_edit'])
                <a href="{{ $workspace['links']['variants_edit'] }}" class="btn btn-blue" data-pw-manage="variants">Kelola Variant</a>
            @endif
            <a href="{{ $workspace['links']['variants_create'] }}" class="btn btn-green">Tambah Variant</a>
        </div>
    </div>

    @if(count($workspace['variants']))
        <div class="pw-table-wrap">
            <table class="pw-table">
                <thead>
                    <tr>
                        <th class="text-left">Variant</th>
                        <th>Kode</th>
                        <th>Status</th>
                        <th>Dine In</th>
                        <th>Delivery</th>
                        <th class="text-left">Outlet</th>
                        <th>Siap Jual</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($workspace['variants'] as $pwVariant)
                        <tr data-pw-variant="{{ $pwVariant['id'] }}">
                            <td class="text-left"><strong>{{ $pwVariant['name'] }}</strong></td>
                            <td><span class="pw-code">{{ $pwVariant['code'] }}</span></td>
                            <td>
                                <span class="status-badge {{ $pwVariant['is_active'] ? 'status-active' : 'status-inactive' }}">{{ $pwVariant['is_active'] ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="pw-num">Rp {{ number_format($pwVariant['price_dine_in'], 0, ',', '.') }}</td>
                            <td class="pw-num">Rp {{ number_format($pwVariant['price_delivery'], 0, ',', '.') }}</td>
                            <td class="text-left">{{ count($pwVariant['outlets']) ? implode(', ', $pwVariant['outlets']) : '-' }}</td>
                            <td>
                                <span class="status-badge {{ $pwVariant['readiness_count'] > 0 && $pwVariant['sellable_count'] === $pwVariant['readiness_count'] ? 'status-active' : 'status-warn' }}">
                                    {{ $pwVariant['sellable_count'] }}/{{ $pwVariant['readiness_count'] }} outlet
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="pw-note">Detail alasan siap jual ada di bagian Stock &amp; Readiness.</p>
    @else
        <div class="pw-empty">Belum ada Variant. Product baru bisa dijual di Cashier setelah memiliki Variant aktif dan Recipe yang valid.</div>
    @endif
</div>
