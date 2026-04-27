@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Production Recipes - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .production-recipes-shell {
            display: grid;
            gap: 22px;
        }

        .production-recipes-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .production-recipes-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .production-recipes-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #e3deff;
            color: #5b4bd1;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .production-recipes-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .production-recipes-subtitle {
            margin: 0;
            max-width: 820px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .production-recipes-actions {
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
            background: linear-gradient(135deg, #ffffff 0%, #fbfaff 65%, #f4f3ff 100%);
            border: 1px solid #e3deff;
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
            background: radial-gradient(circle, rgba(91,75,209,0.14) 0%, rgba(91,75,209,0.03) 65%, rgba(91,75,209,0) 80%);
            pointer-events: none;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #e3deff;
            color: #5b4bd1;
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
            color: #111827;
            position: relative;
            z-index: 1;
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
            min-width: 1180px;
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
            vertical-align: top;
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

        .recipe-name {
            font-weight: 800;
            color: #111827;
            font-size: 15px;
        }

        .output-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            background: #fff7ed;
            color: #c2410c;
        }

        .item-pill {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            background: #eef2ff;
            color: #0f172a;
            margin: 2px 6px 2px 0;
        }

        .status-badge {
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

        .btn-small {
            min-height: 34px;
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 10px;
            box-shadow: none;
            background: #2563eb;
            color: white;
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

        @media (max-width: 1280px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 780px) {
            .production-recipes-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .production-recipes-title {
                font-size: 32px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .hero-heading {
                font-size: 28px;
            }

            .hero-card,
            .table-card {
                margin-left: 18px;
                margin-right: 18px;
            }
        }
    </style>

    <div class="production-recipes-shell">
        <div class="production-recipes-topbar">
            <div class="production-recipes-title-block">
                <div class="production-recipes-kicker">Production Recipes Workspace</div>
                <h1 class="production-recipes-title">Back Office - Production Recipes</h1>
                <p class="production-recipes-subtitle">
                    Kelola resep internal untuk bahan setengah jadi.
                </p>
            </div>

            <div class="production-recipes-actions">
                <a href="{{ route('backoffice.production-recipes.create') }}" class="btn btn-green">Tambah Production Recipe</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Dashboard</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="hero-card">
                <div class="hero-kicker">Production Recipe</div>
                <h2 class="hero-heading">Definisikan bahan setengah jadi beserta komposisi mentahnya.</h2>
                <p class="hero-text">
                    Master recipe internal untuk mengelola output bahan setengah jadi dan bahan input.
                </p>
            </div>

            <div class="summary-grid">
                <div class="summary-card orange">
                    <div class="summary-label">Total Production Recipes</div>
                    <div class="summary-value">{{ $recipes->count() }}</div>
                    <div class="summary-desc">Jumlah seluruh production recipe internal yang tersimpan di sistem.</div>
                </div>

                <div class="summary-card green">
                    <div class="summary-label">Active Recipes</div>
                    <div class="summary-value">{{ $recipes->where('is_active', true)->count() }}</div>
                    <div class="summary-desc">Recipe aktif yang siap dipakai untuk flow produksi internal.</div>
                </div>

                <div class="summary-card blue">
                    <div class="summary-label">Input Ingredients</div>
                    <div class="summary-value">{{ $recipes->sum(fn($recipe) => $recipe->items->count()) }}</div>
                    <div class="summary-desc">Total bahan input yang dipakai di seluruh production recipe.</div>
                </div>

                <div class="summary-card violet">
                    <div class="summary-label">Output Recipes</div>
                    <div class="summary-value">{{ $recipes->filter(fn($recipe) => !is_null($recipe->outputIngredient))->count() }}</div>
                    <div class="summary-desc">Recipe yang sudah punya output ingredient setengah jadi.</div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-head">
                    <h2 class="table-title">All Production Recipes</h2>
                    <p class="table-subtitle">
                        Output ingredient harus bertipe setengah jadi, sedangkan bahan input dibatasi ke ingredient mentah.
                    </p>
                </div>

                @if($recipes->count())
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Recipe Name</th>
                                    <th>Output Ingredient</th>
                                    <th>Output Qty</th>
                                    <th>Input Ingredients</th>
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
                                        <td>
                                            <span class="output-badge">
                                                {{ $recipe->outputIngredient->name ?? '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong>{{ number_format((float) $recipe->output_qty, 2, ',', '.') }}</strong>
                                            {{ $recipe->output_unit }}
                                        </td>
                                        <td>
                                            @forelse($recipe->items as $item)
                                                <span class="item-pill">
                                                    {{ $item->inputIngredient->name ?? '-' }} - {{ number_format((float) $item->qty, 2, ',', '.') }} {{ $item->unit }}
                                                </span>
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
                                            <a href="{{ route('backoffice.production-recipes.edit', $recipe->id) }}" class="btn btn-small">Kelola Recipe</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty">
                        Belum ada production recipe tersimpan.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection