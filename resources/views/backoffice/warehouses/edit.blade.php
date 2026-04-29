<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Warehouse - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
        }

        .wrap {
            max-width: 920px;
            margin: 40px auto;
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
            font-size: 32px;
            font-weight: 700;
            color: #111827;
        }

        .subtitle {
            margin-top: 6px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
            max-width: 720px;
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

        .btn-primary {
            background: #1d4ed8;
        }

        .btn-dark {
            background: #111827;
        }

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

        .error-box {
            margin-bottom: 18px;
            background: #ffe8e8;
            color: #9b1c1c;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #fecaca;
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
            min-height: 100px;
            resize: vertical;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .note {
            margin-top: 20px;
            background: #fff7ed;
            color: #b45309;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #fed7aa;
            line-height: 1.7;
        }

        @media (max-width: 760px) {
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
                <div class="title">Edit Warehouse</div>

            </div>
            <a href="{{ route('backoffice.warehouses.index') }}" class="btn btn-dark">Kembali</a>
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


            <form method="POST" action="{{ route('backoffice.warehouses.update', $warehouse) }}">
                @csrf
                @method('PUT')

                <div class="field">
                    <label>Nama Warehouse</label>
                    <input type="text" name="name" value="{{ old('name', $warehouse->name) }}" placeholder="contoh: Gudang Pusat" required>
                </div>

                <div class="field">
                    <label>Kode Warehouse</label>
                    <input type="text" name="code" value="{{ old('code', $warehouse->code) }}" placeholder="contoh: GUDANG_PUSAT" required>
                </div>

                <div class="field">
                    <label>Alamat</label>
                    <textarea name="address" placeholder="Masukkan alamat warehouse">{{ old('address', $warehouse->address) }}</textarea>
                </div>

                <div class="field">
                    <label>No. Telepon</label>
                    <input type="text" name="phone" value="{{ old('phone', $warehouse->phone) }}" placeholder="contoh: 081234567890">
                </div>

                <div class="field">
                    <label>Status</label>
                    <select name="is_active" required>
                        <option value="1" @selected(old('is_active', (string) (int) $warehouse->is_active) == '1')>Active</option>
                        <option value="0" @selected(old('is_active', (string) (int) $warehouse->is_active) == '0')>Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Update Warehouse</button>
                    <a href="{{ route('backoffice.warehouses.index') }}" class="btn btn-dark">Batal</a>
                </div>
            </form>

            <div class="note">
                Setelah warehouse diupdate, lokasi ini tetap bisa dipakai sebagai tujuan <strong>Penerimaan Barang</strong>, <strong>Adjustment</strong>, <strong>Opname Gudang</strong>, <strong>Transfers</strong>, dan referensi <strong>location_id</strong> pada import opening stock.
            </div>
        </div>
    </div>
</body>
</html>