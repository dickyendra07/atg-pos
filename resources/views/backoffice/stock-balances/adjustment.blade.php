<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adjustment - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 1180px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            gap: 16px;
        }

        .title {
            font-size: 30px;
            font-weight: bold;
        }

        .subtitle {
            margin-top: 6px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
            max-width: 760px;
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

        .btn-warning {
            background: #92400e;
        }

        .btn-add {
            background: #1d4ed8;
        }

        .btn-danger-lite {
            background: #dc2626;
        }

        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
        }

        .helper {
            margin-bottom: 18px;
            background: #fef3c7;
            color: #92400e;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
            line-height: 1.7;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 6px;
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
        }

        .readonly-box {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px;
            font-size: 14px;
            background: #f9fafb;
            color: #374151;
            min-height: 46px;
            display: flex;
            align-items: center;
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
            background: #fef3c7;
            color: #92400e;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
            line-height: 1.7;
        }

        .muted {
            color: #6b7280;
            font-size: 13px;
            margin-top: 6px;
        }

        .items-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 20px 0 14px;
        }

        .items-title {
            font-size: 18px;
            font-weight: 700;
            color: #111827;
        }

        .items-subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-top: 4px;
        }

        .item-row {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 14px;
            background: #fafafa;
        }

        .row-index {
            display: inline-flex;
            margin-bottom: 10px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #374151;
            font-size: 12px;
            font-weight: 700;
        }

        .item-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.85fr 0.85fr 0.9fr 1.1fr auto;
            gap: 12px;
            align-items: end;
        }

        .delta-up {
            color: #166534;
            font-weight: 700;
        }

        .delta-down {
            color: #b91c1c;
            font-weight: 700;
        }

        .delta-equal {
            color: #6b7280;
            font-weight: 700;
        }

        @media (max-width: 1100px) {
            .item-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div>
                <div class="title">Adjustment</div>
                <div class="subtitle">
                    Gunakan form ini untuk menyesuaikan stok berdasarkan kondisi aktual di warehouse atau outlet. Isi stok aktual, lalu sistem otomatis menghitung selisihnya.
                </div>
            </div>
            <a href="{{ route('backoffice.stock-balances.index') }}" class="btn">Kembali</a>
        </div>

        @if($errors->any())
            <div class="error-box">
                <div>Form belum valid:</div>
                <ul style="margin:10px 0 0 18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="helper">
                Adjustment dipakai untuk koreksi stok di lokasi terpilih. Isi jumlah stok aktual, lalu sistem akan otomatis menghitung selisih dari stok sistem saat ini.
            </div>

            <form method="POST" action="{{ route('backoffice.stock-balances.adjustment.store') }}">
                @csrf

                <div class="field">
                    <label>Tipe Lokasi Tujuan</label>
                    <select name="location_type" id="location_type" required>
                        <option value="">Pilih tipe lokasi</option>
                        <option value="warehouse" {{ old('location_type') === 'warehouse' ? 'selected' : '' }}>Warehouse</option>
                        <option value="outlet" {{ old('location_type') === 'outlet' ? 'selected' : '' }}>Outlet</option>
                    </select>
                </div>

                <div class="field">
                    <label>Lokasi Tujuan</label>
                    <select name="location_id" id="location_id" required>
                        <option value="">Pilih lokasi tujuan</option>

                        @foreach($warehouses as $warehouse)
                            <option
                                value="{{ $warehouse->id }}"
                                data-type="warehouse"
                                data-label="Warehouse - {{ $warehouse->name }}"
                                {{ old('location_type') === 'warehouse' && (string) old('location_id') === (string) $warehouse->id ? 'selected' : '' }}
                            >
                                Warehouse - {{ $warehouse->name }}
                            </option>
                        @endforeach

                        @foreach($outlets as $outlet)
                            <option
                                value="{{ $outlet->id }}"
                                data-type="outlet"
                                data-label="Outlet - {{ $outlet->name }}"
                                {{ old('location_type') === 'outlet' && (string) old('location_id') === (string) $outlet->id ? 'selected' : '' }}
                            >
                                Outlet - {{ $outlet->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="muted">Pilih dulu tipe lokasi, lalu pilih lokasi yang stoknya ingin disesuaikan.</div>
                </div>

                <div class="items-head">
                    <div>
                        <div class="items-title">Daftar Item Adjustment</div>
                        <div class="items-subtitle">Bisa input banyak bahan sekaligus dalam satu submit.</div>
                    </div>
                    <button type="button" class="btn btn-add" id="add-item-btn">Tambah Baris</button>
                </div>

                <div id="items-wrapper"></div>

                <div class="actions">
                    <button type="submit" class="btn btn-warning">Simpan Adjustment</button>
                    <a href="{{ route('backoffice.stock-balances.index') }}" class="btn">Batal</a>
                </div>
            </form>

            <div class="note">
                Sistem akan membandingkan <strong>stok sistem saat ini</strong> dengan <strong>stok aktual</strong> yang kamu input, lalu otomatis membuat movement adjustment sesuai selisihnya.
            </div>
        </div>
    </div>

    <template id="item-row-template">
        <div class="item-row">
            <div class="row-index">Item <span class="item-number"></span></div>

            <div class="item-grid">
                <div class="field" style="margin-bottom:0;">
                    <label>Ingredient</label>
                    <select class="ingredient-select" required></select>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>Stok Sistem Saat Ini</label>
                    <div class="readonly-box current-stock-box">-</div>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>Stok Aktual</label>
                    <input type="number" class="actual-qty-input" min="0" step="0.01" required>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>Selisih</label>
                    <div class="readonly-box delta-box delta-equal">0</div>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>Keterangan</label>
                    <input type="text" class="note-input" placeholder="contoh: Selisih hitung / bahan rusak" required>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-danger-lite remove-item-btn">Hapus</button>
                </div>
            </div>
        </div>
    </template>

    <script>
        (function () {
            const typeSelect = document.getElementById('location_type');
            const locationSelect = document.getElementById('location_id');
            const itemsWrapper = document.getElementById('items-wrapper');
            const addItemBtn = document.getElementById('add-item-btn');
            const template = document.getElementById('item-row-template');

            const stockMap = @json($stockMap);
            const oldItems = @json(old('items', []));
            const locationOptions = Array.from(locationSelect.querySelectorAll('option'));

            const ingredientOptions = [
                { value: '', label: 'Pilih ingredient', name: '', unit: '' },
                @foreach($ingredients as $ingredient)
                    {
                        value: '{{ $ingredient->id }}',
                        label: @json($ingredient->name . ' (' . $ingredient->unit . ')'),
                        name: @json($ingredient->name),
                        unit: @json($ingredient->unit)
                    },
                @endforeach
            ];

            function formatNumber(value) {
                try {
                    return new Intl.NumberFormat('id-ID').format(Number(value || 0));
                } catch (e) {
                    return String(value || 0);
                }
            }

            function filterLocationOptions() {
                const selectedType = typeSelect.value;

                locationOptions.forEach(function (option, index) {
                    if (index === 0) {
                        option.hidden = false;
                        return;
                    }

                    option.hidden = selectedType !== '' && option.getAttribute('data-type') !== selectedType;
                });

                const selectedOption = locationSelect.options[locationSelect.selectedIndex];
                if (selectedOption && selectedOption.hidden) {
                    locationSelect.selectedIndex = 0;
                }
            }

            function getLocationStockMap() {
                const locationType = typeSelect.value;
                const locationId = locationSelect.value;
                const mapKey = locationType && locationId ? locationType + ':' + locationId : null;
                return mapKey && stockMap[mapKey] ? stockMap[mapKey] : {};
            }

            function buildIngredientSelect(selectEl, selectedValue = '') {
                const locationStock = getLocationStockMap();
                selectEl.innerHTML = '';

                ingredientOptions.forEach(function (item) {
                    const opt = document.createElement('option');
                    opt.value = item.value;

                    if (!item.value) {
                        opt.textContent = item.label;
                    } else {
                        const stock = Object.prototype.hasOwnProperty.call(locationStock, item.value)
                            ? locationStock[item.value]
                            : 0;
                        opt.textContent = item.name + ' (' + item.unit + ') — Stock saat ini: ' + formatNumber(stock);
                        opt.setAttribute('data-name', item.name);
                        opt.setAttribute('data-unit', item.unit);
                    }

                    if (String(selectedValue) === String(item.value)) {
                        opt.selected = true;
                    }

                    selectEl.appendChild(opt);
                });
            }

            function updateRowComputed(row) {
                const ingredientSelect = row.querySelector('.ingredient-select');
                const actualQtyInput = row.querySelector('.actual-qty-input');
                const currentStockBox = row.querySelector('.current-stock-box');
                const deltaBox = row.querySelector('.delta-box');

                const locationStock = getLocationStockMap();
                const ingredientId = ingredientSelect.value;

                let currentStock = 0;
                if (ingredientId && Object.prototype.hasOwnProperty.call(locationStock, ingredientId)) {
                    currentStock = Number(locationStock[ingredientId] || 0);
                }

                const actualQty = Number(actualQtyInput.value || 0);
                const delta = actualQty - currentStock;

                currentStockBox.textContent = formatNumber(currentStock);

                deltaBox.classList.remove('delta-up', 'delta-down', 'delta-equal');

                if (delta > 0) {
                    deltaBox.textContent = '+' + formatNumber(delta);
                    deltaBox.classList.add('delta-up');
                } else if (delta < 0) {
                    deltaBox.textContent = '-' + formatNumber(Math.abs(delta));
                    deltaBox.classList.add('delta-down');
                } else {
                    deltaBox.textContent = '0';
                    deltaBox.classList.add('delta-equal');
                }
            }

            function refreshAllIngredientLabels() {
                const rows = itemsWrapper.querySelectorAll('.item-row');
                rows.forEach(function (row) {
                    const selectEl = row.querySelector('.ingredient-select');
                    const currentValue = selectEl.value;
                    buildIngredientSelect(selectEl, currentValue);
                    updateRowComputed(row);
                });
            }

            function refreshRowNames() {
                const rows = itemsWrapper.querySelectorAll('.item-row');
                rows.forEach(function (row, index) {
                    row.querySelector('.item-number').textContent = index + 1;
                    row.querySelector('.ingredient-select').name = 'items[' + index + '][ingredient_id]';
                    row.querySelector('.actual-qty-input').name = 'items[' + index + '][actual_qty]';
                    row.querySelector('.note-input').name = 'items[' + index + '][note]';
                });
            }

            function addRow(data = {}) {
                const fragment = template.content.cloneNode(true);
                const row = fragment.querySelector('.item-row');
                const ingredientSelect = row.querySelector('.ingredient-select');
                const actualQtyInput = row.querySelector('.actual-qty-input');
                const noteInput = row.querySelector('.note-input');
                const removeBtn = row.querySelector('.remove-item-btn');

                buildIngredientSelect(ingredientSelect, data.ingredient_id || '');
                actualQtyInput.value = data.actual_qty || '';
                noteInput.value = data.note || '';

                ingredientSelect.addEventListener('change', function () {
                    updateRowComputed(row);
                });

                actualQtyInput.addEventListener('input', function () {
                    updateRowComputed(row);
                });

                removeBtn.addEventListener('click', function () {
                    row.remove();
                    refreshRowNames();
                    ensureAtLeastOneRow();
                });

                itemsWrapper.appendChild(row);
                refreshRowNames();
                updateRowComputed(row);
            }

            function ensureAtLeastOneRow() {
                if (!itemsWrapper.querySelector('.item-row')) {
                    addRow();
                }
            }

            addItemBtn.addEventListener('click', function () {
                addRow();
            });

            typeSelect.addEventListener('change', function () {
                filterLocationOptions();
                locationSelect.selectedIndex = 0;
                refreshAllIngredientLabels();
            });

            locationSelect.addEventListener('change', function () {
                refreshAllIngredientLabels();
            });

            filterLocationOptions();

            if (oldItems && oldItems.length) {
                oldItems.forEach(function (item) {
                    addRow(item);
                });
            } else {
                addRow();
            }
        })();
    </script>
</body>
</html>