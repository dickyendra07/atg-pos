{{-- Product Workspace header. Re-rendered after a successful section save. --}}
<div class="pw-header-main">
    <div class="pw-kicker">Product Workspace</div>
    <h1 class="pw-title" data-pw-title>{{ $product->name }}</h1>
    <div class="pw-meta">
        <span class="pw-code">{{ $product->code }}</span>
        @if($product->is_active)
            <span class="status-badge status-active">Active</span>
        @else
            <span class="status-badge status-inactive">Inactive</span>
        @endif
        <span class="pw-chip">{{ $product->category->name ?? 'Tanpa Category' }}</span>
        <span class="pw-chip">{{ $product->brand->name ?? '-' }}</span>
        <span class="pw-chip">{{ $workspace['header']['active_variant_count'] }}/{{ $workspace['header']['variant_count'] }} variant aktif</span>
        <span class="pw-chip">{{ $workspace['header']['outlet_count'] }} outlet</span>
        @php($pwSellable = $workspace['header']['sellable'])
        <span class="pw-chip {{ $pwSellable['total'] > 0 && $pwSellable['eligible'] === $pwSellable['total'] ? 'pw-chip-ok' : 'pw-chip-warn' }}"
              title="Variant x outlet yang lolos aturan Cashier pada konteks outlet saat ini">
            {{ $pwSellable['eligible'] }}/{{ $pwSellable['total'] }} siap jual
        </span>
    </div>
</div>

<div class="pw-header-actions">
    <span class="pw-save-state" data-pw-save-state aria-live="polite">Tersimpan</span>
    <a href="{{ $closeUrl }}" class="btn btn-secondary" data-pw-close>{{ $closeLabel ?? 'Kembali ke Products' }}</a>
</div>
