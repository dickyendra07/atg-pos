<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penerimaan Barang - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 1080px;
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

        .btn-success {
            background: #166534;
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
            background: #fff7ed;
            color: #b45309;
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

        .item-row-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.7fr 1.1fr auto;
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

        @media (max-width: 900px) {
            .item-row-grid {
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
                <div class="title">Penerimaan Barang</div>
                <div class="subtitle">
                    Gunakan form ini untuk barang yang masuk dari luar sistem internal, misalnya dari supplier, vendor, atau pembelian langsung ke warehouse maupun outlet.
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
                Penerimaan Barang dipakai untuk stok yang datang dari luar. Kalau barang pindah dari warehouse atau outlet lain, gunakan menu <strong>Transfers</strong>, bukan form ini.
            </div>

            <form method="POST" action="{{ route('backoffice.stock-balances.store') }}">
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
                        <div class="items-subtitle">Bisa input banyak bahan sekaligus dalam satu submit.</div>
                    </div>
                    <button type="button" class="btn btn-add" id="add-item-btn">Tambah Baris</button>
                </div>

                <div id="items-wrapper"></div>

                <div class="actions">
                    <button type="submit" class="btn btn-success">Simpan Penerimaan Barang</button>
                    <a href="{{ route('backoffice.stock-balances.index') }}" class="btn">Batal</a>
                </div>
            </form>

            <div class="note">
                Penerimaan Barang bulk akan menambah <strong>qty_on_hand</strong> dan otomatis mencatat <strong>stock movement</strong> tipe <strong>stock_in</strong> untuk setiap baris item pada lokasi tujuan yang dipilih.
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
                    <label>Note</label>
                    <input type="text" class="note-input" placeholder="contoh: Pembelian supplier pagi">
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

            const ingredientOptions = [
                { value: '', label: 'Pilih ingredient' },
                @foreach($ingredients as $ingredient)
                    { value: '{{ $ingredient->id }}', label: @json($ingredient->name . ' (' . $ingredient->unit . ')') },
                @endforeach
            ];

            const oldItems = @json(old('items', []));
            const locationOptions = Array.from(locationSelect.querySelectorAll('option'));

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

            function refreshRowNames() {
                const rows = itemsWrapper.querySelectorAll('.item-row');
                rows.forEach(function (row, index) {
                    row.querySelector('.item-number').textContent = index + 1;
                    row.querySelector('.ingredient-select').name = `items[${index}][ingredient_id]`;
                    row.querySelector('.qty-input').name = `items[${index}][qty_in]`;
                    row.querySelector('.note-input').name = `items[${index}][note]`;
                });
            }

            function addRow(data = {}) {
                const fragment = template.content.cloneNode(true);
                const row = fragment.querySelector('.item-row');
                const ingredientSelect = row.querySelector('.ingredient-select');
                const qtyInput = row.querySelector('.qty-input');
                const noteInput = row.querySelector('.note-input');
                const removeBtn = row.querySelector('.remove-item-btn');

                buildIngredientSelect(ingredientSelect, data.ingredient_id || '');
                qtyInput.value = data.qty_in || '';
                noteInput.value = data.note || '';

                removeBtn.addEventListener('click', function () {
                    row.remove();
                    refreshRowNames();
                    ensureAtLeastOneRow();
                });

                itemsWrapper.appendChild(row);
                refreshRowNames();
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
</body>
</html>