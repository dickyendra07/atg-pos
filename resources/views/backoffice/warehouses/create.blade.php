<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Warehouse - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
        }

        .wrap {
            max-width: 860px;
            margin: 36px auto;
            padding: 0 20px 40px;
        }

        .title {
            font-size: 30px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            padding: 24px;
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
            margin-bottom: 14px;
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
            margin-top: 18px;
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
    </style>
</head>
<body>
    <div class="wrap">
        <div class="title">Tambah Warehouse</div>

        @if($errors->any())
            <div class="error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <form method="POST" action="{{ route('backoffice.warehouses.store') }}">
                @csrf

                <div class="field">
                    <label>Warehouse Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>

                <div class="field">
                    <label>Warehouse Code</label>
                    <input type="text" name="code" value="{{ old('code') }}" required>
                </div>

                <div class="field">
                    <label>Address</label>
                    <textarea name="address">{{ old('address') }}</textarea>
                </div>

                <div class="field">
                    <label>Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}">
                </div>

                <div class="field">
                    <label>Status</label>
                    <select name="is_active" required>
                        <option value="1" @selected(old('is_active', '1') == '1')>Active</option>
                        <option value="0" @selected(old('is_active') == '0')>Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Simpan Warehouse</button>
                    <a href="{{ route('backoffice.warehouses.index') }}" class="btn btn-dark">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>