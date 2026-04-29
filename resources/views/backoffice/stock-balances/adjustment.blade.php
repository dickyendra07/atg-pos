@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Adjustment Stok - Back Office';
@endphp

@section('content')
    <style>
        .adjustment-shell {
            display: grid;
            gap: 22px;
        }

        .adjustment-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .adjustment-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .adjustment-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #f3dfcf;
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .adjustment-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .adjustment-subtitle {
            margin: 0;
            max-width: 860px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .adjustment-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            border: 0;
            cursor: pointer;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 14px;
            color: white;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(15,23,42,0.10);
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.96;
        }

        .btn-dark {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .btn-warning {
            background: linear-gradient(135deg, #92400e 0%, #b45309 100%);
        }

        .btn-add {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
        }

        .btn-danger-lite {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        }

        .alert {
            border-radius: 18px;
            padding: 16px 18px;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.7;
        }

        .alert-error {
            background: #ffe8e8;
            color: #9b1c1c;
            border: 1px solid #fecaca;
        }

        .card {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 30px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .hero-wrap {
            padding: 24px 24px 0;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 20px;
        }

        .hero-card {
            background: linear-gradient(135deg, #ffffff 0%, #fffaf5 58%, #fff4eb 100%);
            border: 1px solid #f0e1d8;
            border-radius: 28px;
            padding: 24px;
            position: relative;
            overflow: hidden;
        }

        .hero-card::after {
            content: "";
            position: absolute;
            right: -50px;
            top: -50px;
            width: 180px;
            height: 180px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(201,85,42,0.14) 0%, rgba(201,85,42,0.03) 65%, rgba(201,85,42,0) 80%);
            pointer-events: none;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
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
            position: relative;
            z-index: 1;
        }

        .hero-heading {
            margin: 0 0 10px;
            font-size: 34px;
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.03em;
            color: #111827;
            position: relative;
            z-index: 1;
        }

        .hero-text {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
            max-width: 760px;
            position: relative;
            z-index: 1;
        }

        .rule-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e8edf4;
            border-radius: 28px;
            padding: 24px;
        }

        .rule-title {
            margin: 0 0 14px;
            font-size: 18px;
            font-weight: 800;
            color: #111827;
        }

        .rule-line {
            font-size: 14px;
            line-height: 1.9;
            color: #374151;
        }

        .rule-line strong {
            color: #111827;
        }

        .section-card {
            margin: 20px 24px 24px;
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }

        .section-head {
            padding: 22px 22px 0;
        }

        .section-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .section-subtitle {
            margin: 0 0 18px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
        }

        .helper-box {
            margin: 0 22px 18px;
            background: #fff7ed;
            color: #b45309;
            padding: 16px 18px;
            border-radius: 18px;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.8;
            border: 1px solid #fed7aa;
        }

        .form-wrap {
            padding: 0 22px 22px;
        }

        .top-fields-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 18px;
        }

        .field {
            margin-bottom: 0;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .field input,
        .field select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
            padding: 12px 14px;
            font-size: 14px;
            min-height: 48px;
            outline: none;
        }

        .field input:focus,
        .field select:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .muted {
            color: #6b7280;
            font-size: 13px;
            margin-top: 8px;
            line-height: 1.7;
        }

        .items-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 22px 0 16px;
            flex-wrap: wrap;
        }

        .items-title {
            font-size: 20px;
            font-weight: 800;
            color: #111827;
        }

        .items-subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-top: 4px;
            line-height: 1.7;
        }

        .item-row {
            border: 1px solid #e8edf4;
            border-radius: 20px;
            padding: 18px;
            margin-bottom: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
        }

        .row-index {
            display: inline-flex;
            margin-bottom: 12px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #374151;
            font-size: 12px;
            font-weight: 700;
        }

        .item-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.85fr 0.85fr 0.95fr 1.15fr auto;
            gap: 12px;
            align-items: end;
        }

        .readonly-box {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            padding: 12px 14px;
            font-size: 14px;
            background: #f9fafb;
            color: #374151;
            min-height: 48px;
            display: flex;
            align-items: center;
            font-weight: 700;
        }

        .delta-up {
            color: #166534;
            font-weight: 800;
        }

        .delta-down {
            color: #b91c1c;
            font-weight: 800;
        }

        .delta-equal {
            color: #6b7280;
            font-weight: 800;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .note {
            margin-top: 22px;
            background: #fef3c7;
            color: #92400e;
            padding: 16px 18px;
            border-radius: 18px;
            font-weight: 700;
            line-height: 1.8;
            border: 1px solid #fcd34d;
        }

        @media (max-width: 1320px) {
            .hero-wrap {
                grid-template-columns: 1fr;
            }

            .item-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 860px) {
            .adjustment-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .adjustment-title {
                font-size: 32px;
            }

            .hero-heading {
                font-size: 28px;
            }

            .top-fields-grid,
            .item-grid {
                grid-template-columns: 1fr;
            }

            .hero-wrap {
                padding-left: 18px;
                padding-right: 18px;
            }

            .section-card {
                margin-left: 18px;
                margin-right: 18px;
            }
        }
    </style>

    <div class="adjustment-shell">
        <div class="adjustment-topbar">
            <div class="adjustment-title-block">

                <h1 class="adjustment-title">Adjustment Stok</h1>

            </div>

            <div class="adjustment-actions">
                <a href="{{ route('backoffice.stock-balances.index') }}" class="btn btn-dark">Kembali</a>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-error">
                <div>Form belum valid:</div>
                <ul style="margin:10px 0 0 18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">

                    <div class="rule-line"><strong>Outlet:</strong> boleh adjustment</div>
                    <div class="rule-line"><strong>Selisih positif:</strong> sistem tambah stok</div>
                    <div class="rule-line"><strong>Selisih negatif:</strong> sistem kurangi stok</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">Form Adjustment</h2>

                </div>


                <div class="form-wrap">
                    <form method="POST" action="{{ route('backoffice.stock-balances.adjustment.store') }}">
                        @csrf

                        <div class="top-fields-grid">
                            <div class="field">
                                <label for="location_type">Tipe Lokasi</label>
                                <select name="location_type" id="location_type" required>
                                    <option value="">Pilih tipe lokasi</option>
                                    <option value="warehouse" {{ old('location_type') === 'warehouse' ? 'selected' : '' }}>Warehouse</option>
                                    <option value="outlet" {{ old('location_type') === 'outlet' ? 'selected' : '' }}>Outlet</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="location_id">Lokasi</label>
                                <select name="location_id" id="location_id" required>
                                    <option value="">Pilih lokasi</option>

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
                        </div>

                        <div class="items-head">
                            <div>
                                <div class="items-title">Daftar Item Adjustment</div>

                            </div>
                            <button type="button" class="btn btn-add" id="add-item-btn">Tambah Baris</button>
                        </div>

                        <div id="items-wrapper"></div>

                        <div class="actions">
                            <button type="submit" class="btn btn-warning">Simpan Adjustment</button>
                            <a href="{{ route('backoffice.stock-balances.index') }}" class="btn btn-dark">Batal</a>
                        </div>
                    </form>

                    <div class="note">
                        Sistem akan membandingkan <strong>stok sistem saat ini</strong> dengan <strong>stok aktual</strong> yang kamu isi. Kalau ada selisih, sistem otomatis membuat <strong>stock movement adjustment</strong> sesuai hasil koreksi.
                    </div>
                </div>
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
                    <div class="readonly-box current-stock-box">Rp 0</div>
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
                    <input type="text" class="note-input" placeholder="Contoh: selisih hitung / bahan rusak" required>
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
                    return new Intl.NumberFormat('id-ID', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 2
                    }).format(Number(value || 0));
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
                        opt.textContent = item.name + ' (' + item.unit + ') — Stok saat ini: ' + formatNumber(stock);
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
@endsection