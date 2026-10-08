{{-- Nested above the Ingredient drawer: create an Ingredient Category with the Category pages' rules
     (CategoryWriter). On success it is added to the Ingredient form's selector and selected. --}}
<div class="pw-drawer-backdrop" data-pw-drawer-backdrop="ingredient-category" hidden></div>
<aside class="pw-drawer"
       role="dialog"
       aria-modal="true"
       aria-labelledby="pw-ingredient-category-drawer-title"
       data-pw-drawer="ingredient-category"
       hidden>
    <form method="POST" action="{{ route('backoffice.products.workspace.ingredient-categories.store', $product) }}" data-pw-drawer-form novalidate>
        @csrf
        @include('backoffice.partials.return-to-field', ['returnTo' => $workspaceReturnTo])

        <div class="pw-drawer-head">
            <div>
                <div class="pw-kicker">Category Bahan</div>
                <h2 class="pw-card-title" id="pw-ingredient-category-drawer-title">Buat Ingredient Category</h2>
                <p class="pw-card-sub">Category baru langsung bisa dipilih untuk Ingredient ini.</p>
            </div>
            <button type="button" class="pw-drawer-close" data-pw-drawer-cancel aria-label="Tutup">&times;</button>
        </div>

        <div class="pw-drawer-body">
            <div class="pw-field">
                <label for="pw-ingredient-category-name">Nama Category</label>
                <input id="pw-ingredient-category-name" type="text" name="name" maxlength="255" autocomplete="off" required>
                <div class="pw-field-error" data-pw-drawer-error="name" hidden></div>
            </div>

            <label class="pw-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" checked>
                <span>Active</span>
            </label>
            <div class="pw-field-error" data-pw-drawer-error="is_active" hidden></div>
        </div>

        <div class="pw-drawer-foot">
            <button type="button" class="btn btn-secondary" data-pw-drawer-cancel>Batal</button>
            <button type="submit" class="btn btn-primary" data-pw-drawer-save>Simpan Category</button>
        </div>
    </form>
</aside>
