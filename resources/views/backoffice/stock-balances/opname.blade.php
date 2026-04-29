@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Opname Gudang - Back Office';
@endphp

@section('content')
    <style>
        .opname-shell {
            display: grid;
            gap: 22px;
        }

        .opname-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .opname-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .opname-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #ddd6fe;
            color: #6d28d9;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .opname-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .opname-subtitle {
            margin: 0;
            max-width: 860px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .opname-actions {
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

        .btn-info {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
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
            background: linear-gradient(135deg, #ffffff 0%, #faf8ff 58%, #f3efff 100%);
            border: 1px solid #e3deff;
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
            background: radial-gradient(circle, rgba(109,40,217,0.14) 0%, rgba(109,40,217,0.03) 65%, rgba(109,40,217,0) 80%);
            pointer-events: none;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #e3deff;
            color: #6d28d9;
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

        .info-box {
            margin: 0 22px 18px;
            background: #f8fafc;
            color: #374151;
            padding: 16px 18px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.8;
            border: 1px solid #e5e7eb;
        }

        .info-box strong {
            color: #111827;
        }

        .form-wrap {
            padding: 0 22px 22px;
        }

        .warehouse-picker-form {
            margin-bottom: 18px;
        }

        .form-grid {
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
            border-color: rgba(109,40,217,0.45);
            box-shadow: 0 0 0 4px rgba(109,40,217,0.10);
        }

        .muted {
            color: #6b7280;
            font-size: 13px;
            margin-top: 8px;
            line-height: 1.7;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .note {
            margin-top: 22px;
            background: #ede9fe;
            color: #5b21b6;
            padding: 16px 18px;
            border-radius: 18px;
            font-weight: 700;
            line-height: 1.8;
            border: 1px solid #ddd6fe;
        }

        @media (max-width: 1320px) {
            .hero-wrap {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 860px) {
            .opname-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .opname-title {
                font-size: 32px;
            }

            .hero-heading {
                font-size: 28px;
            }

            .form-grid {
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

    <div class="opname-shell">
        <div class="opname-topbar">
            <div class="opname-title-block">

                <h1 class="opname-title">Opname Gudang</h1>

            </div>

            <div class="opname-actions">
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

                    <div class="rule-line"><strong>Bukan untuk:</strong> outlet</div>
                    <div class="rule-line"><strong>Qty sistem:</strong> stok sebelum opname</div>
                    <div class="rule-line"><strong>Qty fisik:</strong> hasil hitung aktual di gudang</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">Form Opname Gudang</h2>

                </div>


                <div class="form-wrap">
                    <form method="GET" action="{{ route('backoffice.stock-balances.opname.create') }}" class="warehouse-picker-form">
                        <div class="field">
                            <label for="warehouse_id_picker">Warehouse</label>
                            <select name="warehouse_id" id="warehouse_id_picker" onchange="this.form.submit()" required>
                                <option value="">Pilih warehouse</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" {{ (string) $selectedWarehouseId === (string) $warehouse->id ? 'selected' : '' }}>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="muted">Begitu warehouse dipilih, halaman akan otomatis refresh untuk menampilkan stock item gudang tersebut.</div>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('backoffice.stock-balances.opname.store') }}">
                        @csrf

                        <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">

                        <div class="form-grid">
                            <div class="field">
                                <label for="stock_balance_id">Stock Item Warehouse</label>
                                <select name="stock_balance_id" id="stock_balance_id" required {{ empty($selectedWarehouseId) ? 'disabled' : '' }}>
                                    <option value="">Pilih stock item</option>
                                    @foreach($stockBalances as $stock)
                                        <option value="{{ $stock->id }}" @selected(old('stock_balance_id') == $stock->id)>
                                            {{ $stock->ingredient->name ?? '-' }} | Sistem saat ini: {{ number_format((float) $stock->qty_on_hand, 0, ',', '.') }}
                                        </option>
                                    @endforeach
                                </select>

                                @if(empty($selectedWarehouseId))
                                    <div class="muted">Pilih warehouse dulu supaya stock item muncul.</div>
                                @elseif($stockBalances->isEmpty())
                                    <div class="muted">Belum ada stock warehouse untuk gudang yang dipilih.</div>
                                @endif
                            </div>

                            <div class="field">
                                <label for="physical_qty">Qty Fisik Hasil Hitung</label>
                                <input type="number" name="physical_qty" id="physical_qty" min="0" step="0.01" value="{{ old('physical_qty') }}" required>
                                <div class="muted">Isi jumlah stok fisik hasil hitung aktual di gudang.</div>
                            </div>
                        </div>

                        <div class="field">
                            <label for="note">Keterangan</label>
                            <input type="text" name="note" id="note" value="{{ old('note') }}" placeholder="Contoh: opname gudang rak bahan kering" required>
                        </div>

                        <div class="actions">
                            <button type="submit" class="btn btn-info">Simpan Opname Gudang</button>
                            <a href="{{ route('backoffice.stock-balances.index') }}" class="btn btn-dark">Batal</a>
                        </div>
                    </form>

                    <div class="note">
                        Opname gudang akan menyesuaikan <strong>qty sistem</strong> menjadi <strong>qty fisik</strong>. Kalau ada selisih, sistem otomatis membuat <strong>movement adjustment</strong> dengan reference <strong>stock_opname</strong> pada lokasi warehouse tersebut.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection