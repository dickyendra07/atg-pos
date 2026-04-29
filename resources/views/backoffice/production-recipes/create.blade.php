<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Production Recipe - Back Office ATG POS</title>
    <style>
        :root {
            --text: #111827;
            --muted: #6b7280;
            --brand: #e86a3a;
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

        .title {
            margin: 0;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .subtitle {
            margin-top: 10px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
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
        }

        .btn-primary { background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%); }
        .btn-dark { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); }

        .content {
            padding: 22px 28px 30px;
        }

        .alert {
            margin-bottom: 18px;
            border-radius: 16px;
            padding: 15px 18px;
            font-size: 14px;
            line-height: 1.7;
            font-weight: 700;
            background: var(--red-soft);
            color: var(--red);
            border: 1px solid #fecaca;
        }

        .hero-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff9f5 70%, #fff1ea 100%);
            border: 1px solid #f0e1d8;
            border-radius: 28px;
            padding: 24px;
            margin-bottom: 22px;
        }

        .hero-kicker {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #f2dfd4;
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .hero-heading {
            margin: 0 0 10px;
            font-size: 30px;
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -0.03em;
        }

        .hero-text {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
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
    </style>
</head>
<body>
<div class="page">
    <div class="shell">
        <div class="topbar">
            <div>
                <h1 class="title">Tambah Production Recipe</h1>

            </div>

            <a href="{{ route('backoffice.production-recipes.index') }}" class="btn btn-dark">Kembali</a>
        </div>

        <div class="content">
            @if($errors->any())
                <div class="alert">
                    <ul style="margin:0; padding-left:18px;">
                        @foreach($errors->all() as $error)
                            <li style="margin-bottom:6px;">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif


            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">Form Production Recipe</h2>

                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('backoffice.production-recipes.store') }}">
                        @csrf

                        <div class="grid">
                            <div class="field full">
                                <label>Output Ingredient (Setengah Jadi)</label>
                                <select name="output_ingredient_id" required>
                                    <option value="">Pilih output ingredient</option>
                                    @foreach($outputIngredients as $ingredient)
                                        <option value="{{ $ingredient->id }}" @selected(old('output_ingredient_id') == $ingredient->id)>
                                            {{ $ingredient->name }} ({{ $ingredient->unit }})
                                        </option>
                                    @endforeach
                                </select>

                            </div>

                            <div class="field full">
                                <label>Recipe Name</label>
                                <input type="text" name="name" value="{{ old('name') }}" placeholder="contoh: Produksi Adonan Waffle" required>
                            </div>

                            <div class="field">
                                <label>Output Qty per Batch</label>
                                <input type="number" name="output_qty" min="0.01" step="0.01" value="{{ old('output_qty', 1) }}" required>
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
                            <button type="submit" class="btn btn-primary">Simpan Production Recipe</button>
                            <a href="{{ route('backoffice.production-recipes.index') }}" class="btn btn-dark">Batal</a>
                        </div>
                    </form>

                    <div class="note">
                        Setelah header tersimpan, lanjutkan ke halaman edit untuk menambahkan bahan input mentah.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>