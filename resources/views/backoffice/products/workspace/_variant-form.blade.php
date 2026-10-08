{{-- Variant drawer form (fetched when the drawer opens, so outlet choices are the Product's current ones). --}}
@php
    $pwPrice = fn ($value) => $value === null ? '' : 'Rp. '.number_format((float) $value, 0, ',', '.');
@endphp
<form method="POST"
      action="{{ $variant ? route('backoffice.products.workspace.variants.update', [$product, $variant]) : route('backoffice.products.workspace.variants.store', $product) }}"
      data-pw-drawer-form
      novalidate>
    @csrf
    @if($variant) @method('PUT') @endif
    @include('backoffice.partials.return-to-field', ['returnTo' => $workspaceReturnTo])

    <div class="pw-drawer-head">
        <div>
            <div class="pw-kicker">Variants &amp; Pricing</div>
            <h2 class="pw-card-title" id="pw-variant-drawer-title">{{ $variant ? 'Edit Variant '.$variant->name : 'Tambah Variant' }}</h2>
            <p class="pw-card-sub">{{ $product->name }} · harga disimpan per Variant.</p>
        </div>
        <button type="button" class="pw-drawer-close" data-pw-drawer-cancel aria-label="Tutup">&times;</button>
    </div>

    <div class="pw-drawer-body">
        <div class="pw-drawer-grid">
            <div class="pw-field">
                <label for="pw-variant-name">Nama Variant</label>
                <input id="pw-variant-name" type="text" name="name" value="{{ $variant?->name }}" maxlength="255" placeholder="Regular / Large" required>
                <div class="pw-field-error" data-pw-drawer-error="name" hidden></div>
            </div>

            <div class="pw-field">
                <label for="pw-variant-dine-in">Harga Dine In</label>
                <input id="pw-variant-dine-in" type="text" name="price_dine_in" class="pw-rupiah" inputmode="numeric" autocomplete="off"
                       value="{{ $pwPrice($variant ? ($variant->price_dine_in ?? $variant->price) : null) }}" placeholder="Rp. 0" required>
                <div class="pw-field-error" data-pw-drawer-error="price_dine_in" hidden></div>
            </div>

            <div class="pw-field">
                <label for="pw-variant-delivery">Harga Delivery</label>
                <input id="pw-variant-delivery" type="text" name="price_delivery" class="pw-rupiah" inputmode="numeric" autocomplete="off"
                       value="{{ $pwPrice($variant ? ($variant->price_delivery ?? $variant->price) : null) }}" placeholder="Rp. 0" required>
                <div class="pw-field-error" data-pw-drawer-error="price_delivery" hidden></div>
            </div>
        </div>
        <p class="pw-note pw-mt-0">Harga lama (kolom price) otomatis mengikuti harga Dine In, sama seperti editor Variant.</p>

        <div class="pw-field">
            <label>Outlet Variant</label>
            @if(count($outletChoices))
                <div class="pw-outlet-grid">
                    @foreach($outletChoices as $pwOutlet)
                        <label class="pw-outlet-card">
                            <input type="checkbox" name="outlet_ids[]" value="{{ $pwOutlet['id'] }}" @checked($pwOutlet['checked'])>
                            <span><span class="pw-outlet-name">{{ $pwOutlet['name'] }}</span></span>
                        </label>
                    @endforeach
                </div>
            @else
                <div class="pw-alert pw-alert-warn">Product belum tersedia di outlet yang dapat kamu akses. Atur di bagian Outlets terlebih dahulu.</div>
            @endif
            <div class="pw-field-error" data-pw-drawer-error="outlet_ids" hidden></div>
            <p class="pw-note pw-mt-sm">Hanya outlet Product yang sudah disimpan yang bisa dipilih.</p>
            @if(count($lockedOutlets))
                <div class="pw-alert pw-alert-info pw-mt-sm">
                    Juga tersedia di outlet di luar akses kamu (tetap dipertahankan saat disimpan):
                    <div class="pw-chip-row pw-mt-sm">
                        @foreach($lockedOutlets as $pwName)
                            <span class="pw-chip pw-chip-locked">{{ $pwName }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <label class="pw-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked($variant ? $variant->is_active : true)>
            <span>Active</span>
        </label>
        <div class="pw-field-error" data-pw-drawer-error="is_active" hidden></div>
    </div>

    <div class="pw-drawer-foot">
        <button type="button" class="btn btn-secondary" data-pw-drawer-cancel>Batal</button>
        <button type="submit" class="btn btn-primary" data-pw-drawer-save>{{ $variant ? 'Simpan Variant' : 'Tambah Variant' }}</button>
    </div>
</form>
