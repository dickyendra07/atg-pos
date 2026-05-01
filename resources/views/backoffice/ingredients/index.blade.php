@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Ingredients - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .ingredients-shell {
            display: grid;
            gap: 22px;
        }

        .ingredients-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .ingredients-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .ingredients-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #d8f0de;
            color: #166534;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .ingredients-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .ingredients-subtitle {
            margin: 0;
            max-width: 780px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .ingredients-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            border: 0;
            cursor: pointer;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 14px;
            color: white;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(15,23,42,0.10);
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.96;
        }

        .btn-dark {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .btn-green {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
        }

        .btn-orange {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        }

        .btn-blue {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
        }

        .alert {
            border-radius: 18px;
            padding: 16px 18px;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.7;
        }

        .alert-success {
            background: #e8fff1;
            color: #17663a;
            border: 1px solid #ccefd8;
        }

        .alert-error {
            background: #fff1f1;
            color: #b42318;
            border: 1px solid #fecaca;
        }

        .card {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 30px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .hero-card {
            margin: 24px 24px 0;
            background: linear-gradient(135deg, #ffffff 0%, #f7fcf8 65%, #eefaf1 100%);
            border: 1px solid #d8f0de;
            border-radius: 28px;
            padding: 24px;
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
            background: radial-gradient(circle, rgba(22,101,52,0.12) 0%, rgba(22,101,52,0.03) 65%, rgba(22,101,52,0) 80%);
            pointer-events: none;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #d8f0de;
            color: #166534;
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
            color: #111827;
        }

        .hero-text {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
            max-width: 760px;
            position: relative;
            z-index: 1;
        }

        .filter-card {
            margin: 20px 24px 0;
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 22px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            padding: 18px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
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
            border-color: rgba(22,101,52,0.65);
            box-shadow: 0 0 0 4px rgba(22,101,52,0.10);
        }

        .table-card {
            margin: 20px 24px 24px;
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
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
            color: #6b7280;
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
        .unit-text {
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
            background: #e8fff1;
            color: #17663a;
        }

        .status-inactive {
            background: #f3f4f6;
            color: #6b7280;
        }

        .type-raw {
            background: #eef2ff;
            color: #0f172a;
        }

        .type-semi {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
        }

        .action-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-small {
            width: 68px;
            min-width: 68px;
            height: 38px;
            min-height: 38px;
            padding: 0 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            color: white;
            text-decoration: none;
            border: 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: none;
            box-sizing: border-box;
            line-height: 1;
        }

        .btn-edit {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
        }

        .btn-delete {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        }

        .delete-form {
            margin: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            .ingredients-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .ingredients-title {
                font-size: 32px;
            }
        }

        @media (max-width: 780px) {
            .hero-heading {
                font-size: 28px;
            }

            .hero-card,
            .filter-card,
            .table-card {
                margin-left: 18px;
                margin-right: 18px;
            }
        }

    </style>

    <div class="ingredients-shell">
        <div class="ingredients-topbar">
            <div class="ingredients-title-block">

                <h1 class="ingredients-title">Back Office - Ingredients</h1>

            </div>

            <div class="ingredients-actions">
                <a href="{{ route('backoffice.ingredients.export.csv', ['ingredient_type' => $selectedIngredientType]) }}" class="btn btn-blue">Export CSV</a>
                <a href="{{ route('backoffice.ingredients.import') }}" class="btn btn-green">Import CSV</a>
                <a href="{{ route('backoffice.ingredients.create') }}" class="btn btn-orange">Tambah Ingredient</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Dashboard</a>
            </div>
        </div>

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

        <div class="card">


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

                    <button type="submit" class="btn btn-orange">Apply Filter</button>
                    <a href="{{ route('backoffice.ingredients.index') }}" class="btn btn-dark">Reset</a>
                    <a href="{{ route('backoffice.ingredients.export.csv', ['ingredient_type' => $selectedIngredientType]) }}" class="btn btn-blue">Export CSV</a>
                </form>
            </div>

            <div class="table-card">
                <div class="table-head">
                    <h2 class="table-title">All Ingredients</h2>

                </div>

                @if($ingredients->count())
                    <div class="table-wrap">
                        <table class="table-center">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Code</th>
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
                                        <td class="number-text">{{ $ingredient->code ?? '-' }}</td>
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
            </div>
        </div>
    </div>
@endsection