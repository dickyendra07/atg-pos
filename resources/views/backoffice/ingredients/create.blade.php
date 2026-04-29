<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Ingredient - Back Office ATG POS</title>
    <style>
        :root {
            --bg: #f3f6fb;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --brand: #e86a3a;
            --brand-dark: #c9552a;
            --brand-soft: #fff3eb;
            --green: #166534;
            --green-soft: #e8fff1;
            --blue: #3730a3;
            --blue-soft: #eef2ff;
            --red: #9b1c1c;
            --red-soft: #ffe8e8;
            --shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
            color: var(--text);
        }

        .page {
            min-height: 100vh;
            padding: 24px;
        }

        .shell {
            max-width: 1040px;
            margin: 0 auto;
            background: rgba(255,255,255,0.62);
            border: 1px solid rgba(255,255,255,0.9);
            border-radius: 30px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            padding: 28px 28px 0;
        }

        .title-wrap {
            max-width: 700px;
        }

        .title {
            margin: 0;
            font-size: 32px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.03em;
        }

        .subtitle {
            margin-top: 10px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .actions {
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
            border-radius: 14px;
            font-weight: 800;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            box-shadow: 0 10px 22px rgba(15,23,42,0.10);
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.97;
        }

        .btn-primary {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        }

        .btn-dark {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .content {
            padding: 22px 28px 30px;
        }

        .alert {
            margin-bottom: 18px;
            border-radius: 16px;
            padding: 15px 18px;
            font-size: 14px;
            line-height: 1.7;
            border: 1px solid transparent;
        }

        .alert-error {
            background: var(--red-soft);
            color: var(--red);
            border-color: #fecaca;
            font-weight: 700;
        }

        .hero-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff9f5 70%, #fff1ea 100%);
            border: 1px solid #f0e1d8;
            border-radius: 28px;
            padding: 24px;
            margin-bottom: 22px;
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
            background: radial-gradient(circle, rgba(232,106,58,0.14) 0%, rgba(232,106,58,0.03) 65%, rgba(232,106,58,0) 80%);
            pointer-events: none;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #f2dfd4;
            color: var(--brand-dark);
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
            font-size: 30px;
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -0.03em;
            position: relative;
            z-index: 1;
        }

        .hero-text {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
            max-width: 760px;
            position: relative;
            z-index: 1;
        }

        .card {
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-head {
            padding: 22px 22px 0;
        }

        .card-title {
            margin: 0 0 8px;
            font-size: 22px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .card-subtitle {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .card-body {
            padding: 0 22px 22px;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #374151;
        }

        .field input,
        .field select {
            width: 100%;
            min-height: 50px;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 0 14px;
            font-size: 14px;
            background: white;
            color: #111827;
            outline: none;
        }

        .field input:focus,
        .field select:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .helper {
            margin-top: 6px;
            color: #6b7280;
            font-size: 12px;
            line-height: 1.6;
        }

        .actions-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .note {
            margin-top: 18px;
            background: var(--blue-soft);
            color: var(--blue);
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #dbe3ff;
            line-height: 1.7;
        }

        @media (max-width: 900px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .page {
                padding: 14px;
            }

            .topbar,
            .content {
                padding-left: 18px;
                padding-right: 18px;
            }

            .hero-heading {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="shell">
            <div class="topbar">
                <div class="title-wrap">
                    <h1 class="title">Tambah Ingredient</h1>

                </div>

                <div class="actions">
                    <a href="{{ route('backoffice.ingredients.index') }}" class="btn btn-dark">Kembali</a>
                </div>
            </div>

            <div class="content">
                @if($errors->any())
                    <div class="alert alert-error">
                        <ul style="margin:0; padding-left:18px;">
                            @foreach($errors->all() as $error)
                                <li style="margin-bottom:6px;">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif


                <div class="card">
                    <div class="card-head">
                        <h2 class="card-title">Form Ingredient</h2>

                    </div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('backoffice.ingredients.store') }}">
                            @csrf

                            <div class="grid">
                                <div class="field">
                                    <label>Category</label>
                                    <select name="ingredient_category_id" required>
                                        <option value="">Pilih category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" @selected(old('ingredient_category_id') == $category->id)>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="field">
                                    <label>Unit</label>
                                    <input type="text" name="unit" value="{{ old('unit') }}" placeholder="contoh: gram / ml / pcs" required>
                                </div>

                                <div class="field full">
                                    <label>Ingredient Name</label>
                                    <input type="text" name="name" value="{{ old('name') }}" placeholder="contoh: Fresh Milk" required>

                                </div>

                                <div class="field">
                                    <label>Tipe Bahan</label>
                                    <select name="ingredient_type" required>
                                        @foreach($ingredientTypeOptions as $typeValue => $typeLabel)
                                            <option value="{{ $typeValue }}" @selected(old('ingredient_type', \App\Models\Ingredient::TYPE_RAW) === $typeValue)>
                                                {{ $typeLabel }}
                                            </option>
                                        @endforeach
                                    </select>

                                </div>

                                <div class="field">
                                    <label>Minimum Stock</label>
                                    <input type="number" name="minimum_stock" min="0" step="0.01" value="{{ old('minimum_stock', 0) }}" required>
                                </div>

                                <div class="field">
                                    <label>Cost per Unit</label>
                                    <input type="number" name="cost_per_unit" min="0" step="0.01" value="{{ old('cost_per_unit', 0) }}" required>
                                </div>

                                <div class="field">
                                    <label>Status</label>
                                    <select name="is_active" required>
                                        <option value="1" @selected(old('is_active', '1') == '1')>Active</option>
                                        <option value="0" @selected(old('is_active') == '0')>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="actions-row">
                                <button type="submit" class="btn btn-primary">Simpan Ingredient</button>
                                <a href="{{ route('backoffice.ingredients.index') }}" class="btn btn-dark">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>