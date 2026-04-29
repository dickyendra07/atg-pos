@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Edit Variant Group - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .variant-form-shell {
            display: grid;
            gap: 22px;
        }

        .variant-form-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .variant-form-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .variant-form-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #e8ddff;
            color: #5b4bd1;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .variant-form-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .variant-form-subtitle {
            margin: 0;
            max-width: 840px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .variant-form-actions {
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

        .btn-green {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
        }

        .btn-blue {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
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

        .alert-error {
            background: #fff1f1;
            color: #b42318;
            border: 1px solid #fecaca;
        }

        .card {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 30px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .card-body {
            padding: 24px;
            display: grid;
            gap: 22px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #374151;
            margin-bottom: 8px;
        }

        .field input,
        .field select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
            padding: 14px 16px;
            font-size: 14px;
            color: #111827;
            outline: none;
        }

        .field input:focus,
        .field select:focus {
            border-color: rgba(91,75,209,0.6);
            box-shadow: 0 0 0 4px rgba(91,75,209,0.10);
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 16px 18px;
            font-size: 14px;
            line-height: 1.8;
            color: #374151;
        }

        .rows-shell {
            display: grid;
            gap: 14px;
        }

        .variant-row {
            border: 1px solid #e8edf4;
            border-radius: 22px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            padding: 18px;
            display: grid;
            gap: 14px;
        }

        .variant-row-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .variant-row-title {
            font-size: 15px;
            font-weight: 800;
            color: #111827;
        }

        .variant-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr 1fr 1fr 0.8fr;
            gap: 14px;
            align-items: end;
        }

        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 52px;
            padding: 0 12px;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
        }

        .checkbox-wrap input {
            width: auto;
            margin: 0;
        }

        .checkbox-wrap span {
            font-size: 14px;
            font-weight: 700;
            color: #374151;
        }

        .note {
            background: #eef2ff;
            color: #3730a3;
            padding: 16px 18px;
            border-radius: 16px;
            font-weight: 700;
            line-height: 1.7;
            border: 1px solid #dbe3ff;
        }

        .bottom-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        @media (max-width: 1180px) {
            .variant-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 780px) {
            .variant-form-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .variant-form-title {
                font-size: 32px;
            }

            .variant-grid {
                grid-template-columns: 1fr;
            }

            .card-body {
                padding: 18px;
            }
        }
        .variant-outlet-dropdown {
            position: relative;
        }

        .variant-outlet-button {
            width: 100%;
            min-height: 42px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #ffffff;
            color: #111827;
            padding: 0 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            text-align: left;
        }

        .variant-outlet-button::after {
            content: "⌄";
            color: #6b7280;
            font-size: 14px;
            line-height: 1;
        }

        .variant-outlet-menu {
            display: none;
            position: absolute;
            z-index: 30;
            top: calc(100% + 8px);
            left: 0;
            width: 100%;
            max-height: 230px;
            overflow: auto;
            padding: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.16);
        }

        .variant-outlet-dropdown.is-open .variant-outlet-menu {
            display: block;
        }

        .variant-outlet-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 10px;
            border-radius: 10px;
            color: #111827;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            user-select: none;
        }

        .variant-outlet-option:hover {
            background: #f3f4f6;
        }

        .variant-outlet-option input {
            width: 14px;
            height: 14px;
            margin: 0;
        }

        .variant-outlet-help {
            display: block;
            margin-top: 7px;
            color: #6b7280;
            font-size: 11px;
            line-height: 1.4;
        }

        .variant-status-field {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .variant-status-field .checkbox-wrap {
            justify-content: center;
            min-height: 46px;
            width: 100%;
        }

    </style>

    @php
        $oldRows = old('variants');

        if (! $oldRows) {
            $oldRows = $productVariants->map(function ($item) {
                return [
                    'id' => $item->id,
                    'outlet_id' => $item->outlet_id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'outlet_ids' => $item->outlets->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    'price_dine_in' => $item->price_dine_in ?? $item->price,
                    'price_delivery' => $item->price_delivery ?? $item->price,
                    'is_active' => $item->is_active ? 1 : 0,
                ];
            })->toArray();
        }
    @endphp

    <div class="variant-form-shell">
        <div class="variant-form-topbar">
            <div class="variant-form-title-block">

                <h1 class="variant-form-title">Edit Group Variant</h1>

            </div>

            <div class="variant-form-actions">
                <a href="{{ route('backoffice.variants.index') }}" class="btn btn-dark">Kembali</a>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-error">
                <div>Form belum valid:</div>
                <div style="margin-top:10px; font-weight:600;">
                    @foreach($errors->all() as $error)
                        <div style="margin-bottom:6px;">• {{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-body">


                <form method="POST" action="{{ route('backoffice.variants.update', $variant->id) }}" id="variant-form">
                    @csrf
                    @method('PUT')

                    <div class="field" style="margin-bottom: 20px;">
                        <label for="product_id">Product</label>
                        <select name="product_id" id="product_id" required>
                            <option value="">Pilih product</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @selected(old('product_id', $variant->product_id) == $product->id)>
                                    {{ $product->name }} - {{ $product->brand->name ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="rows-shell" id="variant-rows">
                        @foreach($oldRows as $index => $row)
                            @php
                                $selectedOutletIds = collect($row['outlet_ids'] ?? [])->map(fn ($id) => (int) $id)->all();
                            @endphp

                            <div class="variant-row" data-variant-row>
                                <div class="variant-row-top">
                                    <div class="variant-row-title">Variant Row</div>
                                    <button type="button" class="btn btn-red btn-remove-row">Hapus Row</button>
                                </div>

                                <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">

                                <div class="variant-grid">
                                    <div class="field outlet-field">
                                        <label>Outlet</label>
                                        <div class="variant-outlet-dropdown" data-outlet-dropdown>
                                            <button type="button" class="variant-outlet-button" data-outlet-toggle>
                                                <span data-outlet-label>Pilih Outlet</span>
                                            </button>

                                            <div class="variant-outlet-menu">
                                                @foreach($outlets as $outlet)
                                                    <label class="variant-outlet-option">
                                                        <input
                                                            type="checkbox"
                                                            name="variants[{{ $index }}][outlet_ids][]"
                                                            value="{{ $outlet->id }}"
                                                            data-outlet-checkbox
                                                            @checked(in_array((int) $outlet->id, $selectedOutletIds, true))
                                                        >
                                                        <span>{{ $outlet->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                        <small class="variant-outlet-help">Kosongkan jika berlaku untuk semua outlet.</small>
                                    </div>

                                    <div class="field">
                                        <label>Nama Variant</label>
                                        <input type="text" name="variants[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="Regular / Large" required>
                                    </div>

                                    <div class="field">
                                        <label>Kode Variant</label>
                                        <input type="text" name="variants[{{ $index }}][code]" value="{{ $row['code'] ?? '' }}" placeholder="R / L / XL" required>
                                    </div>

                                    <div class="field">
                                        <label>Harga Dine In</label>
                                        <input class="rupiah-input" type="text" name="variants[{{ $index }}][price_dine_in]" value="{{ $row['price_dine_in'] ?? 0 }}" required>
                                    </div>

                                    <div class="field">
                                        <label>Harga Delivery</label>
                                        <input class="rupiah-input" type="text" name="variants[{{ $index }}][price_delivery]" value="{{ $row['price_delivery'] ?? 0 }}" required>
                                    </div>

                                    <div class="field variant-status-field">
                                        <label>Status</label>
                                        <label class="checkbox-wrap">
                                            <input type="hidden" name="variants[{{ $index }}][is_active]" value="0">
                                            <input type="checkbox" name="variants[{{ $index }}][is_active]" value="1" {{ !empty($row['is_active']) ? 'checked' : '' }}>
                                            <span>Active</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="bottom-actions" style="margin-top: 18px;">
                        <button type="button" class="btn btn-blue" id="add-row-button">Tambah Variant Row</button>
                        <button type="submit" class="btn btn-green">Update Group Variant</button>
                        <a href="{{ route('backoffice.variants.index') }}" class="btn btn-dark">Batal</a>
                    </div>
                </form>


            </div>
        </div>
    </div>

    <template id="variant-row-template">
        <div class="variant-row" data-variant-row>
            <div class="variant-row-top">
                <div class="variant-row-title">Variant Row</div>
                <button type="button" class="btn btn-red btn-remove-row">Hapus Row</button>
            </div>

            <input type="hidden" data-name="id" value="">

            <div class="variant-grid">
                <div class="field outlet-field">
                    <label>Outlet</label>
                    <div class="variant-outlet-dropdown" data-outlet-dropdown>
                        <button type="button" class="variant-outlet-button" data-outlet-toggle>
                            <span data-outlet-label>Pilih Outlet</span>
                        </button>

                        <div class="variant-outlet-menu">
                            @foreach($outlets as $outlet)
                                <label class="variant-outlet-option">
                                    <input
                                        type="checkbox"
                                        data-name="outlet_ids"
                                        value="{{ $outlet->id }}"
                                        data-outlet-checkbox
                                    >
                                    <span>{{ $outlet->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <small class="variant-outlet-help">Kosongkan jika berlaku untuk semua outlet.</small>
                </div>

                <div class="field">
                    <label>Nama Variant</label>
                    <input type="text" data-name="name" placeholder="Regular / Large" required>
                </div>

                <div class="field">
                    <label>Kode Variant</label>
                    <input type="text" data-name="code" placeholder="R / L / XL" required>
                </div>

                <div class="field">
                    <label>Harga Dine In</label>
                    <input class="rupiah-input" type="text" data-name="price_dine_in" value="0" required>
                </div>

                <div class="field">
                    <label>Harga Delivery</label>
                    <input class="rupiah-input" type="text" data-name="price_delivery" value="0" required>
                </div>

                <div class="field variant-status-field">
                    <label>Status</label>
                    <label class="checkbox-wrap">
                        <input type="hidden" data-name-hidden="is_active" value="0">
                        <input type="checkbox" data-name="is_active" value="1" checked>
                        <span>Active</span>
                    </label>
                </div>
            </div>
        </div>
    </template>

    <script>
        (function () {
            const rowsContainer = document.getElementById('variant-rows');
            const addRowButton = document.getElementById('add-row-button');
            const template = document.getElementById('variant-row-template');

            function refreshOutletDropdowns() {
                rowsContainer.querySelectorAll('[data-outlet-dropdown]').forEach(function (dropdown) {
                    const label = dropdown.querySelector('[data-outlet-label]');
                    const checked = Array.from(dropdown.querySelectorAll('[data-outlet-checkbox]:checked'))
                        .map(function (checkbox) {
                            const optionLabel = checkbox.closest('.variant-outlet-option')?.querySelector('span');
                            return optionLabel ? optionLabel.textContent.trim() : '';
                        })
                        .filter(Boolean);

                    if (!label) return;

                    if (checked.length === 0) {
                        label.textContent = 'Semua Outlet';
                    } else if (checked.length === 1) {
                        label.textContent = checked[0];
                    } else {
                        label.textContent = checked.length + ' outlet dipilih';
                    }
                });
            }

            function refreshIndexes() {
                const rows = rowsContainer.querySelectorAll('[data-variant-row]');

                rows.forEach((row, index) => {
                    row.querySelectorAll('[data-name]').forEach((field) => {
                        const key = field.getAttribute('data-name');

                        if (key === 'outlet_ids') {
                            field.setAttribute('name', `variants[${index}][outlet_ids][]`);
                        } else {
                            field.setAttribute('name', `variants[${index}][${key}]`);
                        }
                    });

                    row.querySelectorAll('[data-name-hidden]').forEach((field) => {
                        const key = field.getAttribute('data-name-hidden');
                        field.setAttribute('name', `variants[${index}][${key}]`);
                    });
                });

                refreshOutletDropdowns();
            }

            function addRow() {
                const clone = template.content.cloneNode(true);
                rowsContainer.appendChild(clone);
                refreshIndexes();
            }

            rowsContainer.addEventListener('click', function (event) {
                const toggle = event.target.closest('[data-outlet-toggle]');

                if (toggle) {
                    event.preventDefault();
                    const dropdown = toggle.closest('[data-outlet-dropdown]');

                    rowsContainer.querySelectorAll('[data-outlet-dropdown].is-open').forEach(function (opened) {
                        if (opened !== dropdown) {
                            opened.classList.remove('is-open');
                        }
                    });

                    dropdown.classList.toggle('is-open');
                    return;
                }

                if (event.target.matches('[data-outlet-checkbox]')) {
                    refreshOutletDropdowns();
                }
            });

            document.addEventListener('click', function (event) {
                if (!event.target.closest('[data-outlet-dropdown]')) {
                    rowsContainer.querySelectorAll('[data-outlet-dropdown].is-open').forEach(function (dropdown) {
                        dropdown.classList.remove('is-open');
                    });
                }
            });

            addRowButton.addEventListener('click', addRow);

            rowsContainer.addEventListener('click', function (event) {
                const removeButton = event.target.closest('.btn-remove-row');
                if (!removeButton) return;

                const rows = rowsContainer.querySelectorAll('[data-variant-row]');
                if (rows.length <= 1) {
                    alert('Minimal harus ada 1 row variant.');
                    return;
                }

                removeButton.closest('[data-variant-row]').remove();
                refreshIndexes();
            });

            refreshIndexes();
        })();
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const cleanCurrencyNumber = function (value) {
                let raw = String(value || '').trim();

                // Database decimal value like 12000.00 / 12000,00 should become 12000, not 1200000.
                raw = raw.replace(/[.,]00$/, '');

                return raw.replace(/[^\d]/g, '');
            };

            const formatRupiah = function (value) {
                const numeric = cleanCurrencyNumber(value);

                if (!numeric) {
                    return '';
                }

                return 'Rp. ' + Number(numeric).toLocaleString('id-ID');
            };

            const normalizeNumber = function (value) {
                return cleanCurrencyNumber(value);
            };

            document.querySelectorAll('.rupiah-input').forEach(function (input) {
                input.setAttribute('inputmode', 'numeric');
                input.setAttribute('autocomplete', 'off');

                input.value = formatRupiah(input.value);

                input.addEventListener('input', function () {
                    const cursorEnd = input.selectionStart === input.value.length;
                    input.value = formatRupiah(input.value);

                    if (cursorEnd) {
                        input.setSelectionRange(input.value.length, input.value.length);
                    }
                });

                input.addEventListener('blur', function () {
                    input.value = formatRupiah(input.value);
                });
            });

            document.querySelectorAll('form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    form.querySelectorAll('.rupiah-input').forEach(function (input) {
                        input.value = normalizeNumber(input.value);
                    });
                });
            });
        });
    </script>

@endsection