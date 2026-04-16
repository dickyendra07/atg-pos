<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Movements - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 1450px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .top-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .title {
            font-size: 28px;
            font-weight: bold;
        }

        .btn {
            text-decoration: none;
            background: #111827;
            color: white;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: bold;
            display: inline-block;
            border: 0;
            cursor: pointer;
        }

        .btn-success {
            background: #166534;
        }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-primary {
            background: #1d4ed8;
        }

        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
        }

        .info {
            margin-bottom: 18px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
        }

        .filter-box {
            margin-bottom: 20px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr auto auto auto;
            gap: 12px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .field select,
        .field input {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px;
            font-size: 14px;
            background: white;
        }

        .table-wrap {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1450px;
        }

        th, td {
            text-align: left;
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        th {
            background: #f9fafb;
            font-size: 13px;
            color: #555;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-green {
            background: #e8fff1;
            color: #17663a;
        }

        .badge-yellow {
            background: #fff7ed;
            color: #9a3412;
        }

        .badge-blue {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .qty-in {
            font-weight: bold;
            color: #166534;
        }

        .qty-out {
            font-weight: bold;
            color: #b91c1c;
        }

        .note {
            margin-top: 20px;
            background: #e8fff1;
            color: #17663a;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
        }

        .empty {
            padding: 16px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 12px;
            margin-top: 16px;
            font-weight: bold;
        }

        @media (max-width: 1200px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div class="title">Back Office - Stock Movements</div>

        <div class="top-actions">
            <a
                href="{{ route('backoffice.stock-movements.export.csv', request()->query()) }}"
                class="btn btn-primary"
            >
                Export CSV
            </a>
            <a href="{{ route('backoffice.index') }}" class="btn">Kembali</a>
        </div>
    </div>

    <div class="card">
        <div class="info">
            <strong>User:</strong> {{ $user->name }}<br>
            <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
            <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}
        </div>

        <form method="GET" action="{{ route('backoffice.stock-movements.index') }}" class="filter-box">
            <div class="filter-grid">
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
                        <option value="transfer_cancel_return" @selected(($filters['movement_type'] ?? '') === 'transfer_cancel_return')>transfer_cancel_return</option>
                        <option value="transfer_cancel_out" @selected(($filters['movement_type'] ?? '') === 'transfer_cancel_out')>transfer_cancel_out</option>
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

                <button type="submit" class="btn btn-success">Apply Filter</button>
                <a href="{{ route('backoffice.stock-movements.index') }}" class="btn btn-secondary">Reset</a>
                <a href="{{ route('backoffice.stock-movements.export.csv', request()->query()) }}" class="btn btn-primary">Export CSV</a>
            </div>
        </form>

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
                            <td>{{ $movement->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td>{{ $movement->ingredient->name ?? '-' }}</td>
                            <td>{{ ucfirst($movement->location_type ?? '-') }}</td>
                            <td>{{ $movement->location_id }}</td>
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
                            <td>{{ $movement->reference_type }}{{ $movement->reference_id ? ' #' . $movement->reference_id : '' }}</td>
                            <td>{{ $movement->note }}</td>
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

        <div class="note">
            Stock movements sekarang sudah bisa di-export ke CSV sesuai filter aktif, jadi lebih gampang dipakai untuk audit dan pengecekan histori stok.
        </div>
    </div>
</div>
</body>
</html>