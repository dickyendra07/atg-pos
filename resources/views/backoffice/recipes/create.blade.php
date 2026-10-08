<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Recipe - Back Office ATG POS</title>
    {{-- Shown inside the Recipes list drawer (an iframe of this same page): drop the page header there. --}}
    <script>if (window.self !== window.top) { document.documentElement.classList.add('is-embedded'); }</script>
    <style>
        .is-embedded .topbar { display: none; }
        .is-embedded .wrap { margin: 16px auto; }
        .is-embedded body { background: #fff; }
    </style>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 980px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 16px;
        }

        .title-block {
            max-width: 680px;
        }

        .title {
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #111827;
        }

        .subtitle {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.7;
        }

        .btn {
            text-decoration: none;
            background: #111827;
            color: white;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: bold;
            display: inline-block;
            border: 0;
            cursor: pointer;
        }

        .btn-success {
            background: #166534;
        }

        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
            margin-bottom: 20px;
        }

        .hero-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff9f5 70%, #fff1ea 100%);
            border: 1px solid #f0e1d8;
        }

        .hero-kicker {
            display: inline-block;
            background: rgba(255,255,255,0.84);
            border: 1px solid #f2dfd4;
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 8px 12px;
            border-radius: 999px;
            margin-bottom: 14px;
        }

        .hero-title {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.15;
            margin: 0 0 10px;
            color: #111827;
        }

        .hero-text {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.8;
            max-width: 760px;
        }

        .info {
            margin-bottom: 18px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            line-height: 1.8;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 6px;
            color: #374151;
        }

        .field input,
        .field select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px;
            font-size: 14px;
            background: white;
            color: #111827;
        }

        .field input:focus,
        .field select:focus {
            outline: none;
            border-color: #e86a3a;
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .field select:disabled,
        .field input:read-only {
            background: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
        }

        .field input:read-only {
            font-weight: bold;
            color: #111827;
            cursor: default;
        }

        .field.has-error select {
            border-color: #dc2626;
        }

        .field-error {
            margin-top: 6px;
            font-size: 12px;
            font-weight: bold;
            color: #b91c1c;
        }

        .existing-recipes {
            margin-top: 8px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 12px;
            line-height: 1.7;
        }

        .existing-recipes[hidden] {
            display: none;
        }

        .existing-recipes a {
            color: #c2410c;
            font-weight: bold;
        }

        .helper {
            margin-top: 6px;
            font-size: 12px;
            line-height: 1.6;
            color: #6b7280;
        }

        .error-box {
            margin-bottom: 18px;
            background: #ffe8e8;
            color: #9b1c1c;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .note {
            margin-top: 20px;
            background: #eef2ff;
            color: #3730a3;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
            line-height: 1.7;
        }

        .tip-list {
            margin: 12px 0 0 18px;
            padding: 0;
            font-weight: normal;
        }

        .tip-list li {
            margin-bottom: 8px;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .wrap {
                margin: 24px auto;
                padding: 0 14px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .title {
                font-size: 26px;
            }

            .hero-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title-block">
                <div class="title">Tambah Recipe</div>

            </div>

            <a href="{{ \App\Support\BackofficeReturnUrl::resolve(request(), 'backoffice.recipes.index', [], null) }}" class="btn btn-secondary">Kembali</a>
        </div>

        @if($errors->any())
            <div class="error-box">
                <div>Form belum valid:</div>
                <ul style="margin:10px 0 0 18px;">
                    @foreach($errors->all() as $error)
                        <li style="margin-bottom:6px;">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif


        <div class="card">
            <div class="info">
                <strong>User:</strong> {{ $user->name }}<br>
                <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                <strong>Outlet:</strong> {{ $activeOutletLabel ?? '-' }}
            </div>

            <form method="POST" action="{{ route('backoffice.recipes.store') }}">
                @csrf
                @include('backoffice.partials.return-to-field')

                <div class="field {{ $errors->has('product_id') ? 'has-error' : '' }}">
                    <label for="recipe-product">Menu / Product</label>
                    <select id="recipe-product" name="product_id" required aria-describedby="recipe-product-help">
                        <option value="">Pilih Menu</option>
                    </select>
                    @error('product_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                    <div class="helper" id="recipe-product-help">
                        Recipe berlaku ke semua outlet yang memakai Variant. Hanya Menu dan Variant yang boleh kamu ubah yang ditampilkan:
                        untuk Variant multi-outlet, kamu harus punya akses ke seluruh outlet pemakainya.
                    </div>
                </div>

                <div class="field {{ $errors->has('product_variant_id') ? 'has-error' : '' }}">
                    <label for="recipe-variant">Variant / Size</label>
                    <select id="recipe-variant" name="product_variant_id" required disabled aria-describedby="recipe-variant-help">
                        <option value="">Pilih Menu dulu</option>
                    </select>
                    @error('product_variant_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                    <div class="helper" id="recipe-variant-help" aria-live="polite"></div>
                    <div class="existing-recipes" id="recipe-existing" hidden></div>
                </div>

                <div class="field">
                    <label for="recipe-name-preview">Nama Recipe Otomatis</label>
                    {{-- Display only (no name attribute): the stored name is always built server-side. --}}
                    <input type="text" id="recipe-name-preview" value="" placeholder="Otomatis terisi setelah memilih Variant" readonly tabindex="-1" aria-live="polite">
                </div>

                <div class="field">
                    <label>Status</label>
                    <select name="is_active" required>
                        <option value="1" @selected(old('is_active', '1') == '1')>Active</option>
                        <option value="0" @selected(old('is_active') == '0')>Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Simpan Recipe</button>
                    <a href="{{ \App\Support\BackofficeReturnUrl::resolve(request(), 'backoffice.recipes.index', [], null) }}" class="btn btn-secondary">Batal</a>
                </div>
            </form>


        </div>
    </div>

    <script>
        (function () {
            var menus = @json($menuOptions);
            var oldProduct = @json((string) old('product_id', ''));
            var oldVariant = @json((string) old('product_variant_id', ''));

            var productSelect = document.getElementById('recipe-product');
            var variantSelect = document.getElementById('recipe-variant');
            var preview = document.getElementById('recipe-name-preview');
            var help = document.getElementById('recipe-variant-help');
            var existingBox = document.getElementById('recipe-existing');

            function option(value, label, disabled) {
                var el = document.createElement('option');
                el.value = value;
                el.textContent = label;
                el.disabled = !!disabled;
                return el;
            }

            function findMenu(id) {
                for (var i = 0; i < menus.length; i++) {
                    if (String(menus[i].id) === String(id)) { return menus[i]; }
                }
                return null;
            }

            function findVariant(menu, id) {
                if (!menu) { return null; }
                for (var i = 0; i < menu.variants.length; i++) {
                    if (String(menu.variants[i].id) === String(id)) { return menu.variants[i]; }
                }
                return null;
            }

            function renderExisting(menu) {
                var taken = menu ? menu.variants.filter(function (v) { return v.existing; }) : [];
                existingBox.textContent = '';
                existingBox.hidden = taken.length === 0;

                if (taken.length === 0) { return; }

                existingBox.appendChild(document.createTextNode('Sudah punya Recipe: '));
                taken.forEach(function (v, index) {
                    if (index > 0) { existingBox.appendChild(document.createTextNode(', ')); }
                    var link = document.createElement('a');
                    link.href = v.existing.url;
                    link.textContent = v.name + ' (buka Recipe)';
                    existingBox.appendChild(link);
                });
            }

            function renderPreview() {
                var menu = findMenu(productSelect.value);
                var variant = findVariant(menu, variantSelect.value);
                preview.value = variant ? variant.recipe_name : '';
            }

            // Rebuild the Variant list for the chosen Menu; keepId is only used for the first paint after a failed submit.
            function renderVariants(keepId) {
                var menu = findMenu(productSelect.value);
                variantSelect.textContent = '';
                variantSelect.setCustomValidity('');
                help.textContent = '';

                if (!menu) {
                    variantSelect.appendChild(option('', 'Pilih Menu dulu'));
                    variantSelect.disabled = true;
                    renderExisting(null);
                    renderPreview();
                    return;
                }

                var free = menu.variants.filter(function (v) { return !v.existing; });

                // Every Variant stays listed; those that already have a Recipe are disabled options. When none is
                // left the select stays enabled (so keyboard and screen-reader users can still open it and read the
                // list) but its only selectable value is the empty placeholder, so the required field blocks Simpan.
                variantSelect.appendChild(option('', free.length === 0 ? 'Semua Variant sudah punya Recipe' : 'Pilih Variant'));
                menu.variants.forEach(function (v) {
                    variantSelect.appendChild(option(v.id, v.existing ? v.name + ' — Sudah ada Recipe' : v.name, !!v.existing));
                });
                variantSelect.disabled = false;

                if (free.length === 0) {
                    help.textContent = 'Semua Variant Menu ini sudah punya Recipe. Buka Recipe yang ada di bawah, atau pilih Menu lain.';
                    variantSelect.setCustomValidity('Semua Variant Menu ini sudah punya Recipe.');
                }

                if (keepId && findVariant(menu, keepId) && !findVariant(menu, keepId).existing) {
                    variantSelect.value = String(keepId);
                }

                renderExisting(menu);
                renderPreview();
            }

            menus.forEach(function (menu) {
                productSelect.appendChild(option(menu.id, menu.name));
            });

            // Legacy/failed submit with only a Variant id: recover its Menu from the list.
            if (!oldProduct && oldVariant) {
                menus.forEach(function (menu) {
                    if (findVariant(menu, oldVariant)) { oldProduct = String(menu.id); }
                });
            }

            if (oldProduct && findMenu(oldProduct)) {
                productSelect.value = String(oldProduct);
            }

            renderVariants(oldVariant);

            // Changing the Menu always resets the Variant.
            productSelect.addEventListener('change', function () { renderVariants(null); });
            variantSelect.addEventListener('change', renderPreview);
        })();
    </script>
    @include('backoffice.partials.feedback')
@include('backoffice.partials.button-system')
</body>
</html>