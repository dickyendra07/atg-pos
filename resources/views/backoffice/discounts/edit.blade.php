@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Edit Discount - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .page-shell {
            display: grid;
            gap: 22px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #f1e3da;
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .page-title {
            margin: 0 0 10px;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .page-subtitle {
            margin: 0;
            max-width: 860px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .card {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 28px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #6b7280;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .field input,
        .field select {
            width: 100%;
            min-height: 52px;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
            padding: 0 14px;
            font-size: 14px;
            color: #111827;
            outline: none;
            box-sizing: border-box;
        }

        .field input:focus,
        .field select:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .field-error {
            margin-top: 8px;
            color: #b91c1c;
            font-size: 13px;
            font-weight: 700;
        }

        .toggle-row {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 52px;
            padding: 0 14px;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
            font-size: 14px;
            font-weight: 800;
            color: #111827;
        }

        .toggle-row input {
            width: 18px;
            height: 18px;
        }

        .helper {
            margin-top: 8px;
            color: #6b7280;
            font-size: 13px;
            line-height: 1.6;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 22px;
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
        }

        .btn-brand {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        }

        .btn-soft {
            background: #f3f4f6;
            color: #374151;
            box-shadow: none;
        }

        @media (max-width: 900px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-shell">
        <div class="topbar">
            <div>

                <h1 class="page-title">Edit Discount</h1>

            </div>

            <a href="{{ route('backoffice.discounts.index') }}" class="btn btn-soft">Back to Discounts</a>
        </div>

        <div class="card">
            <form method="POST" action="{{ route('backoffice.discounts.update', $discount) }}">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="field full">
                        <label for="name">Discount Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $discount->name) }}" placeholder="Contoh: VIP 10%, Voucher Rp 20.000" required>
                        @error('name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="outlet_id">Outlet</label>
                        <select name="outlet_id" id="outlet_id">
                            <option value="">All Outlets</option>
                            @foreach($outletOptions as $outlet)
                                <option value="{{ $outlet->id }}" @selected((string) old('outlet_id', $discount->outlet_id) === (string) $outlet->id)>
                                    {{ $outlet->name }}
                                </option>
                            @endforeach
                        </select>

                        @error('outlet_id')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="type">Discount Type</label>
                        <select name="type" id="type" required>
                            <option value="amount" @selected(old('type', $discount->type) === 'amount')>Nominal Rp</option>
                            <option value="percent" @selected(old('type', $discount->type) === 'percent')>Percent</option>
                        </select>
                        @error('type')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="value">Value</label>
                        <input type="number" name="value" id="value" value="{{ old('value', $discount->value) }}" min="0" step="0.01" placeholder="Contoh: 20000 atau 10" required>

                        @error('value')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="field">
                        <label>Status</label>
                        <label class="toggle-row">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $discount->is_active))>
                            Active
                        </label>

                    </div>
                </div>

                <div class="actions">
                    <a href="{{ route('backoffice.discounts.index') }}" class="btn btn-soft">Cancel</a>
                    <button type="submit" class="btn btn-brand">Update Discount</button>
                </div>
            </form>
        </div>
    </div>
@endsection
