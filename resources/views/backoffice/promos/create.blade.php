@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Create Promo - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .page-shell { display: grid; gap: 22px; }
        .topbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
        .kicker {
            display: inline-flex; align-items: center; padding: 8px 12px; border-radius: 999px;
            background: rgba(255,255,255,0.88); border: 1px solid #f1e3da; color: #c9552a;
            font-size: 12px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 14px;
        }
        .page-title { margin: 0 0 10px; font-size: 38px; line-height: 1; font-weight: 800; letter-spacing: -0.04em; color: #111827; }
        .page-subtitle { margin: 0; max-width: 900px; color: #6b7280; font-size: 15px; line-height: 1.9; }
        .card {
            background: rgba(255,255,255,0.92); border: 1px solid #e8edf4; border-radius: 28px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08); padding: 24px;
        }
        .section-card {
            border: 1px solid #e8edf4; background: #fff; border-radius: 22px; padding: 20px; margin-bottom: 18px;
        }
        .section-title { margin: 0 0 6px; font-size: 18px; font-weight: 800; color: #111827; }
        .section-subtitle { margin: 0 0 18px; font-size: 13px; color: #6b7280; line-height: 1.7; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .field.full { grid-column: 1 / -1; }
        .field label {
            display: block; font-size: 12px; font-weight: 800; color: #6b7280; margin-bottom: 8px;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .field input, .field select {
            width: 100%; min-height: 52px; border: 1px solid #d7dce5; border-radius: 14px; background: white;
            padding: 0 14px; font-size: 14px; color: #111827; outline: none; box-sizing: border-box;
        }
        .field-error { margin-top: 8px; color: #b91c1c; font-size: 13px; font-weight: 700; }
        .helper { margin-top: 8px; color: #6b7280; font-size: 13px; line-height: 1.6; }
        .days-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
        .day-check, .toggle-row {
            display: flex; align-items: center; gap: 10px; min-height: 48px; padding: 0 14px;
            border: 1px solid #d7dce5; border-radius: 14px; background: white; font-size: 14px; font-weight: 800; color: #111827;
        }
        .day-check input, .toggle-row input { width: 18px; height: 18px; }
        .rule-list { display: grid; gap: 12px; }
        .rule-row {
            display: grid; grid-template-columns: 1.5fr 140px auto; gap: 12px; align-items: end;
            border: 1px solid #edf1f6; background: #fbfcfe; border-radius: 18px; padding: 14px;
        }
        .reward-row {
            display: grid; grid-template-columns: 1fr 1fr 1.5fr 120px auto; gap: 12px; align-items: end;
            border: 1px solid #edf1f6; background: #fbfcfe; border-radius: 18px; padding: 14px;
        }
        .btn-mini {
            border: 0; min-height: 42px; padding: 0 14px; border-radius: 12px; cursor: pointer;
            font-size: 12px; font-weight: 800;
        }
        .btn-add { background: #e8fff1; color: #166534; border: 1px solid #ccefd8; }
        .btn-remove { background: #fff1f1; color: #b91c1c; border: 1px solid #fecaca; }
        .actions { display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-top: 22px; }
        .btn {
            border: 0; cursor: pointer; min-height: 42px; padding: 0 16px; border-radius: 14px; color: white;
            font-size: 13px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center;
            justify-content: center; box-shadow: 0 10px 20px rgba(15,23,42,0.10);
        }
        .btn-brand { background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%); }
        .btn-soft { background: #f3f4f6; color: #374151; box-shadow: none; }
        @media (max-width: 1100px) {
            .form-grid, .days-grid, .rule-row, .reward-row { grid-template-columns: 1fr; }
        }
    </style>

    <div class="page-shell">
        <div class="topbar">
            <div>
                <div class="kicker">Create Promo</div>
                <h1 class="page-title">Create Promo</h1>
                <p class="page-subtitle">
                    Buat promo dengan beberapa product requirement dan beberapa reward seperti POS lama.
                </p>
            </div>

            <a href="{{ route('backoffice.promos.index') }}" class="btn btn-soft">Back to Promos</a>
        </div>

        <div class="card">
            <form method="POST" action="{{ route('backoffice.promos.store') }}">
                @csrf

                <div class="section-card">
                    <h2 class="section-title">1. Promo Basic</h2>
                    <p class="section-subtitle">Nama promo, outlet, dan status promo.</p>

                    <div class="form-grid">
                        <div class="field full">
                            <label for="name">Promo Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" placeholder="Contoh: Buy 2 Get 1 Paket A" required>
                            @error('name') <div class="field-error">{{ $message }}</div> @enderror
                        </div>

                        <div class="field">
                            <label for="outlet_id">Outlet</label>
                            <select name="outlet_id" id="outlet_id">
                                <option value="">All Outlets</option>
                                @foreach($outletOptions as $outlet)
                                    <option value="{{ $outlet->id }}" @selected((string) old('outlet_id') === (string) $outlet->id)>{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                            <div class="helper">Kosongkan untuk berlaku di semua outlet.</div>
                        </div>

                        <div class="field">
                            <label for="status">Promo Status</label>
                            <select name="status" id="status" required>
                                <option value="draft" @selected(old('status', 'draft') === 'draft')>Draft</option>
                                <option value="active" @selected(old('status') === 'active')>Active</option>
                                <option value="discontinued" @selected(old('status') === 'discontinued')>Discontinued</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="section-title">2. Purchase Requirements</h2>
                    <p class="section-subtitle">Tambahkan satu atau lebih product/variant yang wajib dibeli customer.</p>

                    <div id="requirementsList" class="rule-list"></div>

                    <div style="margin-top:14px;">
                        <button type="button" class="btn-mini btn-add" onclick="addRequirement()">+ Add Requirement</button>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="section-title">3. Rewards</h2>
                    <p class="section-subtitle">Tambahkan reward discount nominal, discount persen, atau free item.</p>

                    <div id="rewardsList" class="rule-list"></div>

                    <div style="margin-top:14px;">
                        <button type="button" class="btn-mini btn-add" onclick="addReward()">+ Add Reward</button>
                    </div>
                </div>

                <div class="section-card">
                    <h2 class="section-title">4. Promo Schedule</h2>
                    <p class="section-subtitle">Periode, jam, dan hari aktif promo.</p>

                    <div class="form-grid">
                        <div class="field">
                            <label for="start_date">Promo Start</label>
                            <input type="date" name="start_date" id="start_date" value="{{ old('start_date') }}">
                        </div>

                        <div class="field">
                            <label for="end_date">Promo End</label>
                            <input type="date" name="end_date" id="end_date" value="{{ old('end_date') }}">
                        </div>

                        <div class="field">
                            <label for="start_time">Start Hour</label>
                            <input type="time" name="start_time" id="start_time" value="{{ old('start_time') }}">
                        </div>

                        <div class="field">
                            <label for="end_time">End Hour</label>
                            <input type="time" name="end_time" id="end_time" value="{{ old('end_time') }}">
                        </div>

                        <div class="field full">
                            <label>Active Days</label>
                            <div class="days-grid">
                                @foreach($dayOptions as $dayValue => $dayLabel)
                                    <label class="day-check">
                                        <input type="checkbox" name="active_days[]" value="{{ $dayValue }}" @checked(in_array($dayValue, old('active_days', []), true))>
                                        {{ $dayLabel }}
                                    </label>
                                @endforeach
                            </div>
                            <div class="helper">Kosongkan jika promo berlaku setiap hari.</div>
                        </div>

                        <div class="field">
                            <label>Active Toggle</label>
                            <label class="toggle-row">
                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1'))>
                                Active
                            </label>
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <a href="{{ route('backoffice.promos.index') }}" class="btn btn-soft">Cancel</a>
                    <button type="submit" class="btn btn-brand">Save Promo</button>
                </div>
            </form>
        </div>
    </div>

    <template id="variantOptionsTemplate">
        <option value="">Pilih product / variant</option>
        @foreach($variantOptions as $variant)
            <option value="{{ $variant->id }}">{{ $variant->product->name ?? 'Product' }} - {{ $variant->name }}</option>
        @endforeach
    </template>

    <script>
        let requirementIndex = 0;
        let rewardIndex = 0;

        function variantOptionsHtml() {
            return document.getElementById('variantOptionsTemplate').innerHTML;
        }

        function addRequirement(data = {}) {
            const wrapper = document.createElement('div');
            wrapper.className = 'rule-row';
            wrapper.innerHTML = `
                <div class="field">
                    <label>Product / Variant</label>
                    <select name="requirements[${requirementIndex}][product_variant_id]" required>
                        ${variantOptionsHtml()}
                    </select>
                </div>
                <div class="field">
                    <label>Qty</label>
                    <input type="number" name="requirements[${requirementIndex}][qty]" value="${data.qty || 1}" min="1" step="1" required>
                </div>
                <button type="button" class="btn-mini btn-remove" onclick="this.closest('.rule-row').remove()">Remove</button>
            `;

            document.getElementById('requirementsList').appendChild(wrapper);

            if (data.product_variant_id) {
                wrapper.querySelector('select').value = data.product_variant_id;
            }

            requirementIndex++;
        }

        function addReward(data = {}) {
            const wrapper = document.createElement('div');
            wrapper.className = 'reward-row';
            wrapper.innerHTML = `
                <div class="field">
                    <label>Reward Type</label>
                    <select name="rewards[${rewardIndex}][reward_type]" onchange="syncRewardRow(this)" required>
                        <option value="discount_amount">Discount Nominal Rp</option>
                        <option value="discount_percent">Discount Percent</option>
                        <option value="free_item">Free Item</option>
                    </select>
                </div>
                <div class="field reward-value-field">
                    <label>Value</label>
                    <input type="number" name="rewards[${rewardIndex}][reward_value]" value="${data.reward_value || 0}" min="0" step="0.01">
                </div>
                <div class="field reward-item-field">
                    <label>Free Product / Variant</label>
                    <select name="rewards[${rewardIndex}][product_variant_id]">
                        ${variantOptionsHtml()}
                    </select>
                </div>
                <div class="field reward-item-field">
                    <label>Qty</label>
                    <input type="number" name="rewards[${rewardIndex}][qty]" value="${data.qty || 1}" min="1" step="1">
                </div>
                <button type="button" class="btn-mini btn-remove" onclick="this.closest('.reward-row').remove()">Remove</button>
            `;

            document.getElementById('rewardsList').appendChild(wrapper);

            const typeSelect = wrapper.querySelector('select[name$="[reward_type]"]');
            typeSelect.value = data.reward_type || 'discount_amount';

            const productSelect = wrapper.querySelector('select[name$="[product_variant_id]"]');
            if (data.product_variant_id) {
                productSelect.value = data.product_variant_id;
            }

            syncRewardRow(typeSelect);
            rewardIndex++;
        }

        function syncRewardRow(select) {
            const row = select.closest('.reward-row');
            const isFreeItem = select.value === 'free_item';

            row.querySelectorAll('.reward-value-field').forEach((field) => {
                field.style.display = isFreeItem ? 'none' : 'block';
            });

            row.querySelectorAll('.reward-item-field').forEach((field) => {
                field.style.display = isFreeItem ? 'block' : 'none';
            });
        }

        addRequirement();
        addReward();
    </script>
@endsection
