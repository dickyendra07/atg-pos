<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock In Warehouse - Back Office ATG POS</title>
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
            margin-bottom: 8px;
        }

        .subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .error, .info {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
        }

        .error {
            background: #ffe8e8;
            color: #9b1c1c;
            border: 1px solid #fecaca;
            font-weight: 700;
        }

        .info {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            line-height: 1.75;
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
        <div class="title">Stock In Warehouse</div>


        @if($errors->any())
            <div class="error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <div class="info">
                <strong>Warehouse:</strong> {{ $warehouse->name }}<br>
                <strong>Code:</strong> {{ $warehouse->code }}
            </div>

            <form method="POST" action="{{ route('backoffice.warehouses.stock.store', $warehouse) }}">
                @csrf

                <div class="field">
                    <label>Ingredient</label>
                    <select name="ingredient_id" required>
                        <option value="">Pilih ingredient</option>
                        @foreach($ingredients as $ingredient)
                            <option value="{{ $ingredient->id }}" @selected(old('ingredient_id') == $ingredient->id)>
                                {{ $ingredient->name }} - {{ $ingredient->category->name ?? '-' }} - {{ $ingredient->unit }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label>Qty In</label>
                    <input type="number" name="qty_in" min="0.01" step="0.01" value="{{ old('qty_in') }}" required>
                </div>

                <div class="field">
                    <label>Note</label>
                    <textarea name="note">{{ old('note') }}</textarea>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Simpan Stock In</button>
                    <a href="{{ route('backoffice.warehouses.stock.index', $warehouse) }}" class="btn btn-dark">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>