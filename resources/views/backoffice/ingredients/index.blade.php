<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingredients - Back Office ATG POS</title>
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
            --green: #166534;
            --green-soft: #e8fff1;
            --blue: #2563eb;
            --blue-soft: #eff6ff;
            --red: #dc2626;
            --red-soft: #ffe8e8;
            --shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
            --navy: #0f172a;
            --navy-soft: #eef2ff;
            --orange-soft: #fff7ed;
            --orange-text: #c2410c;
            --orange-border: #fed7aa;
        }

        * {
            box-sizing: border-box;
        }

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
            max-width: 1440px;
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
            max-width: 760px;
        }

        .title {
            margin: 0;
            font-size: 30px;
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

        .btn-import {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
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

        .alert-success {
            background: #e8fff1;
            color: #17663a;
            border-color: #ccefd8;
            font-weight: 700;
        }

        .alert-error {
            background: #ffe8e8;
            color: #9b1c1c;
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
            font-size: 34px;
            font-weight: 800;
            line-height: 1.05;
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

        .filter-card {
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 22px;
            box-shadow: var(--shadow);
            padding: 18px;
            margin-bottom: 22px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 12px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

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

        .field select:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .table-card {
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-head {
            padding: 22px 22px 0;
        }

        .table-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .table-subtitle {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .table-wrap {
            padding: 0 22px 22px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1120px;
            border-collapse: collapse;
            background: var(--surface);
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

        tbody tr:last-child td {
            border-bottom: 0;
        }

        tbody tr:hover {
            background: #fcfcfd;
        }

        .ingredient-name {
            font-weight: 700;
            color: #111827;
        }

        .category-text,
        .unit-text,
        .muted-text {
            color: #4b5563;
        }

        .number-text {
            font-weight: 700;
            color: #111827;
        }

        .status-badge,
        .type-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-active {
            background: var(--green-soft);
            color: #17663a;
        }

        .status-inactive {
            background: #f3f4f6;
            color: #6b7280;
        }

        .type-raw {
            background: var(--navy-soft);
            color: var(--navy);
        }

        .type-semi {
            background: var(--orange-soft);
            color: var(--orange-text);
            border: 1px solid var(--orange-border);
        }

        .action-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-small {
            min-height: 38px;
            padding: 0 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            color: white;
            text-decoration: none;
            border: 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-edit {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
        }

        .btn-delete {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        }

        .delete-form {
            margin: 0;
        }

        .empty {
            margin: 0 22px 22px;
            padding: 18px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 16px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }

        .note {
            margin: 20px 22px 22px;
            background: #eef2ff;
            color: #3730a3;
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

            .actions {
                width: 100%;
            }

            .filter-form {
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
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="shell">
            <div class="topbar">
                <div class="title-wrap">
                    <h1 class="title">Back Office · Ingredients</h1>
                    <div class="subtitle">
                        Kelola seluruh bahan baku operasional dalam satu halaman yang lebih rapi, bersih, dan mudah dicek sebelum dipakai ke inventory, recipe, dan transfer.
                    </div>
                </div>

                <div class="actions">
                    <a href="{{ route('backoffice.ingredients.import') }}" class="btn btn-import">Import CSV</a>
                    <a href="{{ route('backoffice.ingredients.create') }}" class="btn btn-primary">Tambah Ingredient</a>
                    <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Kembali</a>
                </div>
            </div>

            <div class="content">
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-error">
                        {{ session('error') }}
                    </div>
                @endif

                @if(session('import_errors') && count(session('import_errors')))
                    <div class="alert alert-error">
                        <div style="margin-bottom:8px;">Detail baris yang dilewati:</div>
                        <ul style="margin:0 0 0 18px; padding:0;">
                            @foreach(session('import_errors') as $error)
                                <li style="margin-bottom:6px;">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="hero-card">
                    <div class="hero-kicker">Ingredients Master</div>
                    <h2 class="hero-heading">Raw material list yang siap dipakai ke seluruh flow operasional.</h2>
                    <p class="hero-text">
                        Semua bahan yang ada di halaman ini bisa dipakai untuk stock in, adjustment, transfer, recipe, dan summary inventory. Sekarang ingredient juga sudah bisa dibedakan antara bahan mentah dan bahan setengah jadi.
                    </p>
                </div>

                <div class="filter-card">
                    <form method="GET" action="{{ route('backoffice.ingredients.index') }}" class="filter-form">
                        <div class="field">
                            <label>Tipe Bahan</label>
                            <select name="ingredient_type">
                                <option value="">Semua tipe bahan</option>
                                @foreach($ingredientTypeOptions as $typeValue => $typeLabel)
                                    <option value="{{ $typeValue }}" @selected($selectedIngredientType === $typeValue)>
                                        {{ $typeLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                        <a href="{{ route('backoffice.ingredients.index') }}" class="btn btn-dark">Reset</a>
                    </form>
                </div>

                <div class="table-card">
                    <div class="table-head">
                        <h2 class="table-title">All Ingredients</h2>
                        <p class="table-subtitle">
                            Lihat kategori, tipe bahan, unit, minimum stock, harga dasar, dan status aktif setiap ingredient dalam satu tabel yang lebih enak dibaca.
                        </p>
                    </div>

                    @if($ingredients->count())
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Unit</th>
                                        <th>Minimum Stock</th>
                                        <th>Cost per Unit</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ingredients as $ingredient)
                                        <tr>
                                            <td class="category-text">{{ $ingredient->category->name ?? '-' }}</td>
                                            <td class="ingredient-name">{{ $ingredient->name }}</td>
                                            <td>
                                                @if($ingredient->ingredient_type === \App\Models\Ingredient::TYPE_SEMI_FINISHED)
                                                    <span class="type-badge type-semi">Setengah Jadi</span>
                                                @else
                                                    <span class="type-badge type-raw">Mentah</span>
                                                @endif
                                            </td>
                                            <td class="unit-text">{{ $ingredient->unit }}</td>
                                            <td class="number-text">{{ number_format((float) $ingredient->minimum_stock, 0, ',', '.') }}</td>
                                            <td class="number-text">{{ number_format((float) $ingredient->cost_per_unit, 0, ',', '.') }}</td>
                                            <td>
                                                @if($ingredient->is_active)
                                                    <span class="status-badge status-active">Active</span>
                                                @else
                                                    <span class="status-badge status-inactive">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="action-row">
                                                    <a href="{{ route('backoffice.ingredients.edit', $ingredient) }}" class="btn-small btn-edit">Edit</a>

                                                    <form method="POST" action="{{ route('backoffice.ingredients.destroy', $ingredient) }}" class="delete-form" onsubmit="return confirm('Yakin hapus ingredient ini?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn-small btn-delete">Hapus</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty">
                            Belum ada ingredient tersimpan.
                        </div>
                    @endif

                    <div class="note">
                        Batch 1 aktif: ingredient sekarang sudah dibedakan antara bahan mentah dan bahan setengah jadi sebagai fondasi sebelum masuk flow produksi internal.
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>