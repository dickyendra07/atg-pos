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

        .field input {
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
            <a href="{{ route('backoffice.products.index') }}" class="btn">Kembali</a>
        </div>

        @if(session('error'))
            <div class="error">{{ session('error') }}</div>
        @endif

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
            </div>

            <form method="POST" action="{{ route('backoffice.products.import.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="field">
                    <label>File CSV</label>
                    <input type="file" name="file" accept=".csv,.txt" required>
                </div>

                <div class="actions">
                    <a href="{{ route('backoffice.products.import.template') }}" class="btn btn-primary">Download Template</a>
                    <button type="submit" class="btn btn-success">Import Products</button>
                </div>
            </form>

            <div class="note">
                Format template: <strong>brand_name, category_name, name, code, description, is_active</strong>.
                Kalau code product sudah ada, data akan di-update. Kalau belum ada, data baru akan dibuat.
            </div>
        </div>
    </div>
</body>
</html>