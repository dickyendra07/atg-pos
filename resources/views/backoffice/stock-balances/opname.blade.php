<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opname Gudang - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 980px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .btn-info {
            background: #1d4ed8;
        }

        .btn-brand {
            background: #e86a3a;
        }

        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
        }

        .info {
            margin-bottom: 18px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            line-height: 1.8;
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
            background: #ede9fe;
            color: #5b21b6;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
            line-height: 1.7;
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

        .muted {
            color: #6b7280;
            font-size: 13px;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div>
                <div class="title">Opname Gudang</div>
                <div class="subtitle">
                    Stock opname formal hanya untuk warehouse. Pilih gudang dulu, lalu pilih item stock warehouse yang ingin disesuaikan ke qty fisik.
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
            <div class="info">
                <strong>User:</strong> {{ $user->name }}<br>
                <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                <strong>Outlet Login:</strong> {{ $user->outlet->name ?? '-' }}<br>
                <strong>Rule:</strong> Opname formal hanya berlaku untuk stock warehouse
            </div>

            <div class="helper">
                Langkah 1: pilih warehouse dulu untuk memunculkan stock item gudang yang sesuai.
            </div>

            <form method="GET" action="{{ route('backoffice.stock-balances.opname.create') }}" style="margin-bottom: 18px;">
                <div class="field">
                    <label>Warehouse</label>
                    <select name="warehouse_id" onchange="this.form.submit()" required>
                        <option value="">Pilih warehouse</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" {{ (string) $selectedWarehouseId === (string) $warehouse->id ? 'selected' : '' }}>
                                {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            <form method="POST" action="{{ route('backoffice.stock-balances.opname.store') }}">
                @csrf

                <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId }}">

                <div class="field">
                    <label>Stock Item Warehouse</label>
                    <select name="stock_balance_id" required {{ empty($selectedWarehouseId) ? 'disabled' : '' }}>
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
                    <label>Qty Fisik Hasil Hitung</label>
                    <input type="number" name="physical_qty" min="0" step="0.01" value="{{ old('physical_qty') }}" required>
                </div>

                <div class="field">
                    <label>Note</label>
                    <input type="text" name="note" value="{{ old('note') }}" placeholder="contoh: Opname gudang rak bahan kering" required>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-info">Simpan Opname Gudang</button>
                    <a href="{{ route('backoffice.stock-balances.index') }}" class="btn">Batal</a>
                </div>
            </form>

            <div class="note">
                Opname gudang akan menyesuaikan qty sistem ke qty fisik. Kalau ada selisih, otomatis dibuat movement adjustment dengan reference stock_opname pada lokasi warehouse.
            </div>
        </div>
    </div>
</body>
</html>