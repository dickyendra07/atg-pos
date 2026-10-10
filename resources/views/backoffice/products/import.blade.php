<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Products - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 900px;
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
            font-size: 28px;
            font-weight: bold;
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

        .btn-primary {
            background: #1d4ed8;
        }

        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
        }

        .info, .error {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
        }

        .info {
            background: #eef2ff;
            color: #3730a3;
        }

        .error {
            background: #ffe8e8;
            color: #9b1c1c;
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

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .note {
            margin-top: 20px;
            background: #fff7ed;
            color: #9a3412;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title">Import Products</div>
            <a href="{{ route('backoffice.products.index') }}" class="btn btn-secondary">Kembali</a>
        </div>


        @if($errors->any())
            <div class="error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <div class="info">
                Upload file CSV sesuai template. Brand dan Category harus sudah ada dulu di sistem.
                Product hasil import hanya tersedia di outlet tujuan yang dipilih, bukan di semua outlet.
            </div>

            <form method="POST" action="{{ route('backoffice.products.import.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="field">
                    <label for="import-outlet">Outlet tujuan</label>
                    <select id="import-outlet" name="outlet_id" required>
                        <option value="">Pilih outlet</option>
                        @foreach($backofficeOutletOptions ?? [] as $importOutlet)
                            <option value="{{ $importOutlet->id }}" @selected((string) old('outlet_id') === (string) $importOutlet->id)>{{ $importOutlet->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label>File CSV</label>
                    <input type="file" name="file" accept=".csv,.txt" required>
                </div>

                <div class="field">
                    <label style="display:flex; gap:8px; align-items:flex-start; font-weight:normal;">
                        <input type="checkbox" name="allow_similar" value="1" style="width:auto; margin-top:3px;" @checked(old('allow_similar'))>
                        <span>Buat meskipun mirip dengan Product yang sudah ada (nama, brand, dan kategori sama tetapi kode berbeda). Biarkan kosong jika ragu: baris yang mirip akan dilewati dan dilaporkan.</span>
                    </label>
                </div>

                <div class="actions">
                    <a href="{{ route('backoffice.products.import.template') }}" class="btn btn-secondary">Download Template</a>
                    <button type="submit" class="btn btn-primary">Import Products</button>
                </div>
            </form>

            <div class="note">
                Format template: <strong>brand_name, category_name, name, code, description, is_active</strong>.
                Kalau code product sudah ada, Product itu hanya di-assign ke outlet tujuan; nama, brand, kategori, deskripsi, dan status tidak diubah oleh import (perbedaannya dilaporkan). Kalau belum ada, data baru akan dibuat. Variant tidak ikut di-assign otomatis.
            </div>
        </div>
    </div>
    @include('backoffice.partials.feedback')
@include('backoffice.partials.button-system')
</body>
</html>