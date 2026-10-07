{{-- Ingredient drawer form. Ingredients are global (Recipes reference them); the same fields and rules
     as the Ingredient page. No delete here: deactivate with Status instead. --}}
<form method="POST"
      action="{{ $ingredient ? route('backoffice.products.workspace.ingredients.update', [$product, $ingredient]) : route('backoffice.products.workspace.ingredients.store', $product) }}"
      data-pw-drawer-form
      novalidate>
    @csrf
    @if($ingredient) @method('PUT') @endif
    @include('backoffice.partials.return-to-field', ['returnTo' => $workspaceReturnTo])
    <input type="hidden" name="return_section" value="{{ $returnSection }}">

    <div class="pw-drawer-head">
        <div>
            <div class="pw-kicker">Ingredient</div>
            <h2 class="pw-card-title" id="pw-ingredient-drawer-title">{{ $ingredient ? 'Edit Ingredient '.$ingredient->name : 'Buat Ingredient' }}</h2>
            <p class="pw-card-sub">Ingredient berlaku global untuk semua Product dan Recipe yang memakainya.</p>
        </div>
        <button type="button" class="pw-drawer-close" data-pw-drawer-cancel aria-label="Tutup">&times;</button>
    </div>

    <div class="pw-drawer-body">
        <div class="pw-field">
            <label for="pw-ingredient-category">Category Bahan</label>
            <div class="pw-field-inline">
                <select id="pw-ingredient-category" name="ingredient_category_id" required data-pw-ingredient-category-select>
                    <option value="">Pilih category</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) $ingredient?->ingredient_category_id === (int) $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (nonaktif)' }}</option>
                    @endforeach
                </select>
                @if($canCreateCategory)
                    <button type="button" class="btn btn-light" data-pw-open-drawer="ingredient-category">+ Buat Category</button>
                @endif
            </div>
            <div class="pw-field-error" data-pw-drawer-error="ingredient_category_id" hidden></div>
        </div>

        <div class="pw-field">
            <label for="pw-ingredient-name">Nama Ingredient</label>
            <input id="pw-ingredient-name" type="text" name="name" value="{{ $ingredient?->name }}" maxlength="255" required>
            <div class="pw-field-error" data-pw-drawer-error="name" hidden></div>
            @if($ingredient)
                <p class="pw-note pw-mt-sm">Kode: <span class="pw-code">{{ $ingredient->code }}</span> (dibuat ulang otomatis bila nama berubah).</p>
            @endif
        </div>

        <div class="pw-drawer-grid">
            <div class="pw-field">
                <label for="pw-ingredient-unit">Unit</label>
                <select id="pw-ingredient-unit" name="unit" required>
                    @foreach($unitOptions as $pwUnit)
                        <option value="{{ $pwUnit }}" @selected(($ingredient?->unit ?? '') === $pwUnit)>{{ $pwUnit }}</option>
                    @endforeach
                </select>
                <div class="pw-field-error" data-pw-drawer-error="unit" hidden></div>
            </div>

            <div class="pw-field">
                <label for="pw-ingredient-type">Jenis</label>
                <select id="pw-ingredient-type" name="ingredient_type" required>
                    @foreach($typeOptions as $pwValue => $pwLabel)
                        <option value="{{ $pwValue }}" @selected(($ingredient?->ingredient_type ?? \App\Models\Ingredient::TYPE_RAW) === $pwValue)>{{ $pwLabel }}</option>
                    @endforeach
                </select>
                <div class="pw-field-error" data-pw-drawer-error="ingredient_type" hidden></div>
            </div>

            <div class="pw-field">
                <label for="pw-ingredient-minimum">Minimum Stock</label>
                <input id="pw-ingredient-minimum" type="text" inputmode="decimal" name="minimum_stock" value="{{ $ingredient ? (float) $ingredient->minimum_stock : 0 }}" required>
                <div class="pw-field-error" data-pw-drawer-error="minimum_stock" hidden></div>
            </div>

            <div class="pw-field">
                <label for="pw-ingredient-cost">Cost per Unit</label>
                <input id="pw-ingredient-cost" type="text" inputmode="decimal" name="cost_per_unit" value="{{ $ingredient ? (float) $ingredient->cost_per_unit : 0 }}" required>
                <div class="pw-field-error" data-pw-drawer-error="cost_per_unit" hidden></div>
            </div>
        </div>

        <div class="pw-field">
            <label for="pw-ingredient-status">Status</label>
            <select id="pw-ingredient-status" name="is_active" required>
                <option value="1" @selected(! $ingredient || $ingredient->is_active)>Active</option>
                <option value="0" @selected($ingredient && ! $ingredient->is_active)>Inactive</option>
            </select>
            <div class="pw-field-error" data-pw-drawer-error="is_active" hidden></div>
            <p class="pw-note pw-mt-sm">Ingredient nonaktif membuat Variant yang memakainya tidak bisa dijual di Cashier.</p>
        </div>

        <div class="pw-field">
            <label>Outlet Ingredient</label>
            <div class="pw-outlet-grid">
                @foreach($outletChoices as $pwOutlet)
                    <label class="pw-outlet-card">
                        <input type="checkbox" name="outlet_ids[]" value="{{ $pwOutlet['id'] }}" @checked($pwOutlet['checked'])>
                        <span><span class="pw-outlet-name">{{ $pwOutlet['name'] }}</span></span>
                    </label>
                @endforeach
            </div>
            <div class="pw-field-error" data-pw-drawer-error="outlet_ids" hidden></div>
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
    </div>

    <div class="pw-drawer-foot">
        @if($ingredient)
            {{-- Temporary cleanup delete (flag + owner/admin pusat): tombstone, blocked while a Recipe still uses it. --}}
            @include('backoffice.partials.cleanup-button', ['type' => 'ingredient', 'id' => $ingredient->id, 'name' => $ingredient->name, 'return' => \App\Services\ProductWorkspace::url($product, 'recipe', $workspaceReturnTo), 'class' => 'btn btn-red', 'testid' => 'cleanup-ingredient-'.$ingredient->id])
        @endif
        <button type="button" class="btn btn-light" data-pw-drawer-cancel>Batal</button>
        <button type="submit" class="btn btn-green" data-pw-drawer-save>{{ $ingredient ? 'Simpan Ingredient' : 'Buat Ingredient' }}</button>
    </div>
</form>
