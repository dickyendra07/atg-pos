@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Stock Movements - Back Office';
@endphp

@section('content')
    <style>
        .movement-shell {
            display: grid;
            gap: 22px;
        }

        .movement-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .movement-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .movement-kicker {
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

        .movement-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .movement-subtitle {
            margin: 0;
            max-width: 860px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .movement-actions {
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

        .btn-brand {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        }

        .btn-green {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
        }

        .btn-blue {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
        }

        .card {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 30px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .hero-wrap {
            padding: 24px 24px 0;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 20px;
        }

        .hero-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f7ff 58%, #f2efff 100%);
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
            border: 1px solid #ece8ff;
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

        .info-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e8edf4;
            border-radius: 28px;
            padding: 24px;
        }

        .info-title {
            margin: 0 0 14px;
            font-size: 18px;
            font-weight: 800;
            color: #111827;
        }

        .info-line {
            font-size: 14px;
            line-height: 1.9;
            color: #374151;
        }

        .info-line strong {
            color: #111827;
        }

        .section-card {
            margin: 20px 24px 0;
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }

        .section-head {
            padding: 22px 22px 0;
        }

        .section-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .section-subtitle {
            margin: 0 0 18px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
        }

        .filter-form {
            padding: 0 22px 22px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1.2fr auto auto;
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

        .field select,
        .field input {
            width: 100%;
            min-height: 48px;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
            padding: 0 14px;
            font-size: 14px;
            outline: none;
        }

        .field select:focus,
        .field input:focus {
            border-color: rgba(91,75,209,0.55);
            box-shadow: 0 0 0 4px rgba(91,75,209,0.10);
        }

        .table-wrap {
            padding: 0 22px 22px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1480px;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 18px;
            overflow: hidden;
        }

        th, td {
            text-align: left;
            padding: 15px 14px;
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
            white-space: nowrap;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        tbody tr:hover {
            background: #fcfcfd;
        }

        .date-text {
            min-width: 130px;
            font-weight: 700;
            color: #374151;
            line-height: 1.6;
        }

        .ingredient-name {
            font-weight: 800;
            color: #111827;
        }

        .location-text,
        .reference-text {
            color: #374151;
        }

        .reference-text strong {
            color: #111827;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .badge-green {
            background: #e8fff1;
            color: #17663a;
        }

        .badge-blue {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .badge-yellow {
            background: #fff7ed;
            color: #9a3412;
        }

        .qty-in {
            font-weight: 800;
            color: #166534;
        }

        .qty-out {
            font-weight: 800;
            color: #b91c1c;
        }

        .note-text {
            color: #374151;
            line-height: 1.7;
            min-width: 320px;
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

        .bottom-bar {
            margin: 20px 24px 24px;
            padding: 15px 16px;
            border-radius: 18px;
            background: #eef2ff;
            color: #3730a3;
            border: 1px solid #dbe3ff;
            font-weight: 700;
            font-size: 14px;
            line-height: 1.7;
        }

        @media (max-width: 1380px) {
            .hero-wrap {
                grid-template-columns: 1fr;
            }

            .filter-form {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 860px) {
            .movement-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .movement-title {
                font-size: 32px;
            }

            .hero-heading {
                font-size: 28px;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .hero-wrap {
                padding-left: 18px;
                padding-right: 18px;
            }

            .section-card,
            .bottom-bar {
                margin-left: 18px;
                margin-right: 18px;
            }
        }
    </style>

    <div class="movement-shell">
        <div class="movement-topbar">
            <div class="movement-title-block">

                <h1 class="movement-title">Back Office - Stock Movements</h1>

            </div>

            <div class="movement-actions">
                <a href="{{ route('backoffice.stock-movements.export.csv', request()->query()) }}" class="btn btn-blue">Export CSV</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Dashboard</a>
            </div>
        </div>

        <div class="card">

                    <div class="info-line">
</div>
                    <div class="info-line">
</div>
                    <div class="info-line">
</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">Filter Stock Movements</h2>

                </div>

                <form method="GET" action="{{ route('backoffice.stock-movements.index') }}" class="filter-form">
                    <div class="field">
                        <label>Filter Ingredient</label>
                        <select name="ingredient_id">
                            <option value="">Semua ingredient</option>
                            @foreach($ingredients as $ingredient)
                                <option value="{{ $ingredient->id }}" @selected(($filters['ingredient_id'] ?? '') == $ingredient->id)>
                                    {{ $ingredient->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>Filter Movement Type</label>
                        <select name="movement_type">
                            <option value="">Semua movement type</option>
                            <option value="opening_balance" @selected(($filters['movement_type'] ?? '') === 'opening_balance')>opening_balance</option>
                            <option value="stock_in" @selected(($filters['movement_type'] ?? '') === 'stock_in')>stock_in</option>
                            <option value="transfer_in" @selected(($filters['movement_type'] ?? '') === 'transfer_in')>transfer_in</option>
                            <option value="transfer_out" @selected(($filters['movement_type'] ?? '') === 'transfer_out')>transfer_out</option>
                            <option value="production_in" @selected(($filters['movement_type'] ?? '') === 'production_in')>production_in</option>
                            <option value="production_out" @selected(($filters['movement_type'] ?? '') === 'production_out')>production_out</option>
                            <option value="stock_adjustment" @selected(($filters['movement_type'] ?? '') === 'stock_adjustment')>stock_adjustment</option>
                            <option value="sales_usage" @selected(($filters['movement_type'] ?? '') === 'sales_usage')>sales_usage</option>
                            <option value="sales_usage_warning" @selected(($filters['movement_type'] ?? '') === 'sales_usage_warning')>sales_usage_warning</option>
                            <option value="transfer_cancel_out" @selected(($filters['movement_type'] ?? '') === 'transfer_cancel_out')>transfer_cancel_out</option>
                            <option value="transfer_cancel_return" @selected(($filters['movement_type'] ?? '') === 'transfer_cancel_return')>transfer_cancel_return</option>
                            <option value="transfer_out_reactivated" @selected(($filters['movement_type'] ?? '') === 'transfer_out_reactivated')>transfer_out_reactivated</option>
                            <option value="transfer_in_reactivated" @selected(($filters['movement_type'] ?? '') === 'transfer_in_reactivated')>transfer_in_reactivated</option>
                        </select>
                    </div>

                    <div class="field">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    </div>

                    <div class="field">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </div>

                    <div class="field">
                        <label>Search Note / Reference</label>
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="contoh: produksi / opname / transfer">
                    </div>

                    <button type="submit" class="btn btn-green">Apply Filter</button>
                    <a href="{{ route('backoffice.stock-movements.index') }}" class="btn btn-dark">Reset</a>
                </form>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">Movement Timeline</h2>

                </div>

                @if($stockMovements->count())
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Ingredient</th>
                                    <th>Location Type</th>
                                    <th>Location ID</th>
                                    <th>Movement Type</th>
                                    <th>Qty In</th>
                                    <th>Qty Out</th>
                                    <th>Reference</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stockMovements as $movement)
                                    <tr>
                                        <td class="date-text">
                                            {{ $movement->created_at?->format('Y-m-d') }}<br>
                                            {{ $movement->created_at?->format('H:i:s') }}
                                        </td>
                                        <td class="ingredient-name">{{ $movement->ingredient->name ?? '-' }}</td>
                                        <td class="location-text">{{ ucfirst($movement->location_type ?? '-') }}</td>
                                        <td class="location-text">{{ $movement->location_id ?? '-' }}</td>
                                        <td>
                                            @if(in_array($movement->movement_type, ['stock_in', 'opening_balance', 'transfer_in', 'production_in', 'transfer_cancel_return', 'transfer_in_reactivated']))
                                                <span class="badge badge-green">{{ $movement->movement_type }}</span>
                                            @elseif(in_array($movement->movement_type, ['transfer_out', 'production_out', 'transfer_cancel_out', 'transfer_out_reactivated']))
                                                <span class="badge badge-blue">{{ $movement->movement_type }}</span>
                                            @else
                                                <span class="badge badge-yellow">{{ $movement->movement_type }}</span>
                                            @endif
                                        </td>
                                        <td class="qty-in">{{ number_format((float) $movement->qty_in, 0, ',', '.') }}</td>
                                        <td class="qty-out">{{ number_format((float) $movement->qty_out, 0, ',', '.') }}</td>
                                        <td class="reference-text">
                                            <strong>{{ $movement->reference_type ?? '-' }}</strong>{{ $movement->reference_id ? ' #' . $movement->reference_id : '' }}
                                        </td>
                                        <td class="note-text">{{ $movement->note ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty">
                        Tidak ada stock movement yang cocok dengan filter.
                    </div>
                @endif
            </div>

            <div class="bottom-bar">
                
            </div>
        </div>
    </div>
@endsection