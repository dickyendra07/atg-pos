<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Recipes CSV - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
        }

        .wrap {
            max-width: 980px;
            margin: 36px auto;
            padding: 0 20px 40px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .title {
            font-size: 30px;
            font-weight: 700;
            color: #111827;
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

        .info {
            margin-bottom: 22px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px 18px;
            line-height: 1.75;
            font-size: 14px;
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

        .template {
            margin-top: 22px;
            background: #eef2ff;
            color: #3730a3;
            padding: 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #dbe3ff;
        }

        .template-title {
            margin-bottom: 10px;
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
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title">Import Recipes CSV</div>

            <div class="top-actions">
                <a href="{{ route('backoffice.recipes.import.template') }}" class="btn btn-success">Download Template CSV</a>
                <a href="{{ route('backoffice.recipes.index') }}" class="btn btn-dark">Kembali ke Recipes</a>
            </div>
        </div>

        @if(session('error'))
            <div class="error">
                {{ session('error') }}
            </div>
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
                <strong>User:</strong> {{ $user->name }}<br>
                <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}
            </div>

            <form method="POST" action="{{ route('backoffice.recipes.import.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="field">
                    <label>Upload File CSV</label>
                    <input type="file" name="file" accept=".csv,text/csv" required>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Import Recipes</button>
                    <a href="{{ route('backoffice.recipes.index') }}" class="btn btn-dark">Batal</a>
                </div>
            </form>

            <div class="template">
                <div class="template-title">Template header CSV wajib:</div>
                <code>variant_code,ingredient_name,qty,is_active</code>

                <div class="template-title" style="margin-top:14px;">Contoh isi:</div>
                <code>r,Black Tea,10,1
r,Liquid Sugar,20,1
l,Black Tea,15,1
l,Liquid Sugar,25,1</code>

                <div class="hint">
                    Pakai tombol <strong>Download Template CSV</strong> supaya format file selalu sesuai sistem.
                </div>
            </div>
        </div>
    </div>
</body>
</html>