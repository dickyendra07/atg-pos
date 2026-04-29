@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Import Opening Stock - Back Office';
@endphp

@section('content')
    <style>
        .import-shell {
            display: grid;
            gap: 22px;
        }

        .import-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .import-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .import-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #dbe7ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .import-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .import-subtitle {
            margin: 0;
            max-width: 860px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .import-actions {
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

        .btn-primary {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        }

        .btn-dark {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
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

        .alert-success {
            background: #eefaf1;
            color: #166534;
            border: 1px solid #cce9d3;
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
            background: linear-gradient(135deg, #ffffff 0%, #f7faff 58%, #edf4ff 100%);
            border: 1px solid #dbe7ff;
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
            background: radial-gradient(circle, rgba(29,78,216,0.14) 0%, rgba(29,78,216,0.03) 65%, rgba(29,78,216,0) 80%);
            pointer-events: none;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #dbe7ff;
            color: #1d4ed8;
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
            background: #eef2ff;
            color: #3730a3;
            padding: 16px 18px;
            border-radius: 18px;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.8;
            border: 1px solid #dbe3ff;
        }

        .content-grid {
            padding: 0 22px 22px;
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            gap: 20px;
            align-items: start;
        }

        .upload-card,
        .location-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e8edf4;
            border-radius: 24px;
            padding: 20px;
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

        .field input[type="file"] {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            padding: 12px 13px;
            font-size: 14px;
            background: white;
            color: #111827;
            min-height: 48px;
        }

        .field input[type="file"]:focus {
            border-color: rgba(29,78,216,0.45);
            box-shadow: 0 0 0 4px rgba(29,78,216,0.10);
            outline: none;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .template {
            margin-top: 22px;
            background: #fff7ed;
            color: #b45309;
            padding: 16px;
            border-radius: 18px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }

        .template-title {
            margin-bottom: 10px;
        }

        .location-title {
            font-size: 16px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 12px;
        }

        .location-group {
            margin-bottom: 16px;
        }

        .location-group:last-child {
            margin-bottom: 0;
        }

        .location-group-title {
            font-size: 13px;
            font-weight: 800;
            color: #4b5563;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .location-item {
            padding: 12px;
            border-radius: 14px;
            background: white;
            border: 1px solid #e5e7eb;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 8px;
        }

        .location-item:last-child {
            margin-bottom: 0;
        }

        .id-badge {
            display: inline-block;
            margin-top: 6px;
            font-size: 12px;
            font-weight: 800;
            color: #c9552a;
            background: #fff3eb;
            border: 1px solid #f3d7c9;
            border-radius: 999px;
            padding: 4px 10px;
        }

        code {
            display: block;
            margin-top: 8px;
            background: #ffffff;
            color: #111827;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            border: 1px solid #d1d5db;
            white-space: pre-wrap;
            line-height: 1.7;
            font-family: monospace;
        }

        .hint {
            margin-top: 12px;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.8;
            font-weight: 500;
        }

        .success-list,
        .skip-list {
            margin-top: 10px;
            font-weight: 600;
            line-height: 1.7;
        }

        .skip-title {
            margin-top: 14px;
            font-weight: 700;
            color: #92400e;
        }

        .skip-list {
            color: #92400e;
        }

        @media (max-width: 1320px) {
            .hero-wrap,
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 860px) {
            .import-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .import-title {
                font-size: 32px;
            }

            .hero-heading {
                font-size: 28px;
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

    <div class="import-shell">
        <div class="import-topbar">
            <div class="import-title-block">

                <h1 class="import-title">Import Opening Stock</h1>

            </div>

            <div class="import-actions">
                <a href="{{ route('backoffice.stock-balances.import.template') }}" class="btn btn-success">Download Template CSV</a>
                <a href="{{ route('backoffice.stock-balances.index') }}" class="btn btn-dark">Kembali</a>
            </div>
        </div>

        @if(session('error'))
            <div class="alert alert-error">
                {{ session('error') }}
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success">
                <div>{{ session('success') }}</div>

                @if(session('import_success_rows'))
                    <div class="success-list">
                        @foreach(session('import_success_rows') as $row)
                            <div>• {{ $row }}</div>
                        @endforeach
                    </div>
                @endif

                @if(session('import_errors'))
                    <div class="skip-title">Baris yang di-skip:</div>
                    <div class="skip-list">
                        @foreach(session('import_errors') as $error)
                            <div>• {{ $error }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card">

                    <div class="rule-line"><strong>Bukan untuk:</strong> penerimaan barang harian</div>
                    <div class="rule-line"><strong>Bukan untuk:</strong> transfer antar lokasi</div>
                    <div class="rule-line"><strong>Format file:</strong> CSV sesuai template</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">Form Import Opening Stock</h2>

                </div>


                <div class="content-grid">
                    <div class="upload-card">
                        <form method="POST" action="{{ route('backoffice.stock-balances.import.store') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="field">
                                <label for="file">Upload File CSV</label>
                                <input type="file" name="file" id="file" accept=".csv,text/csv" required>
                            </div>

                            <div class="actions">
                                <button type="submit" class="btn btn-primary">Import Opening Stock</button>
                                <a href="{{ route('backoffice.stock-balances.index') }}" class="btn btn-dark">Batal</a>
                            </div>
                        </form>

                        <div class="template">
                            <div class="template-title">Header CSV wajib:</div>
                            <code>ingredient_name,location_type,location_id,qty_on_hand,note</code>

                            <div class="template-title" style="margin-top:14px;">Contoh isi CSV:</div>
                            <code>Black Tea,outlet,1,1000,Opening stock outlet 1
Fresh Milk,outlet,1,800,Opening stock outlet 1
Liquid Sugar,warehouse,1,5000,Opening stock gudang utama</code>

                            <div class="hint">
                                Gunakan <strong>location_type</strong> dengan nilai <strong>outlet</strong> atau <strong>warehouse</strong>.<br>
                                Gunakan <strong>location_id</strong> sesuai ID lokasi tujuan.<br>
                                Baris akan di-skip kalau kombinasi <strong>ingredient + location_type + location_id</strong> sudah punya stock balance.
                            </div>
                        </div>
                    </div>

                    <div class="location-card">
                        <div class="location-title">Daftar lokasi aktif untuk CSV import</div>

                        <div class="location-group">
                            <div class="location-group-title">Warehouses</div>
                            @forelse($warehouses as $warehouse)
                                <div class="location-item">
                                    <div><strong>{{ $warehouse->name }}</strong></div>
                                    <div class="id-badge">location_type: warehouse | location_id: {{ $warehouse->id }}</div>
                                </div>
                            @empty
                                <div class="location-item">
                                    Belum ada warehouse aktif.
                                </div>
                            @endforelse
                        </div>

                        <div class="location-group">
                            <div class="location-group-title">Outlets</div>
                            @forelse($outlets as $outlet)
                                <div class="location-item">
                                    <div><strong>{{ $outlet->name }}</strong></div>
                                    <div class="id-badge">location_type: outlet | location_id: {{ $outlet->id }}</div>
                                </div>
                            @empty
                                <div class="location-item">
                                    Belum ada outlet aktif.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection