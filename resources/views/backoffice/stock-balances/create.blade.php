@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Purchase Order - Back Office';
@endphp

@section('content')
    <style>
        .receive-shell {
            display: grid;
            gap: 22px;
        }

        .receive-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .receive-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .receive-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #f3d7c9;
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .receive-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .receive-subtitle {
            margin: 0;
            max-width: 860px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .receive-actions {
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

        .btn-brand {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        }

        .btn-green {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
        }

        .btn-blue {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
        }

        .btn-red {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        }

        .alert {
            border-radius: 18px;
            padding: 16px 18px;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.7;
        }

        .alert-success {
            background: #e8fff1;
            color: #17663a;
            border: 1px solid #ccefd8;
        }

        .alert-error {
            background: #ffe8e8;
            color: #9b1c1c;
            border: 1px solid #fecaca;
        }

        .page-card {
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 30px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .hero-wrap {
            padding: 24px 24px 0;
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            gap: 20px;
        }

        .hero-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff9f5 58%, #fff1ea 100%);
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
            background: radial-gradient(circle, rgba(232,106,58,0.14) 0%, rgba(232,106,58,0.03) 65%, rgba(232,106,58,0) 80%);
            pointer-events: none;
        }

        .hero-chip {
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
            background: rgba(255,255,255,0.96);
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
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            line-height: 1.8;
            border: 1px solid #fed7aa;
        }

        .form-wrap {
            padding: 0 22px 22px;
        }

        .field {
            margin-bottom: 16px;
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
            min-height: 50px;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 0 14px;
            font-size: 14px;
            background: white;
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
            margin-top: 6px;
            line-height: 1.7;
        }

        .items-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 20px 0 14px;
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
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 16px;
            margin-bottom: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #fafafa 100%);
        }

        .item-row-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr 0.9fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
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

        .readonly-box {
            width: 100%;
            box-sizing: border-box;
            min-height: 50px;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 0 14px;
            font-size: 14px;
            background: #f9fafb;
            color: #111827;
            display: flex;
            align-items: center;
            font-weight: 700;
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
            border-radius: 14px;
            font-weight: 700;
            line-height: 1.8;
            border: 1px solid #dbe3ff;
        }

        @media (max-width: 1200px) {
            .hero-wrap {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 900px) {
            .item-row-grid {
                grid-template-columns: 1fr;
            }

            .receive-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .receive-title {
                font-size: 32px;
            }

            .hero-heading {
                font-size: 28px;
            }
        }
    </style>

    <div class="receive-shell">
        <div class="receive-topbar">
            <div class="receive-title-block">

                <h1 class="receive-title">Purchase Order</h1>

            </div>

            <div class="receive-actions">
                <a href="{{ route('backoffice.stock-balances.index') }}" class="btn btn-dark">Kembali</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

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

        <div class="page-card">


                <div class="rule-card">
                    <h3 class="rule-title">Rule Penggunaan</h3>
                    <div class="rule-line"><strong>Penerimaan Barang:</strong> stok datang dari supplier / vendor / pembelian luar</div>
                    <div class="rule-line"><strong>Bukan untuk:</strong> perpindahan stok antar warehouse / outlet</div>
                    <div class="rule-line"><strong>Kalau antar lokasi:</strong> pakai menu <strong>Transfers</strong></div>
                    <div class="rule-line"><strong>Output:</strong> stock balance bertambah dan stock movement tercatat</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">Form Purchase Order</h2>

                </div>

                <div class="form-wrap">
                    <form method="POST" action="{{ route('backoffice.stock-balances.store') }}">
                        @csrf

                        <div class="field">
                            <label for="location_type">Tipe Lokasi Tujuan</label>
                            <select name="location_type" id="location_type" required>
                                <option value="">Pilih tipe lokasi</option>
                                <option value="warehouse" {{ old('location_type') === 'warehouse' ? 'selected' : '' }}>Warehouse</option>
                                <option value="outlet" {{ old('location_type') === 'outlet' ? 'selected' : '' }}>Outlet</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="location_id">Lokasi Tujuan</label>
                            <select name="location_id" id="location_id" required>
                                <option value="">Pilih lokasi tujuan</option>

                                @foreach($warehouses as $warehouse)
                                    <option
                                        value="{{ $warehouse->id }}"
                                        data-type="warehouse"
                                        {{ old('location_type') === 'warehouse' && (string) old('location_id') === (string) $warehouse->id ? 'selected' : '' }}
                                    >
                                        Warehouse - {{ $warehouse->name }}
                                    </option>
                                @endforeach

                                @foreach($outlets as $outlet)
                                    <option
                                        value="{{ $outlet->id }}"
                                        data-type="outlet"
                                        {{ old('location_type') === 'outlet' && (string) old('location_id') === (string) $outlet->id ? 'selected' : '' }}
                                    >
                                        Outlet - {{ $outlet->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="muted">Pilih dulu tipe lokasi, lalu pilih tujuan stok masuk.</div>
                        </div>

                        <div class="items-head">
                            <div>
                                <div class="items-title">Daftar Item Penerimaan</div>

                            </div>
                            <button type="button" class="btn btn-blue" id="add-item-btn">Tambah Baris</button>
                        </div>

                        <div id="items-wrapper"></div>

                        <div class="actions">
                            <button type="submit" class="btn btn-green">Simpan Penerimaan Barang</button>
                            <a href="{{ route('backoffice.stock-balances.index') }}" class="btn btn-dark">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <template id="item-row-template">
        <div class="item-row">
            <div class="row-index">Item <span class="item-number"></span></div>
            <div class="item-row-grid">
                <div class="field" style="margin-bottom:0;">
                    <label>Ingredient</label>
                    <select class="ingredient-select" required></select>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>Qty Masuk</label>
                    <input type="number" class="qty-input" min="0.01" step="0.01" required>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>Harga Satuan</label>
                    <input type="text" class="unit-price-display-input" inputmode="numeric" placeholder="Rp 0" required>
                    <input type="hidden" class="unit-price-input">
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>Total Harga</label>
                    <div class="readonly-box line-total-box">Rp 0</div>
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>Catatan</label>
                    <input type="text" class="note-input" placeholder="Contoh: Pembelian supplier pagi">
                </div>

                <div class="field" style="margin-bottom:0;">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-red remove-item-btn">Hapus</button>
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

            const ingredientOptions = [
                { value: '', label: 'Pilih ingredient' },
                @foreach($ingredients as $ingredient)
                    { value: '{{ $ingredient->id }}', label: @json($ingredient->name . ' (' . $ingredient->unit . ')') },
                @endforeach
            ];

            const oldItems = @json(old('items', []));
            const locationOptions = Array.from(locationSelect.querySelectorAll('option'));

            function formatRupiah(value) {
                return 'Rp ' + new Intl.NumberFormat('id-ID', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(Number(value || 0));
            }

            function parseRupiah(value) {
                const raw = String(value || '').replace(/[^\d]/g, '');
                return raw ? Number(raw) : 0;
            }

            function filterLocationOptions() {
                const selectedType = typeSelect.value;

                locationOptions.forEach((option, index) => {
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

            function buildIngredientSelect(selectEl, selectedValue = '') {
                selectEl.innerHTML = '';

                ingredientOptions.forEach(function (item) {
                    const opt = document.createElement('option');
                    opt.value = item.value;
                    opt.textContent = item.label;

                    if (String(selectedValue) === String(item.value)) {
                        opt.selected = true;
                    }

                    selectEl.appendChild(opt);
                });
            }

            function updateRowTotal(row) {
                const qtyInput = row.querySelector('.qty-input');
                const unitPriceInput = row.querySelector('.unit-price-input');
                const lineTotalBox = row.querySelector('.line-total-box');

                const qty = Number(qtyInput.value || 0);
                const unitPrice = Number(unitPriceInput.value || 0);
                const total = qty * unitPrice;

                lineTotalBox.textContent = formatRupiah(total);
            }

            function refreshRowNames() {
                const rows = itemsWrapper.querySelectorAll('.item-row');

                rows.forEach(function (row, index) {
                    row.querySelector('.item-number').textContent = index + 1;
                    row.querySelector('.ingredient-select').name = `items[${index}][ingredient_id]`;
                    row.querySelector('.qty-input').name = `items[${index}][qty_in]`;
                    row.querySelector('.unit-price-input').name = `items[${index}][unit_price]`;
                    row.querySelector('.note-input').name = `items[${index}][note]`;
                });
            }

            function addRow(data = {}) {
                const fragment = template.content.cloneNode(true);
                const row = fragment.querySelector('.item-row');
                const ingredientSelect = row.querySelector('.ingredient-select');
                const qtyInput = row.querySelector('.qty-input');
                const unitPriceInput = row.querySelector('.unit-price-input');
                const unitPriceDisplayInput = row.querySelector('.unit-price-display-input');
                const noteInput = row.querySelector('.note-input');
                const removeBtn = row.querySelector('.remove-item-btn');

                const unitPriceValue = Number(data.unit_price || 0);

                buildIngredientSelect(ingredientSelect, data.ingredient_id || '');
                qtyInput.value = data.qty_in || '';
                unitPriceInput.value = unitPriceValue > 0 ? unitPriceValue : '';
                unitPriceDisplayInput.value = unitPriceValue > 0 ? formatRupiah(unitPriceValue) : '';
                noteInput.value = data.note || '';

                qtyInput.addEventListener('input', function () {
                    updateRowTotal(row);
                });

                unitPriceDisplayInput.addEventListener('input', function () {
                    const parsed = parseRupiah(this.value);
                    unitPriceInput.value = parsed > 0 ? parsed : '';
                    this.value = parsed > 0 ? formatRupiah(parsed) : '';
                    updateRowTotal(row);
                });

                unitPriceDisplayInput.addEventListener('blur', function () {
                    const parsed = parseRupiah(this.value);
                    unitPriceInput.value = parsed > 0 ? parsed : '';
                    this.value = parsed > 0 ? formatRupiah(parsed) : '';
                    updateRowTotal(row);
                });

                removeBtn.addEventListener('click', function () {
                    row.remove();
                    refreshRowNames();
                    ensureAtLeastOneRow();
                });

                itemsWrapper.appendChild(row);
                refreshRowNames();
                updateRowTotal(row);
            }

            function ensureAtLeastOneRow() {
                if (!itemsWrapper.querySelector('.item-row')) {
                    addRow();
                }
            }

            addItemBtn.addEventListener('click', function () {
                addRow();
            });

            typeSelect.addEventListener('change', filterLocationOptions);

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