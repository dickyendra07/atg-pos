{{-- Recipe drawer form (fetched when the drawer opens). Existing rows carry their stored qty twice: the
     editable value and original_qty; the server only writes a row whose qty the user changed, so
     untouched qty/unit stay exactly as stored. New rows copy the Ingredient's unit (classic rule).
     Activating is NOT part of this form: it is a separate, explicit action in the Recipe section. --}}
<form method="POST"
      action="{{ $recipe ? route('backoffice.products.workspace.recipes.update', [$product, $variant, $recipe]) : route('backoffice.products.workspace.recipes.store', [$product, $variant]) }}"
      data-pw-drawer-form
      data-pw-recipe-form
      data-pw-recipe-active="{{ $recipe && $recipe->is_active ? '1' : '0' }}"
      data-pw-options-url="{{ $optionsUrl }}"
      novalidate>
    @csrf
    @if($recipe) @method('PUT') @endif
    @include('backoffice.partials.return-to-field', ['returnTo' => $workspaceReturnTo])

    <div class="pw-drawer-head">
        <div>
            <div class="pw-kicker">Recipe</div>
            <h2 class="pw-card-title" id="pw-recipe-drawer-title">{{ $recipe ? 'Edit Recipe #'.$recipe->id : 'Buat Recipe' }}</h2>
            <p class="pw-card-sub">{{ $product->name }} · Variant {{ $variant->name }}</p>
        </div>
        <button type="button" class="pw-drawer-close" data-pw-drawer-cancel aria-label="Tutup">&times;</button>
    </div>

    <div class="pw-drawer-body">
        <div class="pw-alert pw-alert-danger pw-mt-0" data-pw-drawer-error="recipe" hidden></div>

        <dl class="pw-facts pw-facts-compact">
            <div><dt>Product</dt><dd>{{ $product->name }}</dd></div>
            <div><dt>Variant</dt><dd>{{ $variant->name }}{{ $variant->code ? ' ('.$variant->code.')' : '' }}</dd></div>
            <div>
                <dt>Status Recipe</dt>
                <dd>
                    @if($recipe)
                        <span class="status-badge pw-badge-sm {{ $recipe->is_active ? 'status-active' : 'status-muted' }}">{{ $recipe->is_active ? 'Active' : 'Inactive' }}</span>
                    @else
                        <span class="status-badge pw-badge-sm status-muted">Baru · Inactive</span>
                    @endif
                </dd>
            </div>
            <div><dt>Outlet Variant</dt><dd>{{ count($variantOutlets) ? implode(', ', $variantOutlets) : '-' }}</dd></div>
        </dl>

        @if(! $recipe)
            <div class="pw-alert pw-alert-info pw-mt-0">Recipe baru disimpan sebagai <strong>nonaktif</strong>. Setelah bahannya lengkap, aktifkan lewat tombol Aktifkan di bagian Recipe.</div>
        @elseif(! $recipe->is_active)
            <div class="pw-alert pw-alert-info pw-mt-0">Recipe ini nonaktif. Menyimpan tidak mengaktifkannya; aktifkan lewat tombol Aktifkan di bagian Recipe.</div>
        @endif

        <div class="pw-field">
            <label for="pw-recipe-name">Nama Recipe</label>
            <input id="pw-recipe-name" type="text" name="name" value="{{ $recipe ? $recipe->name : $defaultName }}" maxlength="255" required>
            <div class="pw-field-error" data-pw-drawer-error="name" hidden></div>
        </div>

        @if($recipe)
            <div>
                <div class="pw-subtitle pw-mt-0">Bahan Recipe</div>
                @if(count($items))
                    <div class="pw-recipe-rows">
                        @foreach($items as $pwItem)
                            <div class="pw-recipe-row" data-pw-recipe-item="{{ $pwItem['id'] }}">
                                <div class="pw-recipe-row-main">
                                    <div class="pw-recipe-ingredient">
                                        <strong>{{ $pwItem['ingredient'] }}</strong>
                                        @if($pwItem['category'])<span class="pw-muted"> · {{ $pwItem['category'] }}</span>@endif
                                        @foreach($pwItem['warnings'] as $pwWarning)
                                            <div class="pw-cell-note pw-warn-text">{{ $pwWarning }}</div>
                                        @endforeach
                                    </div>
                                    <div class="pw-recipe-qty">
                                        <label class="pw-sr-only" for="pw-recipe-qty-{{ $pwItem['id'] }}">Qty {{ $pwItem['ingredient'] }}</label>
                                        <input id="pw-recipe-qty-{{ $pwItem['id'] }}" type="text" inputmode="decimal" autocomplete="off"
                                               name="items[{{ $pwItem['id'] }}][qty]" value="{{ $pwItem['qty'] }}">
                                        <input type="hidden" name="items[{{ $pwItem['id'] }}][original_qty]" value="{{ $pwItem['qty'] }}">
                                        <input type="hidden" name="items[{{ $pwItem['id'] }}][remove]" value="0" data-pw-recipe-remove-flag>
                                        <span class="pw-recipe-unit" title="Unit tersimpan">{{ $pwItem['unit'] ?? '-' }}</span>
                                    </div>
                                    <button type="button" class="btn btn-danger btn-sm" data-pw-recipe-remove aria-label="Hapus {{ $pwItem['ingredient'] }} dari Recipe">Hapus</button>
                                </div>
                                <div class="pw-field-error" data-pw-drawer-error="items.{{ $pwItem['id'] }}.qty" hidden></div>
                                <div class="pw-recipe-removed-note">Bahan ini akan dihapus dari Recipe saat disimpan.</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="pw-empty">Recipe ini belum memiliki bahan.</div>
                @endif
                <p class="pw-note">Qty dan unit yang tidak diubah tetap tersimpan persis seperti sekarang. Unit tidak dikonversi.</p>
            </div>
        @endif

        <div>
            <div class="pw-subtitle pw-mt-0">Tambah bahan</div>
            <div class="pw-recipe-rows" data-pw-recipe-new-rows data-pw-next-index="{{ $recipe ? 0 : 1 }}">
                @unless($recipe)
                    @include('backoffice.products.workspace._recipe-new-row', ['index' => 0, 'ingredientOptions' => $ingredientOptions, 'unavailableOptions' => $unavailableOptions ?? []])
                @endunless
            </div>
            <template data-pw-recipe-row-template>
                @include('backoffice.products.workspace._recipe-new-row', ['index' => '__INDEX__', 'ingredientOptions' => $ingredientOptions, 'unavailableOptions' => $unavailableOptions ?? []])
            </template>

            <div class="pw-row-actions">
                <button type="button" class="btn btn-secondary btn-sm" data-pw-recipe-add-row>+ Tambah bahan</button>
                @if($ingredientCreateUrl)
                    <button type="button" class="btn btn-primary btn-sm" data-pw-open-drawer="ingredient" data-pw-form-url="{{ $ingredientCreateUrl }}">+ Buat Ingredient</button>
                @endif
            </div>
            <p class="pw-note">
                @if(count($variantOutlets))
                    Recipe ini dipakai di {{ implode(', ', $variantOutlets) }}. Ingredient harus aktif dan tersedia di semua outlet tersebut. Ingredient yang belum memenuhi syarat tetap terlihat (abu-abu) dengan alasannya; Owner/Admin Pusat dapat memperbaikinya di menu Ingredients. Unit bahan baru mengikuti unit Ingredient.
                @else
                    <strong>Variant ini belum tersedia di outlet aktif manapun</strong> (Product dan Variant harus sama-sama tersedia di outlet aktif), jadi Recipe belum bisa dinyatakan siap jual.
                @endif
                <span data-pw-recipe-no-options @if(count($ingredientOptions)) hidden @endif>Belum ada Ingredient yang memenuhi syarat.</span>
            </p>
        </div>

    </div>

    <div class="pw-drawer-foot">
        <button type="button" class="btn btn-secondary" data-pw-drawer-cancel>Batal</button>
        <button type="submit" class="btn btn-primary" data-pw-drawer-save>{{ $recipe ? 'Simpan Recipe' : 'Buat Recipe' }}</button>
    </div>
</form>
