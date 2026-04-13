<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Transfers - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
        }

        .wrap {
            max-width: 1450px;
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
            background: #111827;
        }

        .btn-filter {
            background: #166534;
        }

        .btn-reset {
            background: #6b7280;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .info, .success, .filter-box {
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

        .filter-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }

        .filter-title {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto auto;
            gap: 12px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #4b5563;
        }

        .field select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 12px 13px;
            font-size: 14px;
            min-height: 44px;
            background: white;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1500px;
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

        .number {
            font-weight: 800;
            color: #3730a3;
        }

        .qty {
            font-weight: 800;
            color: #1d4ed8;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .badge-completed {
            background: #e8fff1;
            color: #17663a;
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

        @media (max-width: 980px) {
            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 640px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title">Back Office - Warehouse Transfers</div>
            <a href="{{ route('backoffice.index') }}" class="btn">Kembali</a>
        </div>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="info">
                <strong>User:</strong> {{ $user->name }}<br>
                <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}
            </div>

            <form method="GET" action="{{ route('backoffice.warehouse-transfers.index') }}" class="filter-box">
                <div class="filter-title">Filter Transfer</div>

                <div class="filter-grid">
                    <div class="field">
                        <label>Warehouse</label>
                        <select name="warehouse_id">
                            <option value="">Semua warehouse</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected(($filters['warehouse_id'] ?? '') == $warehouse->id)>
                                    {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>Outlet Tujuan</label>
                        <select name="outlet_id">
                            <option value="">Semua outlet</option>
                            @foreach($outlets as $outlet)
                                <option value="{{ $outlet->id }}" @selected(($filters['outlet_id'] ?? '') == $outlet->id)>
                                    {{ $outlet->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn btn-filter">Apply Filter</button>
                    <a href="{{ route('backoffice.warehouse-transfers.index') }}" class="btn btn-reset">Reset</a>
                </div>
            </form>

            @if($transfers->count())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Transfer No</th>
                                <th>Date</th>
                                <th>Warehouse</th>
                                <th>Outlet Tujuan</th>
                                <th>Category</th>
                                <th>Ingredient</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th>Dikirim Oleh</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transfers as $transfer)
                                <tr>
                                    <td class="number">{{ $transfer->transfer_number ?? '-' }}</td>
                                    <td>{{ $transfer->created_at?->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ $transfer->warehouse->name ?? '-' }}</td>
                                    <td>{{ $transfer->outlet->name ?? '-' }}</td>
                                    <td>{{ $transfer->ingredient->category->name ?? '-' }}</td>
                                    <td>{{ $transfer->ingredient->name ?? '-' }}</td>
                                    <td class="qty">{{ number_format((float) $transfer->qty, 0, ',', '.') }}</td>
                                    <td>
                                        <span class="badge badge-completed">{{ strtoupper($transfer->status ?? '-') }}</span>
                                    </td>
                                    <td>{{ $transfer->transferredBy->name ?? '-' }}</td>
                                    <td>{{ $transfer->note ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">Belum ada riwayat transfer gudang ke outlet.</div>
            @endif

            <div class="note">
                Warehouse 2B aktif: transfer gudang ke outlet sekarang sudah lebih rapi dengan nomor transfer dan filter list.
            </div>
        </div>
    </div>
</body>
</html>