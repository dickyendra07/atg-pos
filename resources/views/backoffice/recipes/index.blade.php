@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Recipes - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .recipes-shell {
            display: grid;
            gap: 22px;
        }

        .recipes-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .recipes-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .recipes-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #f1e3da;
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .recipes-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .recipes-subtitle {
            margin: 0;
            max-width: 800px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .recipes-actions {
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

        .btn-blue {
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
        }

        .btn-orange {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
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

        .card-head {
            padding: 24px 24px 0;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 16px 18px;
            font-size: 14px;
            line-height: 1.8;
            color: #374151;
        }

        .summary-grid {
            padding: 20px 24px 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .summary-card {
            border-radius: 22px;
            padding: 20px;
            border: 1px solid #e8edf4;
            background: rgba(255,255,255,0.92);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            min-height: 140px;
        }

        .summary-card.orange {
            background: linear-gradient(180deg, #fff9f6 0%, #ffffff 100%);
            border-color: #f4ddd0;
        }

        .summary-card.green {
            background: linear-gradient(180deg, #f5fcf7 0%, #ffffff 100%);
            border-color: #d8f0de;
        }

        .summary-card.blue {
            background: linear-gradient(180deg, #f7faff 0%, #ffffff 100%);
            border-color: #dbe7ff;
        }

        .summary-card.violet {
            background: linear-gradient(180deg, #f8f7ff 0%, #ffffff 100%);
            border-color: #e3deff;
        }

        .summary-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .summary-value {
            font-size: 36px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 12px;
        }

        .summary-card.orange .summary-value { color: #c9552a; }
        .summary-card.green .summary-value { color: #166534; }
        .summary-card.blue .summary-value { color: #1d4ed8; }
        .summary-card.violet .summary-value { color: #5b4bd1; }

        .summary-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
        }

        .table-wrap {
            padding: 24px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1280px;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 22px;
            overflow: hidden;
        }

        thead th {
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 16px 14px;
            background: #f8fafc;
            border-bottom: 1px solid #e8edf4;
            white-space: nowrap;
        }

        tbody td {
            padding: 16px 14px;
            border-bottom: 1px solid #edf1f6;
            vertical-align: middle;
            font-size: 14px;
            color: #111827;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .recipe-name {
            font-weight: 800;
            color: #111827;
            font-size: 15px;
        }

        .item-pill {
            display: inline-flex;
            align-items: center;
            background: #f8fafc;
            color: #374151;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            margin: 2px 6px 2px 0;
            border: 1px solid #e5e7eb;
        }

        .type-badge,
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            margin: 2px 6px 2px 0;
        }

        .badge-raw {
            background: #fff7ed;
            color: #b45309;
        }

        .badge-semi {
            background: #eef2ff;
            color: #3730a3;
        }

        .status-active {
            background: #e8fff1;
            color: #17663a;
        }

        .status-inactive {
            background: #fff1f1;
            color: #b42318;
        }

        .btn-small {
            min-height: 34px;
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 10px;
            box-shadow: none;
            background: #2563eb;
            color: white;
        }

        .error-list {
            margin: 10px 0 0 18px;
            padding: 0;
            font-weight: 600;
        }

        .error-list li {
            margin-bottom: 6px;
            line-height: 1.5;
        }

        .empty {
            margin: 24px;
            padding: 18px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 16px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }

        .note {
            margin: 0 24px 24px;
            background: #eef2ff;
            color: #3730a3;
            padding: 16px 18px;
            border-radius: 16px;
            font-weight: 700;
            border: 1px solid #dbe3ff;
            line-height: 1.7;
        }


        .recipe-filter-card {
            margin: 20px 24px 0;
            padding: 18px;
            border: 1px solid #e8edf4;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.04);
        }

        .recipe-filter-form {
            display: grid;
            grid-template-columns: 1.4fr 0.8fr 0.9fr auto auto;
            gap: 12px;
            align-items: end;
        }

        .filter-field label {
            display: block;
            font-size: 11px;
            font-weight: 900;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
        }

        .filter-field input,
        .filter-field select {
            width: 100%;
            min-height: 46px;
            box-sizing: border-box;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: #ffffff;
            padding: 0 14px;
            font-size: 14px;
            color: #111827;
            outline: none;
        }

        .filter-field input:focus,
        .filter-field select:focus {
            border-color: rgba(232,106,58,0.70);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .recipe-table th,
        .recipe-table td {
            text-align: center;
            vertical-align: middle;
        }

        .recipe-items-cell {
            text-align: center;
        }

        .recipe-item-row {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 6px;
        }

        @media (max-width: 980px) {
            .recipe-filter-form {
                grid-template-columns: 1fr;
            }

            .recipe-filter-card {
                margin-left: 18px;
                margin-right: 18px;
            }
        }

        @media (max-width: 1280px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 780px) {
            .recipes-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .recipes-title {
                font-size: 32px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .table-wrap,
            .card-head,
            .summary-grid {
                padding-left: 18px;
                padding-right: 18px;
            }

            .note,
            .empty {
                margin-left: 18px;
                margin-right: 18px;
            }
        }
    </style>

    <div class="recipes-shell">
        <div class="recipes-topbar">
            <div class="recipes-title-block">
                <div class="recipes-kicker">Recipes Workspace</div>
                <h1 class="recipes-title">Back Office - Recipes</h1>
                <p class="recipes-subtitle">
                    Kelola recipe produk jual, susunan ingredient, import dan export CSV, serta bahan mentah dan setengah jadi.
                </p>
            </div>

            <div class="recipes-actions">
                <a href="{{ route('backoffice.recipes.export.csv') }}" class="btn btn-blue">Export CSV</a>
                <a href="{{ route('backoffice.recipes.import') }}" class="btn btn-orange">Import CSV</a>
                <a href="{{ route('backoffice.recipes.create') }}" class="btn btn-green">Tambah Recipe</a>
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

        @if(session('import_errors') && count(session('import_errors')) > 0)
            <div class="alert alert-error">
                Detail baris yang dilewati:
                <ul class="error-list">
                    @foreach(session('import_errors') as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-head">
                <div class="info-box">
                    <strong>User:</strong> {{ $user->name }}<br>
                    <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                    <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card orange">
                    <div class="summary-label">Total Recipes</div>
                    <div class="summary-value">{{ $recipes->count() }}</div>
                    <div class="summary-desc">Jumlah seluruh recipe produk jual yang tersimpan di sistem.</div>
                </div>

                <div class="summary-card green">
                    <div class="summary-label">Active Recipes</div>
                    <div class="summary-value">{{ $recipes->where('is_active', true)->count() }}</div>
                    <div class="summary-desc">Recipe aktif yang siap dipakai untuk deduction dan operasional.</div>
                </div>

                <div class="summary-card blue">
                    <div class="summary-label">Total Recipe Items</div>
                    <div class="summary-value">{{ $recipes->sum(fn($recipe) => $recipe->items->count()) }}</div>
                    <div class="summary-desc">Jumlah seluruh item ingredient yang dipakai di semua recipe.</div>
                </div>

                <div class="summary-card violet">
                    <div class="summary-label">Semi Finished Used</div>
                    <div class="summary-value">{{ $recipes->flatMap->items->filter(fn($item) => $item->ingredient?->ingredient_type === \App\Models\Ingredient::TYPE_SEMI_FINISHED)->count() }}</div>
                    <div class="summary-desc">Jumlah item recipe yang sudah memakai bahan setengah jadi.</div>
                </div>
            </div>

            <div class="recipe-filter-card">
                <form method="GET" action="{{ route('backoffice.recipes.index') }}" class="recipe-filter-form">
                    <div class="filter-field">
                        <label for="search">Search</label>
                        <input
                            type="text"
                            name="search"
                            id="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Cari recipe / product / variant / ingredient"
                        >
                    </div>

                    <div class="filter-field">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">Semua Status</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="ingredient_type">Ingredient Type</label>
                        <select name="ingredient_type" id="ingredient_type">
                            <option value="">Semua Type</option>
                            <option value="{{ \App\Models\Ingredient::TYPE_RAW }}" @selected(($filters['ingredient_type'] ?? '') === \App\Models\Ingredient::TYPE_RAW)>Mentah</option>
                            <option value="{{ \App\Models\Ingredient::TYPE_SEMI_FINISHED }}" @selected(($filters['ingredient_type'] ?? '') === \App\Models\Ingredient::TYPE_SEMI_FINISHED)>Setengah Jadi</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-orange">Filter</button>
                    <a href="{{ route('backoffice.recipes.index') }}" class="btn btn-dark">Reset</a>
                </form>
            </div>

            @if($recipes->count())
                <div class="table-wrap">
                    <table class="recipe-table">
                        <thead>
                            <tr>
                                <th>Recipe Name</th>
                                <th>Product</th>
                                <th>Variant</th>
                                <th>Recipe Items</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recipes as $recipe)
                                <tr>
                                    <td>
                                        <div class="recipe-name">{{ $recipe->name }}</div>
                                    </td>
                                    <td>{{ $recipe->variant->product->name ?? '-' }}</td>
                                    <td>{{ $recipe->variant->name ?? '-' }}</td>
                                    <td class="recipe-items-cell">
                                        @forelse($recipe->items as $item)
                                            @php
                                                $ingredientType = $item->ingredient?->ingredient_type;
                                                $ingredientTypeLabel = $item->ingredient?->ingredientTypeLabel() ?? 'Mentah';
                                            @endphp

                                            <div class="recipe-item-row">
                                                <span class="item-pill">
                                                    {{ $item->ingredient->name ?? '-' }}
                                                    - {{ number_format((float) $item->qty, 2, ',', '.') }}
                                                    {{ $item->unit ?? $item->ingredient->unit ?? '' }}
                                                </span>

                                                @if($ingredientType === \App\Models\Ingredient::TYPE_SEMI_FINISHED)
                                                    <span class="type-badge badge-semi">{{ $ingredientTypeLabel }}</span>
                                                @else
                                                    <span class="type-badge badge-raw">{{ $ingredientTypeLabel }}</span>
                                                @endif
                                            </div>
                                        @empty
                                            -
                                        @endforelse
                                    </td>
                                    <td>
                                        @if($recipe->is_active)
                                            <span class="status-badge status-active">Active</span>
                                        @else
                                            <span class="status-badge status-inactive">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('backoffice.recipes.edit', $recipe->id) }}" class="btn btn-small">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">
                    Belum ada recipe yang cocok dengan filter aktif.
                </div>
            @endif

        </div>
    </div>
@endsection