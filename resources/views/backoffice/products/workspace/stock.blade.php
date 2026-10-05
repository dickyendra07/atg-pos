@php
    $pwStock = $workspace['stock'];
    $pwStockStates = ['ok' => ['Aman', 'status-active'], 'low' => ['Low Stock', 'status-warn'], 'out' => ['Out of Stock', 'status-inactive'], 'none' => ['Belum ada saldo', 'status-muted']];
@endphp
<div class="pw-card">
    <div class="pw-card-head">
        <div>
            <h2 class="pw-card-title">Stock &amp; Readiness</h2>
            <p class="pw-card-sub">Status siap jual memakai aturan yang sama dengan Cashier, pada konteks outlet saat ini ({{ $activeOutletLabel ?? '-' }}).</p>
        </div>
        <div class="pw-card-actions">
            <a href="{{ $workspace['links']['stock_balances'] }}" class="btn btn-dark">Inventory Control</a>
        </div>
    </div>

    <div class="pw-alert pw-alert-info">Jumlah stok saat ini tidak memblokir penjualan. Stok bahan dipotong saat transaksi dan boleh menjadi minus.</div>

    @if(! count($pwStock['outlets']))
        <div class="pw-empty">Product belum tersedia di outlet aktif pada konteks outlet saat ini.</div>
    @else
        <h3 class="pw-subtitle">Siap jual per outlet</h3>
        @if(count($pwStock['eligibility']))
            <div class="pw-table-wrap">
                <table class="pw-table">
                    <thead>
                        <tr>
                            <th class="text-left">Variant</th>
                            @foreach($pwStock['outlets'] as $pwOutlet)<th>{{ $pwOutlet['name'] }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pwStock['eligibility'] as $pwRow)
                            <tr>
                                <td class="text-left"><strong>{{ $pwRow['variant_name'] }}</strong></td>
                                @foreach($pwRow['cells'] as $pwCell)
                                    <td data-pw-eligibility="{{ $pwCell['eligible'] ? 'eligible' : ($pwCell['reason'] ?? 'blocked') }}">
                                        @if($pwCell['eligible'])
                                            <span class="status-badge status-active">Siap jual</span>
                                        @else
                                            <span class="status-badge status-inactive">Diblokir</span>
                                            <div class="pw-cell-note">{{ $pwCell['message'] }}</div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="pw-empty">Belum ada Variant.</div>
        @endif

        <h3 class="pw-subtitle">Stok bahan Recipe aktif</h3>
        @if(count($pwStock['ingredients']))
            <div class="pw-table-wrap">
                <table class="pw-table">
                    <thead>
                        <tr>
                            <th class="text-left">Ingredient</th>
                            <th>Minimum</th>
                            @foreach($pwStock['outlets'] as $pwOutlet)<th>{{ $pwOutlet['name'] }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pwStock['ingredients'] as $pwIngredient)
                            <tr>
                                <td class="text-left">
                                    <strong>{{ $pwIngredient['name'] }}</strong>
                                    @unless($pwIngredient['is_active'])<span class="status-badge status-inactive pw-badge-sm">Inactive</span>@endunless
                                </td>
                                <td class="pw-num">{{ \App\Support\QuantityFormatter::twoDecimals($pwIngredient['minimum_stock']) }} {{ $pwIngredient['unit'] }}</td>
                                @foreach($pwIngredient['cells'] as $pwCell)
                                    @php([$pwStateLabel, $pwStateTone] = $pwStockStates[$pwCell['state']])
                                    <td>
                                        @if(! $pwCell['available'])
                                            <span class="status-badge status-inactive">Tidak tersedia di outlet</span>
                                        @else
                                            @if($pwCell['qty'] !== null)
                                                <div class="pw-num">{{ \App\Support\QuantityFormatter::twoDecimals($pwCell['qty']) }} {{ $pwIngredient['unit'] }}</div>
                                            @endif
                                            <span class="status-badge {{ $pwStateTone }}">{{ $pwStateLabel }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="pw-empty">Belum ada bahan dari Recipe aktif.</div>
        @endif
    @endif
</div>
