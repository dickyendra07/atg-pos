<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Opening Stock - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
        }

        .wrap {
            max-width: 1040px;
            margin: 36px auto;
            padding: 0 20px 40px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
        }

        .title {
            font-size: 30px;
            font-weight: 700;
            color: #111827;
        }

        .subtitle {
            margin-top: 6px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
            max-width: 760px;
        }

        .top-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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

        .btn-primary { background: #e86a3a; }
        .btn-dark { background: #111827; }
        .btn-success { background: #166534; }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .helper {
            margin-bottom: 22px;
            background: #eef2ff;
            color: #3730a3;
            padding: 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #dbe3ff;
            line-height: 1.75;
        }

        .error {
            margin-bottom: 18px;
            background: #ffe8e8;
            color: #9b1c1c;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #fecaca;
        }

        .success {
            margin-bottom: 18px;
            background: #eefaf1;
            color: #166534;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #cce9d3;
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

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
            color: #4b5563;
        }

        .field input[type="file"] {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 12px 13px;
            font-size: 14px;
            background: white;
            color: #111827;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 20px;
            align-items: start;
        }

        .template {
            margin-top: 22px;
            background: #fff7ed;
            color: #b45309;
            padding: 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }

        .template-title {
            margin-bottom: 10px;
        }

        .location-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 16px;
        }

        .location-title {
            font-size: 16px;
            font-weight: 700;
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
            font-weight: 700;
            color: #4b5563;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .location-item {
            padding: 10px 12px;
            border-radius: 12px;
            background: white;
            border: 1px solid #e5e7eb;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .location-item:last-child {
            margin-bottom: 0;
        }

        .id-badge {
            display: inline-block;
            margin-top: 4px;
            font-size: 12px;
            font-weight: 700;
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
            border-radius: 10px;
            font-weight: 600;
            border: 1px solid #d1d5db;
            white-space: pre-wrap;
            line-height: 1.7;
        }

        .hint {
            margin-top: 12px;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.7;
            font-weight: 500;
        }

        @media (max-width: 860px) {
            .grid {
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
                <div class="title">Import Opening Stock</div>
                <div class="subtitle">
                    Gunakan halaman ini untuk input stok awal massal ke beberapa lokasi sekaligus. Import ini cocok dipakai saat setup awal sistem atau saat migrasi data stok lama ke Inventory Control.
                </div>
            </div>

            <div class="top-actions">
                <a href="{{ route('backoffice.stock-balances.import.template') }}" class="btn btn-success">Download Template CSV</a>
                <a href="{{ route('backoffice.stock-balances.index') }}" class="btn btn-dark">Kembali</a>
            </div>
        </div>

        @if(session('error'))
            <div class="error">
                {{ session('error') }}
            </div>
        @endif

        @if(session('success'))
            <div class="success">
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
            <div class="error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="grid">
            <div class="card">
                <div class="helper">
                    Import Opening Stock dipakai untuk membuat stok awal secara massal. Kalau stok datang dari luar setelah sistem berjalan, gunakan <strong>Penerimaan Barang</strong>. Kalau barang pindah antar lokasi, gunakan <strong>Transfers</strong>.
                </div>

                <form method="POST" action="{{ route('backoffice.stock-balances.import.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="field">
                        <label>Upload File CSV</label>
                        <input type="file" name="file" accept=".csv,text/csv" required>
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
</body>
</html>