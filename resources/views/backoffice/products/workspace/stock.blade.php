@php
    $pwStock = $workspace['stock'];
    // STOCK OBSERVATION states (informational only; they never decide whether a Variant can be sold).
    $pwStockStates = [
        'normal' => ['Normal', 'status-active'],
        'low' => ['Low Stock', 'status-warn'],
        'zero' => ['Stok nol', 'status-inactive'],
        'negative' => ['Stok minus', 'status-inactive'],
        'none' => ['Belum ada saldo', 'status-muted'],
    ];
    // CONFIGURATION READINESS of a Variant across the outlets in view.
    $pwConfigStates = [
        'ready' => ['Siap jual', 'status-active'],
        'partial' => ['Sebagian siap', 'status-warn'],
        'blocked' => ['Belum siap', 'status-inactive'],
        'none' => ['Tanpa outlet', 'status-muted'],
    ];
    $pwRecipeNotes = [
        'none' => 'Belum ada Recipe yang dapat digunakan untuk membaca kebutuhan stok.',
        'inactive' => 'Belum ada Recipe aktif yang dapat digunakan untuk membaca kebutuhan stok.',
        'empty' => 'Recipe aktif belum memiliki bahan, sehingga belum ada kebutuhan stok yang dapat dibaca.',
        'ambiguous' => 'Kebutuhan stok tidak ditampilkan: ada lebih dari satu Recipe aktif dan sistem tidak memilih salah satu.',
    ];
@endphp
<div class="pw-card" data-pw-stock>
    <div class="pw-card-head">
        <div>
            <h2 class="pw-card-title">Stock &amp; Readiness</h2>
            <p class="pw-card-sub">Apakah setup Product ini siap dipakai per Variant dan outlet, dan kondisi stok bahan yang perlu diketahui. Konteks outlet saat ini: {{ $activeOutletLabel ?? '-' }}.</p>
        </div>
        <div class="pw-card-actions">
            @if($workspace['links']['can_manage_ingredients'])
                <a href="{{ $workspace['links']['ingredient_create'] }}"
                   class="btn btn-green"
                   data-pw-open-drawer="ingredient"
                   data-pw-form-url="{{ $workspace['links']['ingredient_create_form'] }}?return_section=stock">+ Buat Ingredient</a>
            @endif
            @if($pwStock['links']['stock_balances'])
                <a href="{{ $pwStock['links']['stock_balances'] }}" class="btn btn-dark">Inventory Control</a>
            @endif
        </div>
    </div>

    <div class="pw-alert pw-alert-info">Jumlah stok saat ini tidak memblokir penjualan. Stok bahan dipotong saat transaksi dan boleh menjadi minus. <strong>Konfigurasi</strong> menjawab apakah Variant bisa dijual; <strong>stok</strong> hanya pengamatan.</div>

    @if($pwStock['scope']['active_outlet'])
        <p class="pw-note pw-mt-0" data-pw-stock-scope>
            Fokus pada outlet <strong>{{ $pwStock['scope']['active_outlet'] }}</strong>.
            @if($pwStock['scope']['other_outlets'] > 0)
                Product ini juga tersedia di {{ $pwStock['scope']['other_outlets'] }} outlet lain; pilih "Semua Outlet" di bar OUTLET untuk ringkasan seluruh outlet.
            @endif
        </p>
    @endif

    @if($pwStock['foreign_outlet'])
        <div class="pw-alert pw-alert-warn" data-pw-stock-foreign-outlet>
            Product ini belum ditugaskan ke outlet <strong>{{ $pwStock['foreign_outlet']['name'] }}</strong>, sehingga tidak dapat dijual di sana.
            @foreach(array_slice($pwStock['foreign_outlet']['messages'], 0, 1) as $pwForeignMessage)<div>{{ $pwForeignMessage }}</div>@endforeach
            <div><a href="{{ \App\Services\ProductWorkspace::url($product, 'outlets', $workspaceReturnTo) }}" data-pw-nav="outlets">Buka bagian Outlets</a></div>
        </div>
    @endif

    @if(! count($pwStock['outlets']))
        <div class="pw-empty">Product belum tersedia di outlet aktif pada konteks outlet saat ini.</div>
    @elseif(! count($pwStock['variants']))
        <div class="pw-empty">Belum ada Variant.</div>
    @else
        <div class="pw-chip-row" data-pw-stock-summary>
            <span class="pw-chip {{ $pwStock['summary']['total_pairs'] > 0 && $pwStock['summary']['ready_pairs'] === $pwStock['summary']['total_pairs'] ? 'pw-chip-ok' : 'pw-chip-warn' }}">Konfigurasi: {{ $pwStock['summary']['ready_pairs'] }}/{{ $pwStock['summary']['total_pairs'] }} Variant × outlet siap</span>
            <span class="pw-chip {{ $pwStock['summary']['attention'] > 0 ? 'pw-chip-warn' : 'pw-chip-ok' }}">Stok: {{ $pwStock['summary']['attention'] > 0 ? $pwStock['summary']['attention'].' perlu perhatian' : 'tidak ada peringatan' }}</span>
        </div>

        @foreach($pwStock['variants'] as $pwVariant)
            @php([$pwConfigLabel, $pwConfigTone] = $pwConfigStates[$pwVariant['configuration']])
            <div class="pw-row-card pw-stock-variant"
                 data-pw-stock-variant="{{ $pwVariant['id'] }}"
                 data-pw-config="{{ $pwVariant['configuration'] }}"
                 data-pw-stock-attention="{{ $pwVariant['attention'] }}">
                <div class="pw-row-card-head">
                    <div>
                        <strong>{{ $pwVariant['name'] }}</strong>
                        @unless($pwVariant['is_active'])<span class="status-badge status-inactive pw-badge-sm">Inactive</span>@endunless
                    </div>
                    <div class="pw-chip-row">
                        <span class="status-badge {{ $pwConfigTone }}" title="Konfigurasi: aturan jual yang sama dengan Cashier">Konfigurasi: {{ $pwConfigLabel }}</span>
                        @if($pwVariant['stock_context'])
                            <span class="status-badge {{ $pwVariant['attention'] > 0 ? 'status-warn' : 'status-active' }}" title="Stok: pengamatan saja, tidak memblokir penjualan">Stok: {{ $pwVariant['attention'] > 0 ? 'perlu perhatian ('.$pwVariant['attention'].')' : 'normal' }}</span>
                        @endif
                    </div>
                </div>

                <div class="pw-chip-row pw-stock-outlets">
                    @foreach($pwVariant['outlets'] as $pwOutlet)
                        <span class="pw-chip {{ $pwOutlet['eligible'] ? 'pw-chip-ok' : 'pw-chip-warn' }}"
                              data-pw-eligibility="{{ $pwOutlet['eligible'] ? 'eligible' : ($pwOutlet['reason'] ?? 'blocked') }}"
                              title="{{ $pwOutlet['eligible'] ? 'Siap jual' : $pwOutlet['message'] }}">{{ $pwOutlet['name'] }}: {{ $pwOutlet['eligible'] ? 'siap' : 'diblokir' }}</span>
                    @endforeach
                    <span class="pw-muted">{{ $pwVariant['ready_outlets'] }}/{{ $pwVariant['outlet_count'] }} outlet @if($pwVariant['stock_context']) · {{ $pwVariant['ingredient_count'] }} bahan @endif</span>
                </div>

                @foreach($pwVariant['issues'] as $pwIssue)
                    <div class="pw-alert {{ $pwIssue['reason'] === 'recipe_ambiguous' ? 'pw-alert-danger' : 'pw-alert-warn' }} pw-mt-sm" data-pw-readiness-issue="{{ $pwIssue['reason'] }}">
                        <div>{{ $pwIssue['message'] }}</div>
                        <div class="pw-cell-note">Outlet: {{ implode(', ', $pwIssue['outlets']) }}</div>
                        @if($pwIssue['section'])
                            <div class="pw-mt-sm"><a href="{{ \App\Services\ProductWorkspace::url($product, $pwIssue['section'], $workspaceReturnTo) }}" data-pw-nav="{{ $pwIssue['section'] }}">Buka bagian {{ $sections[$pwIssue['section']] }}</a></div>
                        @endif
                    </div>
                @endforeach

                @if($pwVariant['stock_context'] && count($pwVariant['outlets']))
                    <details class="pw-details" data-pw-stock-detail>
                        <summary>Detail stok per outlet ({{ $pwVariant['ingredient_count'] }} bahan)</summary>
                        @foreach($pwVariant['outlets'] as $pwOutlet)
                            @continue(! count($pwOutlet['items']))
                            <details class="pw-details pw-details-outlet" @if($pwVariant['outlet_count'] === 1) open @endif data-pw-stock-outlet="{{ $pwOutlet['id'] }}">
                                <summary>
                                    {{ $pwOutlet['name'] }}
                                    <span class="pw-muted">· {{ count($pwOutlet['items']) }} bahan</span>
                                    @if($pwOutlet['attention'] > 0)<span class="status-badge status-warn pw-badge-sm">{{ $pwOutlet['attention'] }} perlu perhatian</span>@endif
                                </summary>
                                <div class="pw-table-wrap">
                                    <table class="pw-table pw-stack-table">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Ingredient</th>
                                                <th>Kebutuhan</th>
                                                <th>Stok</th>
                                                <th>Minimum</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($pwOutlet['items'] as $pwItem)
                                                @if(! $pwItem['valid'])
                                                    <tr><td class="text-left" colspan="5"><span class="status-badge status-inactive">{{ $pwItem['name'] }}</span></td></tr>
                                                    @continue
                                                @endif
                                                @php([$pwStateLabel, $pwStateTone] = $pwStockStates[$pwItem['state']])
                                                <tr data-pw-stock-row="{{ $pwItem['ingredient_id'] }}" data-pw-stock-state="{{ $pwItem['available'] ? $pwItem['state'] : 'unavailable' }}">
                                                    <td class="text-left" data-label="Ingredient">
                                                        <strong>{{ $pwItem['name'] }}</strong>
                                                        @unless($pwItem['is_active'])<span class="status-badge status-inactive pw-badge-sm">Inactive</span>@endunless
                                                        @if($pwItem['duplicate'])<div class="pw-cell-note pw-warn-text">Ingredient muncul lebih dari sekali di Recipe (data lama, tidak digabung otomatis).</div>@endif
                                                        <div class="pw-row-buttons pw-row-buttons-left pw-mt-sm">
                                                            @if($pwItem['links'])
                                                                <a href="{{ $pwItem['links']['legacy_edit_url'] }}"
                                                                   class="btn btn-blue btn-sm"
                                                                   data-pw-open-drawer="ingredient"
                                                                   data-pw-form-url="{{ $pwItem['links']['form_url'] }}"
                                                                   data-pw-ingredient="{{ $pwItem['ingredient_id'] }}">Edit Ingredient</a>
                                                            @endif
                                                            @if($pwItem['stock_url'])
                                                                <a href="{{ $pwItem['stock_url'] }}" class="btn btn-light btn-sm" data-pw-stock-link="balance">Lihat Stok</a>
                                                                <a href="{{ $pwItem['movement_url'] }}" class="btn btn-light btn-sm" data-pw-stock-link="movement">Riwayat Stok</a>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td class="pw-num" data-label="Kebutuhan">
                                                        {{ \App\Support\QuantityFormatter::twoDecimals($pwItem['need']) }} {{ $pwItem['need_unit'] }}
                                                        @if($pwItem['unit_differs'])<div class="pw-cell-note pw-warn-text">Satuan stok: {{ $pwItem['unit'] }} (tidak dikonversi)</div>@endif
                                                    </td>
                                                    <td class="pw-num" data-label="Stok">
                                                        @if($pwItem['qty'] !== null){{ \App\Support\QuantityFormatter::twoDecimals($pwItem['qty']) }} {{ $pwItem['unit'] }}@else<span class="pw-muted">-</span>@endif
                                                    </td>
                                                    <td class="pw-num" data-label="Minimum">{{ \App\Support\QuantityFormatter::twoDecimals($pwItem['minimum']) }} {{ $pwItem['unit'] }}</td>
                                                    <td data-label="Status">
                                                        @if(! $pwItem['available'])
                                                            <span class="status-badge status-inactive">Tidak tersedia di outlet</span>
                                                            <div class="pw-cell-note">Ingredient belum ditugaskan ke outlet ini (konfigurasi, bukan stok).</div>
                                                        @else
                                                            <span class="status-badge {{ $pwStateTone }}">{{ $pwStateLabel }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        @endforeach
                    </details>
                @elseif(isset($pwRecipeNotes[$pwVariant['recipe_status']]))
                    <p class="pw-note">{{ $pwRecipeNotes[$pwVariant['recipe_status']] }}</p>
                @endif
            </div>
        @endforeach
    @endif
</div>
