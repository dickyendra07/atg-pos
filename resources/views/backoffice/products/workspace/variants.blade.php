{{-- Variants & Pricing. Create/edit open a drawer (built from the Product's current outlets); the links
     fall back to the classic Variant group editor without JS. Removing a Variant is "Nonaktifkan" only. --}}
<div class="pw-card">
    <div class="pw-card-head">
        <div>
            <h2 class="pw-card-title">Variants &amp; Pricing</h2>
            <p class="pw-card-sub">Harga dijual per Variant (Dine In &amp; Delivery). Product tidak memiliki harga dasar.</p>
        </div>
        <div class="pw-card-actions">
            <a href="{{ $workspace['links']['variants_create'] }}"
               class="btn btn-green"
               data-pw-open-drawer="variant"
               data-pw-form-url="{{ $workspace['links']['variant_create_form'] }}">+ Tambah Variant</a>
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
                        <th>Aksi</th>
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
                            <td class="pw-num">
                                Rp {{ number_format($pwVariant['price_dine_in'], 0, ',', '.') }}
                                @if($pwVariant['legacy_price'] !== null)
                                    <div class="pw-cell-note">Harga lama: Rp {{ number_format($pwVariant['legacy_price'], 0, ',', '.') }}</div>
                                @endif
                            </td>
                            <td class="pw-num">Rp {{ number_format($pwVariant['price_delivery'], 0, ',', '.') }}</td>
                            <td class="text-left">{{ count($pwVariant['outlets']) ? implode(', ', $pwVariant['outlets']) : '-' }}</td>
                            <td>
                                <span class="status-badge {{ $pwVariant['readiness_count'] > 0 && $pwVariant['sellable_count'] === $pwVariant['readiness_count'] ? 'status-active' : 'status-warn' }}">
                                    {{ $pwVariant['sellable_count'] }}/{{ $pwVariant['readiness_count'] }} outlet
                                </span>
                            </td>
                            <td>
                                <div class="pw-row-buttons">
                                    <a href="{{ $pwVariant['legacy_edit_url'] }}"
                                       class="btn btn-blue btn-sm"
                                       data-pw-open-drawer="variant"
                                       data-pw-form-url="{{ $pwVariant['form_url'] }}">Edit</a>
                                    @if($pwVariant['is_active'])
                                        <form method="POST"
                                              action="{{ $pwVariant['deactivate_url'] }}"
                                              data-pw-row-action
                                              data-bo-confirm-title="Nonaktifkan Variant?"
                                              data-bo-confirm-body="Variant “{{ $pwVariant['name'] }}” tidak akan bisa dijual di Cashier. Outlet, Recipe, dan riwayat transaksi tetap tersimpan."
                                              data-bo-confirm-label="Nonaktifkan"
                                              data-bo-confirm-tone="warning">
                                            @csrf
                                            @method('PATCH')
                                            @include('backoffice.partials.return-to-field', ['returnTo' => $workspaceReturnTo])
                                            <button type="submit" class="btn btn-orange btn-sm">Nonaktifkan</button>
                                        </form>
                                    @endif
                                    {{-- Temporary cleanup delete (flag + owner/admin pusat): tombstone, history stays. --}}
                                    @include('backoffice.partials.cleanup-button', ['type' => 'variant', 'id' => $pwVariant['id'], 'name' => $pwVariant['name'], 'return' => \App\Services\ProductWorkspace::url($product, 'variants', $workspaceReturnTo), 'class' => 'btn btn-red btn-sm', 'testid' => 'cleanup-variant-'.$pwVariant['id']])
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="pw-note">
            Variant nonaktif bisa diaktifkan kembali lewat Edit. Detail alasan siap jual ada di bagian Stock &amp; Readiness.
            @if($workspace['links']['variants_edit'])
                Editor grup lama: <a href="{{ $workspace['links']['variants_edit'] }}" class="pw-link" data-pw-manage="variants">Kelola semua Variant</a>.
            @endif
        </p>
    @else
        <div class="pw-empty">Belum ada Variant. Product baru bisa dijual di Cashier setelah memiliki Variant aktif dan Recipe yang valid.</div>
    @endif
</div>
