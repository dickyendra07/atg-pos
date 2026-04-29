<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Production - Back Office ATG POS</title>
    <style>
        :root {
            --text: #111827;
            --muted: #6b7280;
            --brand: #e86a3a;
            --blue: #3730a3;
            --blue-soft: #eef2ff;
            --red: #9b1c1c;
            --red-soft: #ffe8e8;
            --shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
            --green-soft: #e8fff1;
            --green-text: #17663a;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
            color: var(--text);
        }

        .page { min-height: 100vh; padding: 24px; }
        .shell {
            max-width: 1280px;
            margin: 0 auto;
            background: rgba(255,255,255,0.62);
            border: 1px solid rgba(255,255,255,0.9);
            border-radius: 30px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            padding: 28px 28px 0;
        }
        .title {
            margin: 0;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }
        .subtitle {
            margin-top: 10px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }
        .btn {
            text-decoration: none;
            border: 0;
            cursor: pointer;
            color: white;
            padding: 11px 16px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            box-shadow: 0 10px 22px rgba(15,23,42,0.10);
        }
        .btn-primary { background: linear-gradient(135deg, #166534 0%, #1f7a44 100%); }
        .btn-dark { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); }

        .content { padding: 22px 28px 30px; }

        .alert {
            margin-bottom: 18px;
            border-radius: 16px;
            padding: 15px 18px;
            font-size: 14px;
            line-height: 1.7;
            font-weight: 700;
            background: var(--red-soft);
            color: var(--red);
            border: 1px solid #fecaca;
        }

        .hero-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff9f5 70%, #fff1ea 100%);
            border: 1px solid #f0e1d8;
            border-radius: 28px;
            padding: 24px;
            margin-bottom: 22px;
        }

        .hero-kicker {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #f2dfd4;
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .hero-heading {
            margin: 0 0 10px;
            font-size: 30px;
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -0.03em;
        }

        .hero-text {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .card {
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-head {
            padding: 22px 22px 0;
        }

        .card-title {
            margin: 0 0 8px;
            font-size: 22px;
            font-weight: 800;
        }

        .card-subtitle {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .card-body {
            padding: 0 22px 22px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #374151;
        }

        .field input,
        .field select,
        .field textarea {
            width: 100%;
            min-height: 50px;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 0 14px;
            font-size: 14px;
            background: white;
            color: #111827;
            outline: none;
        }

        .field textarea {
            min-height: 92px;
            padding-top: 12px;
            padding-bottom: 12px;
            resize: vertical;
        }

        .actions-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .preview-box {
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            background: #f8fafc;
            padding: 16px;
        }

        .preview-line {
            font-size: 14px;
            line-height: 1.8;
            margin-bottom: 8px;
        }

        .preview-label {
            color: #6b7280;
            font-weight: 700;
            margin-right: 6px;
        }

        .preview-items {
            display: grid;
            gap: 10px;
            margin-top: 12px;
        }

        .preview-item {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 14px;
            line-height: 1.7;
        }

        .ok-box {
            margin-top: 16px;
            background: var(--green-soft);
            color: var(--green-text);
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #ccefd8;
            line-height: 1.7;
        }

        @media (max-width: 980px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@php
    $recipesJson = json_encode(
        $recipes->mapWithKeys(function ($recipe) {
            return [
                $recipe->id => [
                    'id' => $recipe->id,
                    'name' => $recipe->name,
                    'output_ingredient_name' => $recipe->outputIngredient->name ?? '-',
                    'output_qty' => (float) $recipe->output_qty,
                    'output_unit' => $recipe->output_unit,
                    'items' => $recipe->items->map(function ($item) {
                        return [
                            'ingredient_name' => $item->inputIngredient->name ?? '-',
                            'qty' => (float) $item->qty,
                            'unit' => $item->unit,
                        ];
                    })->values()->all(),
                ],
            ];
        })->toArray(),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    );
@endphp

<div class="page">
    <div class="shell">
        <div class="topbar">
            <div>
                <h1 class="title">Buat Produksi</h1>

            </div>

            <a href="{{ route('backoffice.productions.index') }}" class="btn btn-dark">Kembali</a>
        </div>

        <div class="content">
            @if($errors->any())
                <div class="alert">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach($errors->all() as $error)
                            <li style="margin-bottom:6px;">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif


            <div class="grid-2">
                <div class="card">
                    <div class="card-head">
                        <h2 class="card-title">Form Produksi</h2>

                    </div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('backoffice.productions.store') }}">
                            @csrf

                            <div class="field">
                                <label>Recipe Produksi</label>
                                <select name="ingredient_production_recipe_id" id="ingredient_production_recipe_id" required>
                                    <option value="">Pilih recipe produksi</option>
                                    @foreach($recipes as $recipe)
                                        <option value="{{ $recipe->id }}" @selected(old('ingredient_production_recipe_id') == $recipe->id)>
                                            {{ $recipe->name }} → {{ $recipe->outputIngredient->name ?? '-' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label>Location Type</label>
                                <select name="location_type" id="location_type" required>
                                    <option value="">Pilih location type</option>
                                    <option value="warehouse" @selected(old('location_type') === 'warehouse')>Warehouse</option>
                                    <option value="outlet" @selected(old('location_type') === 'outlet')>Outlet</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>Location</label>
                                <select name="location_id" id="location_id" required>
                                    <option value="">Pilih lokasi</option>
                                </select>
                            </div>

                            <div class="field">
                                <label>Batch Qty</label>
                                <input type="number" name="batch_qty" id="batch_qty" min="0.01" step="0.01" value="{{ old('batch_qty', 1) }}" required>
                            </div>

                            <div class="field">
                                <label>Catatan</label>
                                <textarea name="note" placeholder="Catatan produksi (opsional)">{{ old('note') }}</textarea>
                            </div>

                            <div class="actions-row">
                                <button type="submit" class="btn btn-primary">Simpan Produksi</button>
                                <a href="{{ route('backoffice.productions.index') }}" class="btn btn-dark">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h2 class="card-title">Preview Recipe</h2>

                    </div>

                    <div class="card-body">
                        <div class="preview-box">
                            <div class="preview-line"><span class="preview-label">Recipe:</span><span id="preview-recipe-name">-</span></div>
                            <div class="preview-line"><span class="preview-label">Output:</span><span id="preview-output-name">-</span></div>
                            <div class="preview-line"><span class="preview-label">Batch Qty:</span><span id="preview-batch-qty">1</span></div>
                            <div class="preview-line"><span class="preview-label">Total Output:</span><span id="preview-output-total">-</span></div>

                            <div class="preview-items" id="preview-items">
                                <div class="preview-item">Pilih recipe dulu untuk melihat kebutuhan bahan.</div>
                            </div>
                        </div>

                        <div class="ok-box">
                            Produksi hanya akan berhasil kalau semua bahan mentah di lokasi terpilih punya stok cukup.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const recipesMap = {!! $recipesJson !!};

    const locationTypeEl = document.getElementById('location_type');
    const locationIdEl = document.getElementById('location_id');
    const recipeEl = document.getElementById('ingredient_production_recipe_id');
    const batchQtyEl = document.getElementById('batch_qty');

    const previewRecipeName = document.getElementById('preview-recipe-name');
    const previewOutputName = document.getElementById('preview-output-name');
    const previewBatchQty = document.getElementById('preview-batch-qty');
    const previewOutputTotal = document.getElementById('preview-output-total');
    const previewItems = document.getElementById('preview-items');

    const warehouseOptions = [
        @foreach($warehouses as $warehouse)
        { value: '{{ $warehouse->id }}', label: 'Warehouse - {{ addslashes($warehouse->name) }}' },
        @endforeach
    ];

    const outletOptions = [
        @foreach($outlets as $outlet)
        { value: '{{ $outlet->id }}', label: 'Outlet - {{ addslashes($outlet->name) }}' },
        @endforeach
    ];

    const oldLocationId = @json(old('location_id'));

    function formatNumber(value) {
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(Number(value || 0));
    }

    function rebuildLocationOptions() {
        const type = locationTypeEl.value;
        const options = type === 'warehouse' ? warehouseOptions : type === 'outlet' ? outletOptions : [];

        locationIdEl.innerHTML = '<option value="">Pilih lokasi</option>';

        options.forEach((option) => {
            const el = document.createElement('option');
            el.value = option.value;
            el.textContent = option.label;

            if (String(oldLocationId || '') === String(option.value)) {
                el.selected = true;
            }

            locationIdEl.appendChild(el);
        });
    }

    function renderPreview() {
        const recipeId = recipeEl.value;
        const batchQty = Number(batchQtyEl.value || 0) || 0;
        const recipe = recipesMap[recipeId];

        if (!recipe) {
            previewRecipeName.textContent = '-';
            previewOutputName.textContent = '-';
            previewBatchQty.textContent = batchQty > 0 ? formatNumber(batchQty) : '1';
            previewOutputTotal.textContent = '-';
            previewItems.innerHTML = '<div class="preview-item">Pilih recipe dulu untuk melihat kebutuhan bahan.</div>';
            return;
        }

        previewRecipeName.textContent = recipe.name || '-';
        previewOutputName.textContent = recipe.output_ingredient_name || '-';
        previewBatchQty.textContent = formatNumber(batchQty || 1);
        previewOutputTotal.textContent = formatNumber((recipe.output_qty || 0) * (batchQty || 1)) + ' ' + (recipe.output_unit || '');

        if (!recipe.items || !recipe.items.length) {
            previewItems.innerHTML = '<div class="preview-item">Recipe ini belum punya bahan input.</div>';
            return;
        }

        previewItems.innerHTML = recipe.items.map((item) => {
            const totalQty = (Number(item.qty || 0) * (batchQty || 1));
            return '<div class="preview-item"><strong>' + item.ingredient_name + '</strong><br>Kebutuhan: ' + formatNumber(totalQty) + ' ' + item.unit + '</div>';
        }).join('');
    }

    locationTypeEl.addEventListener('change', rebuildLocationOptions);
    recipeEl.addEventListener('change', renderPreview);
    batchQtyEl.addEventListener('input', renderPreview);

    window.addEventListener('DOMContentLoaded', function () {
        rebuildLocationOptions();
        renderPreview();
    });
</script>
</body>
</html>