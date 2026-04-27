<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Ingredient - Back Office ATG POS</title>
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
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
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
            background: linear-gradient(135deg, #ffffff 0%, #f7faff 70%, #eef4ff 100%);
            border: 1px solid #dbe7ff;
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
            background: radial-gradient(circle, rgba(37,99,235,0.12) 0%, rgba(37,99,235,0.03) 65%, rgba(37,99,235,0) 80%);
            pointer-events: none;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #dbe7ff;
            color: #1d4ed8;
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
            border-color: rgba(37,99,235,0.75);
            box-shadow: 0 0 0 4px rgba(37,99,235,0.10);
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
                    <h1 class="title">Edit Ingredient</h1>
                    <div class="subtitle">
                        Perbarui data bahan baku supaya tetap sinkron dengan inventory control, recipe, import, dan seluruh flow operasional back office.
                    </div>
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

                <div class="hero-card">
                    <div class="hero-kicker">Edit Ingredient</div>
                    <h2 class="hero-heading">Rapikan data bahan tanpa bikin flow operasional berantakan.</h2>
                    <p class="hero-text">
                        Saat nama, unit, tipe bahan, minimum stock, atau cost berubah, pastikan tetap konsisten dengan kebutuhan operasional dan pembacaan data di inventory control.
                    </p>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h2 class="card-title">Form Edit Ingredient</h2>
                        <p class="card-subtitle">
                            Update data ingredient yang sudah ada. Kalau nama diubah, sistem akan menyesuaikan code ingredient secara otomatis.
                        </p>
                    </div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('backoffice.ingredients.update', $ingredient) }}">
                            @csrf
                            @method('PUT')

                            <div class="grid">
                                <div class="field">
                                    <label>Category</label>
                                    <select name="ingredient_category_id" required>
                                        <option value="">Pilih category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" @selected(old('ingredient_category_id', $ingredient->ingredient_category_id) == $category->id)>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="field">
                                    <label>Unit</label>
                                    <input type="text" name="unit" value="{{ old('unit', $ingredient->unit) }}" placeholder="contoh: gram / ml / pcs" required>
                                </div>

                                <div class="field full">
                                    <label>Ingredient Name</label>
                                    <input type="text" name="name" value="{{ old('name', $ingredient->name) }}" required>
                                    <div class="helper">Nama ingredient sebaiknya tetap konsisten supaya tidak membingungkan saat import, stock movement, dan summary inventory.</div>
                                </div>

                                <div class="field">
                                    <label>Tipe Bahan</label>
                                    <select name="ingredient_type" required>
                                        @foreach($ingredientTypeOptions as $typeValue => $typeLabel)
                                            <option value="{{ $typeValue }}" @selected(old('ingredient_type', $ingredient->ingredient_type ?? \App\Models\Ingredient::TYPE_RAW) === $typeValue)>
                                                {{ $typeLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="helper">Mentah untuk bahan dasar, setengah jadi untuk bahan hasil olahan internal.</div>
                                </div>

                                <div class="field">
                                    <label>Minimum Stock</label>
                                    <input type="number" name="minimum_stock" min="0" step="0.01" value="{{ old('minimum_stock', $ingredient->minimum_stock) }}" required>
                                </div>

                                <div class="field">
                                    <label>Cost per Unit</label>
                                    <input type="number" name="cost_per_unit" min="0" step="0.01" value="{{ old('cost_per_unit', $ingredient->cost_per_unit) }}" required>
                                </div>

                                <div class="field">
                                    <label>Status</label>
                                    <select name="is_active" required>
                                        <option value="1" @selected(old('is_active', (string) (int) $ingredient->is_active) == '1')>Active</option>
                                        <option value="0" @selected(old('is_active', (string) (int) $ingredient->is_active) == '0')>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="actions-row">
                                <button type="submit" class="btn btn-primary">Update Ingredient</button>
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