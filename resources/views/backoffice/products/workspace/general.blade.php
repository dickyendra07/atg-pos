{{-- General: the Product's own fields. Saved on its own (Product outlets are a separate section). --}}
<form method="POST"
      action="{{ route('backoffice.products.workspace.general', $product) }}"
      class="pw-card"
      data-pw-form="general"
      novalidate>
    @csrf
    @method('PUT')
    @include('backoffice.partials.return-to-field', ['returnTo' => $workspaceReturnTo])

    <div class="pw-card-head">
        <div>
            <h2 class="pw-card-title">General</h2>
            <p class="pw-card-sub">Informasi dasar Product. Harga disimpan per Variant, bukan di Product.</p>
        </div>
        <div class="pw-card-actions">
            <button type="submit" class="btn btn-green" data-pw-save>Simpan General</button>
        </div>
    </div>

    <div class="pw-form-grid">
        <div class="pw-field">
            <label for="pw-brand">Brand</label>
            <select id="pw-brand" name="brand_id" required data-pw-brand-select>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected((string) old('brand_id', $product->brand_id) === (string) $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
            <div class="pw-field-error" data-pw-error="brand_id" @unless($errors->has('brand_id')) hidden @endunless>{{ $errors->first('brand_id') }}</div>
        </div>

        <div class="pw-field">
            <label for="pw-category">Category Menu</label>
            <div class="pw-field-inline">
                <select id="pw-category" name="product_category_id" required data-pw-category-select>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('product_category_id', $product->product_category_id) === (string) $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (nonaktif)' }}</option>
                    @endforeach
                </select>
                @if($canCreateCategory)
                    {{-- The drawer needs JS; without it the Category page link below still works. --}}
                    <button type="button" class="btn btn-light" data-pw-open-drawer="category" hidden>+ Buat Category</button>
                @endif
            </div>
            <noscript><a href="{{ route('backoffice.menu-categories.index') }}" class="pw-link">Kelola Category Menu</a></noscript>
            <div class="pw-field-error" data-pw-error="product_category_id" @unless($errors->has('product_category_id')) hidden @endunless>{{ $errors->first('product_category_id') }}</div>
        </div>

        <div class="pw-field">
            <label for="pw-name">Product Name</label>
            <input id="pw-name" type="text" name="name" value="{{ old('name', $product->name) }}" required maxlength="255">
            <div class="pw-field-error" data-pw-error="name" @unless($errors->has('name')) hidden @endunless>{{ $errors->first('name') }}</div>
        </div>

        <div class="pw-field">
            <label for="pw-code">Product Code</label>
            <input id="pw-code" type="text" name="code" value="{{ old('code', $product->code) }}" required maxlength="255">
            <div class="pw-field-error" data-pw-error="code" @unless($errors->has('code')) hidden @endunless>{{ $errors->first('code') }}</div>
        </div>

        <div class="pw-field full">
            <label for="pw-description">Description</label>
            <textarea id="pw-description" name="description" rows="4">{{ old('description', $product->description) }}</textarea>
            <div class="pw-field-error" data-pw-error="description" @unless($errors->has('description')) hidden @endunless>{{ $errors->first('description') }}</div>
        </div>

        <div class="pw-field">
            <label for="pw-status">Status</label>
            <select id="pw-status" name="is_active" required>
                <option value="1" @selected((string) old('is_active', (int) $product->is_active) === '1')>Active</option>
                <option value="0" @selected((string) old('is_active', (int) $product->is_active) === '0')>Inactive</option>
            </select>
            <div class="pw-field-error" data-pw-error="is_active" @unless($errors->has('is_active')) hidden @endunless>{{ $errors->first('is_active') }}</div>
        </div>
    </div>
</form>
