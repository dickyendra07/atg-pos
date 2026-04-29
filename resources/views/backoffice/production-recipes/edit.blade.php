<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Production Recipe - Back Office ATG POS</title>
    <style>
        :root {
            --text: #111827;
            --muted: #6b7280;
            --green: #166534;
            --green-soft: #e8fff1;
            --blue: #3730a3;
            --blue-soft: #eef2ff;
            --red: #9b1c1c;
            --red-soft: #ffe8e8;
            --shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
            --orange-soft: #fff7ed;
            --orange-text: #c2410c;
            --navy-soft: #eef2ff;
            --navy-text: #0f172a;
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
            max-width: 1340px;
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
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .subtitle {
            margin-top: 10px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
            max-width: 780px;
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

        .btn-primary { background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%); }
        .btn-green { background: linear-gradient(135deg, #166534 0%, #1f7a44 100%); }
        .btn-dark { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); }
        .btn-danger-small {
            min-height: 38px;
            padding: 0 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            color: white;
            border: 0;
            cursor: pointer;
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
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
            font-weight: 700;
        }

        .alert-success {
            background: #e8fff1;
            color: #17663a;
            border: 1px solid #ccefd8;
        }

        .alert-error {
            background: var(--red-soft);
            color: var(--red);
            border: 1px solid #fecaca;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            gap: 20px;
            margin-bottom: 20px;
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

        .field {
            margin-bottom: 16px;
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

        .summary-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .summary-line {
            font-size: 14px;
            line-height: 1.8;
            color: #374151;
        }

        .label {
            color: #6b7280;
            font-weight: 700;
            margin-right: 6px;
        }

        .type-output,
        .type-input {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .type-output {
            background: var(--orange-soft);
            color: var(--orange-text);
        }

        .type-input {
            background: var(--navy-soft);
            color: var(--navy-text);
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 860px;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 18px;
            overflow: hidden;
        }

        th, td {
            text-align: left;
            padding: 16px 14px;
            border-bottom: 1px solid #edf1f6;
            vertical-align: middle;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            color: #6b7280;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-active {
            background: var(--green-soft);
            color: #17663a;
        }

        .status-inactive {
            background: #f3f4f6;
            color: #6b7280;
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

        .empty {
            padding: 18px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 16px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }

        @media (max-width: 980px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="shell">
        <div class="topbar">
            <div>
                <h1 class="title">Kelola Production Recipe</h1>

            </div>

            <a href="{{ route('backoffice.production-recipes.index') }}" class="btn btn-dark">Kembali</a>
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

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            <div class="grid-2">
                <div class="card">
                    <div class="card-head">
                        <h2 class="card-title">Header Production Recipe</h2>

                    </div>

                    <div class="card-body">
                        <div class="summary-box">
                            <div class="summary-line"><span class="label">Current Output:</span><span class="type-output">{{ $productionRecipe->outputIngredient->name ?? '-' }}</span></div>
                            <div class="summary-line"><span class="label">Output Qty:</span>{{ number_format((float) $productionRecipe->output_qty, 2, ',', '.') }} {{ $productionRecipe->output_unit }}</div>
                            <div class="summary-line">
                                <span class="label">Status:</span>
                                @if($productionRecipe->is_active)
                                    <span class="status-badge status-active">Active</span>
                                @else
                                    <span class="status-badge status-inactive">Inactive</span>
                                @endif
                            </div>
                        </div>

                        <form method="POST" action="{{ route('backoffice.production-recipes.update', $productionRecipe->id) }}">
                            @csrf
                            @method('PUT')

                            <div class="field">
                                <label>Output Ingredient (Setengah Jadi)</label>
                                <select name="output_ingredient_id" required>
                                    <option value="">Pilih output ingredient</option>
                                    @foreach($outputIngredients as $ingredient)
                                        <option value="{{ $ingredient->id }}" @selected(old('output_ingredient_id', $productionRecipe->output_ingredient_id) == $ingredient->id)>
                                            {{ $ingredient->name }} ({{ $ingredient->unit }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label>Recipe Name</label>
                                <input type="text" name="name" value="{{ old('name', $productionRecipe->name) }}" required>
                            </div>

                            <div class="field">
                                <label>Output Qty per Batch</label>
                                <input type="number" name="output_qty" min="0.01" step="0.01" value="{{ old('output_qty', $productionRecipe->output_qty) }}" required>
                            </div>

                            <div class="field">
                                <label>Status</label>
                                <select name="is_active" required>
                                    <option value="1" @selected(old('is_active', (string) (int) $productionRecipe->is_active) == '1')>Active</option>
                                    <option value="0" @selected(old('is_active', (string) (int) $productionRecipe->is_active) == '0')>Inactive</option>
                                </select>
                            </div>

                            <div class="actions-row">
                                <button type="submit" class="btn btn-primary">Update Header</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h2 class="card-title">Tambah Bahan Input</h2>

                    </div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('backoffice.production-recipes.items.store', $productionRecipe->id) }}">
                            @csrf

                            <div class="field">
                                <label>Input Ingredient (Mentah)</label>
                                <select name="input_ingredient_id" required>
                                    <option value="">Pilih ingredient mentah</option>
                                    @foreach($inputIngredients as $ingredient)
                                        <option value="{{ $ingredient->id }}">
                                            {{ $ingredient->name }} ({{ $ingredient->unit }})
                                        </option>
                                    @endforeach
                                </select>

                            </div>

                            <div class="field">
                                <label>Qty</label>
                                <input type="number" name="qty" min="0.01" step="0.01" required>
                            </div>

                            <div class="actions-row">
                                <button type="submit" class="btn btn-green">Tambah Bahan Input</button>
                            </div>
                        </form>

                        <div class="note">
                            Halaman ini membentuk master recipe internal.
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">Daftar Bahan Input</h2>

                </div>

                <div class="card-body">
                    @if($productionRecipe->items->count())
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Ingredient</th>
                                    <th>Category</th>
                                    <th>Type</th>
                                    <th>Unit</th>
                                    <th>Qty</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($productionRecipe->items as $item)
                                    <tr>
                                        <td>{{ $item->inputIngredient->name ?? '-' }}</td>
                                        <td>{{ $item->inputIngredient->category->name ?? '-' }}</td>
                                        <td><span class="type-input">Mentah</span></td>
                                        <td>{{ $item->unit }}</td>
                                        <td>{{ number_format((float) $item->qty, 2, ',', '.') }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('backoffice.production-recipes.items.destroy', [$productionRecipe->id, $item->id]) }}" onsubmit="return confirm('Yakin hapus bahan input ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-danger-small">Hapus</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty">
                            Production recipe ini belum punya bahan input sama sekali.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>