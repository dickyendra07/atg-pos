<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Stock - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
        }

        .wrap {
            max-width: 1380px;
            margin: 36px auto;
            padding: 0 20px 40px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .title {
            font-size: 30px;
            font-weight: 800;
            color: #111827;
        }

        .subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-top: 6px;
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
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
        }

        .btn-primary { background: #e86a3a; }
        .btn-green { background: #166534; }
        .btn-info { background: #1d4ed8; }
        .btn-dark { background: #111827; }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .info, .success {
            margin-bottom: 18px;
            border-radius: 14px;
            padding: 14px 16px;
        }

        .info {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            line-height: 1.75;
        }

        .success {
            background: #e8fff1;
            color: #17663a;
            border: 1px solid #ccefd8;
            font-weight: 700;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1100px;
            background: white;
        }

        th, td {
            text-align: left;
            padding: 15px 14px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
            font-size: 14px;
        }

        th {
            background: #f9fafb;
            font-size: 12px;
            color: #6b7280;
            font-weight: 700;
            text-transform: uppercase;
        }

        .qty {
            font-weight: 800;
            color: #1d4ed8;
        }

        .empty {
            padding: 18px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 14px;
            margin-top: 12px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }

        .note {
            margin-top: 20px;
            background: #eef2ff;
            color: #3730a3;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #dbe3ff;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div>
                <div class="title">Warehouse Stock - {{ $warehouse->name }}</div>

            </div>

            <div class="actions">
                <a href="{{ route('backoffice.transfers.create', ['from_location_type' => 'warehouse', 'from_location_id' => $warehouse->id]) }}" class="btn btn-green">Transfer</a>
                <a href="{{ route('backoffice.warehouses.stock.create', $warehouse) }}" class="btn btn-primary">Stock In Warehouse</a>
                <a href="{{ route('backoffice.warehouses.movements.index', $warehouse) }}" class="btn btn-info">Lihat Riwayat</a>
                <a href="{{ route('backoffice.warehouses.index') }}" class="btn btn-dark">Kembali</a>
            </div>
        </div>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="info">
                <strong>Warehouse:</strong> {{ $warehouse->name }}<br>
                <strong>Code:</strong> {{ $warehouse->code }}<br>
                <strong>Address:</strong> {{ $warehouse->address ?: '-' }}<br>
                <strong>Phone:</strong> {{ $warehouse->phone ?: '-' }}
            </div>

            @if($stockBalances->count())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Ingredient</th>
                                <th>Unit</th>
                                <th>Minimum Stock</th>
                                <th>Qty On Hand</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stockBalances as $stock)
                                <tr>
                                    <td>{{ $stock->ingredient->category->name ?? '-' }}</td>
                                    <td>{{ $stock->ingredient->name ?? '-' }}</td>
                                    <td>{{ $stock->ingredient->unit ?? '-' }}</td>
                                    <td>{{ number_format((float) ($stock->ingredient->minimum_stock ?? 0), 0, ',', '.') }}</td>
                                    <td class="qty">{{ number_format((float) $stock->qty_on_hand, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">Belum ada stok ingredient di warehouse ini.</div>
            @endif
        </div>
    </div>
</body>
</html>