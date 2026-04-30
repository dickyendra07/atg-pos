@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Create Transfer - Back Office ATG POS';
@endphp

@section('content')
<style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
        }

        .wrap {
            max-width: 1160px;
            margin: 36px auto;
            padding: 0 20px 40px;
        }

        .title {
            font-size: 30px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 8px;
        }

        .subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .info, .error-box {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
        }

        .info {
            background: #eef2ff;
            border: 1px solid #dbe3ff;
            line-height: 1.75;
            color: #3730a3;
            font-weight: 700;
        }

        .error-box {
            background: #ffe8e8;
            color: #9b1c1c;
            border: 1px solid #fecaca;
            font-weight: 700;
        }

        .field {
            margin-bottom: 14px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
            color: #4b5563;
        }

        .field input,
        .field textarea,
        .field select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 12px 13px;
            font-size: 14px;
            background: white;
            color: #111827;
        }

        .field textarea {
            min-height: 90px;
            resize: vertical;
        }

        .field-error {
            margin-top: 6px;
            font-size: 12px;
            color: #b91c1c;
            font-weight: 700;
        }

        .muted {
            margin-top: 6px;
            font-size: 12px;
            color: #6b7280;
        }

        .items-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 20px 0 12px;
        }

        .items-title {
            font-size: 18px;
            font-weight: 800;
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
            grid-template-columns: minmax(0, 1.7fr) minmax(180px, 0.45fr) 120px;
            gap: 12px;
            align-items: end;
        }

        .stock-badge {
            margin-top: 8px;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.6;
            display: none;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .btn {
            text-decoration: none;
            border: 0;
            cursor: pointer;
            color: white;
            padding: 11px 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
        }

        .btn-primary { background: #166534; }
        .btn-dark { background: #111827; }
        .btn-add { background: #1d4ed8; }
        .btn-danger-lite { background: #dc2626; }

        .hint {
            margin-top: 18px;
            background: #fff7ed;
            color: #9a3412;
            padding: 12px 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.7;
        }

        @media (max-width: 960px) {
            .item-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <style>
        .wrap {
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }
    </style>


<div class="wrap">
        <div class="title">Buat Transfer</div>


        @if($errors->any())
            <div class="error-box">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <form method="POST" action="{{ route('backoffice.transfers.store') }}">
                @csrf

                <div class="field">
                    <label>Dari Lokasi</label>
                    <select name="from_location" id="from_location" required>
                        <option value="">Pilih lokasi asal</option>
                        @foreach($locationOptions as $option)
                            <option value="{{ $option['value'] }}" @selected(old('from_location', $prefillFromLocation) === $option['value'])>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('from_location')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field">
                    <label>Ke Lokasi</label>
                    <select name="to_location" id="to_location" required>
                        <option value="">Pilih lokasi tujuan</option>
                        @foreach($locationOptions as $option)
                            <option value="{{ $option['value'] }}" @selected(old('to_location') === $option['value'])>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('to_location')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field">
                    <label>Dikirim Oleh</label>
                    <input type="text" name="sender_name" value="{{ old('sender_name', $defaultSenderName) }}" required>
                    @error('sender_name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field">
                    <label>Diterima Oleh</label>
                    <input type="text" name="receiver_name" value="{{ old('receiver_name') }}" placeholder="Isi kalau penerima barang sudah diketahui">
                    @error('receiver_name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field full">
                    <label>Note Transfer</label>
                    <input type="text" name="note" value="{{ old('note') }}" placeholder="Contoh: pindah stok cabang / kirim bahan">
                    @error('note')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="items-head">
                    <div>
                        <div class="items-title">Daftar Item Transfer</div>

                    </div>
                    <button type="button" class="btn btn-add" id="add-item-btn">Tambah Baris</button>
                </div>

                <div id="items-wrapper"></div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Simpan Transfer</button>
                    <a href="{{ route('backoffice.transfers.index') }}" class="btn btn-dark">Batal</a>
                </div>

                <div class="hint" id="transfer_hint">
                    Transfer bulk akan membuat beberapa record transfer sekaligus, satu record per item, semuanya dengan status awal <strong>in transit</strong>.
                </div>
            </form>
        </div>
    </div>

    <template id="item-row-template">
        <div class="item-row">
            <div class="row-index">Item <span class="item-number"></span></div>

            <div class="item-grid">
                <div class="field item-field ingredient-field" style="margin-bottom:0;">
                    <label>Ingredient</label>
                    <select class="ingredient-select" required disabled>
                        <option value="">Pilih lokasi asal dulu</option>
                    </select>
                </div>

                <div class="field item-field qty-field" style="margin-bottom:0;">
                    <label>Qty Transfer</label>
                    <input type="number" class="qty-input" min="0.01" step="0.01" required>
                </div>

                <div class="field item-field action-field" style="margin-bottom:0;">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-danger-lite remove-item-btn">Hapus</button>
                </div>
            </div>
        </div>
    </template>

    <script>
        const fromLocationSelect = document.getElementById('from_location');
        const toLocationSelect = document.getElementById('to_location');
        const itemsWrapper = document.getElementById('items-wrapper');
        const addItemBtn = document.getElementById('add-item-btn');
        const transferHint = document.getElementById('transfer_hint');
        const template = document.getElementById('item-row-template');

        const oldItems = @json($oldItems ?? []);
        let availableIngredients = [];

        function resetTransferHint(message) {
            transferHint.innerHTML = message;
        }

        function createOption(value, label, selected = false) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            if (selected) {
                option.selected = true;
            }
            return option;
        }

        function populateIngredientSelect(selectEl, selectedValue = '') {
            selectEl.innerHTML = '';

            if (!fromLocationSelect.value) {
                selectEl.appendChild(createOption('', 'Pilih lokasi asal dulu', true));
                selectEl.disabled = true;
                return;
            }

            if (!availableIngredients.length) {
                selectEl.appendChild(createOption('', 'Tidak ada stock ingredient tersedia di lokasi asal', true));
                selectEl.disabled = true;
                return;
            }

            selectEl.appendChild(createOption('', 'Pilih ingredient', selectedValue === ''));

            availableIngredients.forEach((item) => {
                const option = createOption(String(item.id), item.label, String(selectedValue) === String(item.id));
                option.setAttribute('data-name', item.name || '');
                option.setAttribute('data-unit', item.unit || '');
                option.setAttribute('data-stock', item.stock || 0);
                selectEl.appendChild(option);
            });

            selectEl.disabled = false;
        }

        function updateStockBadge(row) {
            return;
        }

        function refreshRowNames() {
            const rows = itemsWrapper.querySelectorAll('.item-row');
            rows.forEach((row, index) => {
                row.querySelector('.item-number').textContent = index + 1;
                row.querySelector('.ingredient-select').name = 'items[' + index + '][ingredient_id]';
                row.querySelector('.qty-input').name = 'items[' + index + '][qty]';
            });
        }

        function ensureAtLeastOneRow() {
            if (!itemsWrapper.querySelector('.item-row')) {
                addRow();
            }
        }

        function addRow(data = {}) {
            const fragment = template.content.cloneNode(true);
            const row = fragment.querySelector('.item-row');
            const ingredientSelect = row.querySelector('.ingredient-select');
            const qtyInput = row.querySelector('.qty-input');
            const removeBtn = row.querySelector('.remove-item-btn');

            populateIngredientSelect(ingredientSelect, data.ingredient_id || '');
            qtyInput.value = data.qty || '';

            ingredientSelect.addEventListener('change', function () {
                updateStockBadge(row);
            });

            removeBtn.addEventListener('click', function () {
                row.remove();
                refreshRowNames();
                ensureAtLeastOneRow();
            });

            itemsWrapper.appendChild(row);
            refreshRowNames();
            updateStockBadge(row);
        }

        function refreshAllIngredientSelects() {
            const rows = itemsWrapper.querySelectorAll('.item-row');
            rows.forEach((row) => {
                const selectEl = row.querySelector('.ingredient-select');
                const currentValue = selectEl.value;
                populateIngredientSelect(selectEl, currentValue);
                updateStockBadge(row);
            });
        }

        async function loadIngredientsByLocation() {
            const location = fromLocationSelect.value;

            if (!location) {
                availableIngredients = [];
                refreshAllIngredientSelects();
                resetTransferHint('');
                return;
            }

            availableIngredients = [];
            refreshAllIngredientSelects();
            resetTransferHint('');

            try {
                const response = await fetch(`{{ route('backoffice.transfers.available-ingredients') }}?location=${encodeURIComponent(location)}`, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Gagal mengambil data ingredient.');
                }

                const data = await response.json();
                availableIngredients = data.items || [];

                refreshAllIngredientSelects();

                if (!availableIngredients.length) {
                    resetTransferHint('');
                    return;
                }

                resetTransferHint('');
            } catch (error) {
                availableIngredients = [];
                refreshAllIngredientSelects();
                resetTransferHint('');
            }
        }

        addItemBtn.addEventListener('click', function () {
            addRow();
        });

        fromLocationSelect.addEventListener('change', function () {
            loadIngredientsByLocation();
        });

        toLocationSelect.addEventListener('change', function () {
            if (fromLocationSelect.value && toLocationSelect.value && fromLocationSelect.value === toLocationSelect.value) {
                resetTransferHint('Lokasi asal dan tujuan tidak boleh sama.');
            } else if (fromLocationSelect.value) {
                resetTransferHint('');
            }
        });

        window.addEventListener('DOMContentLoaded', function () {
            if (oldItems && oldItems.length) {
                oldItems.forEach((item) => addRow(item));
            } else {
                addRow();
            }

            if (fromLocationSelect.value) {
                loadIngredientsByLocation();
            } else {
                refreshAllIngredientSelects();
                resetTransferHint('');
            }
        });
    </script>

@endsection
