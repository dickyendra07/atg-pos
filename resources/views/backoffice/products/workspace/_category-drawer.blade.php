{{-- Create a Menu Category without leaving the workspace. Same rules as the Category page
     (CategoryWriter). On success the new Category is added to the General selector and selected. --}}
<div class="pw-drawer-backdrop" data-pw-drawer-backdrop hidden></div>
<aside class="pw-drawer"
       id="pw-category-drawer"
       role="dialog"
       aria-modal="true"
       aria-labelledby="pw-category-drawer-title"
       data-pw-drawer="category"
       hidden>
    <form method="POST" action="{{ route('backoffice.products.workspace.menu-categories.store', $product) }}" data-pw-drawer-form novalidate>
        @csrf
        @include('backoffice.partials.return-to-field', ['returnTo' => $workspaceReturnTo])

        <div class="pw-drawer-head">
            <div>
                <div class="pw-kicker">Category Menu</div>
                <h2 class="pw-card-title" id="pw-category-drawer-title">Buat Category Menu</h2>
                <p class="pw-card-sub">Category baru langsung bisa dipilih untuk Product ini.</p>
            </div>
            <button type="button" class="pw-drawer-close" data-pw-drawer-cancel aria-label="Tutup">&times;</button>
        </div>

        <div class="pw-drawer-body">
            <div class="pw-field">
                <label for="pw-category-name">Nama Category</label>
                <input id="pw-category-name" type="text" name="name" maxlength="255" autocomplete="off" required>
                <div class="pw-field-error" data-pw-drawer-error="name" hidden></div>
            </div>

            <div class="pw-field">
                <label for="pw-category-brand">Brand</label>
                <select id="pw-category-brand" name="brand_id" required>
                    <option value="">Pilih brand</option>
                    @foreach($categoryBrands as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </select>
                <div class="pw-field-error" data-pw-drawer-error="brand_id" hidden></div>
            </div>

            <label class="pw-check">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" checked>
                <span>Active</span>
            </label>
            <div class="pw-field-error" data-pw-drawer-error="is_active" hidden></div>
        </div>

        <div class="pw-drawer-foot">
            <button type="button" class="btn btn-light" data-pw-drawer-cancel>Batal</button>
            <button type="submit" class="btn btn-green" data-pw-drawer-save>Simpan Category</button>
        </div>
    </form>
</aside>
