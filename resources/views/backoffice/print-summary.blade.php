<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Dashboard Summary - ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #111827;
        }

        .page {
            max-width: 1180px;
            margin: 24px auto;
            background: white;
            padding: 28px;
            box-sizing: border-box;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 24px;
        }

        .title {
            font-size: 30px;
            font-weight: 800;
            margin: 0 0 8px;
        }

        .subtitle {
            margin: 0;
            color: #6b7280;
            line-height: 1.7;
            font-size: 14px;
        }

        .meta {
            text-align: right;
            font-size: 13px;
            line-height: 1.8;
            color: #374151;
            font-weight: 700;
        }

        .actions {
            margin-bottom: 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            border: 0;
            cursor: pointer;
            min-height: 40px;
            padding: 0 14px;
            border-radius: 10px;
            color: white;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-dark {
            background: #111827;
        }

        .btn-blue {
            background: #1d4ed8;
        }

        .section {
            margin-top: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            overflow: hidden;
        }

        .section-head {
            padding: 16px 18px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }

        .section-title {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 800;
        }

        .section-subtitle {
            margin: 0;
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
        }

        .stats-grid {
            padding: 18px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .stat {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px;
            background: #fff;
        }

        .stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 8px;
        }

        .stat-desc {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.6;
        }

        .content-pad {
            padding: 18px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .info-box {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px;
        }

        .info-title {
            margin: 0 0 10px;
            font-size: 15px;
            font-weight: 800;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 8px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-row strong {
            color: #111827;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 720px;
            border-collapse: collapse;
        }

        thead th {
            text-align: left;
            padding: 12px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
        }

        tbody td {
            padding: 12px;
            border-bottom: 1px solid #eef2f7;
            font-size: 13px;
            vertical-align: top;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .strong {
            font-weight: 800;
        }

        @media print {
            body {
                background: white;
            }

            .page {
                margin: 0;
                max-width: none;
                padding: 0;
            }

            .actions {
                display: none;
            }

            .section {
                break-inside: avoid;
            }
        }

        @media (max-width: 900px) {
            .stats-grid,
            .info-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 640px) {
            .topbar,
            .stats-grid,
            .info-grid {
                grid-template-columns: 1fr;
                display: grid;
            }

            .meta {
                text-align: left;
            }
        }
    </style>
</head>
<body>
@php
    $selectedOutletName = 'Semua Outlet';

    if (! empty($filters['outlet_id'])) {
        $selectedOutlet = $outletOptions->firstWhere('id', (int) $filters['outlet_id']);
        $selectedOutletName = $selectedOutlet->name ?? 'Outlet';
    }

    $topProducts = collect($charts['top_products'] ?? []);
    $lowStockFocus = collect($charts['low_stock_focus'] ?? []);
@endphp

<div class="page">
    <div class="actions">
        <button onclick="window.print()" class="btn btn-blue">Print</button>
        <a href="{{ route('backoffice.index', request()->query()) }}" class="btn btn-dark">Kembali ke Dashboard</a>
    </div>

    <div class="topbar">
        <div>
            <h1 class="title">Dashboard Summary Print</h1>
            <p class="subtitle">
                Ringkasan transaksi, inventory, dan top products berdasarkan filter aktif.
            </p>
        </div>

        <div class="meta">
            User: {{ $user->name }}<br>
            Outlet: {{ $selectedOutletName }}<br>
            Periode: {{ $filters['date_from'] }} s/d {{ $filters['date_to'] }}<br>
            Dicetak: {{ now()->format('Y-m-d H:i:s') }}
        </div>
    </div>

    <div class="section">
        <div class="section-head">
            <h2 class="section-title">Transaction Summary</h2>
            <p class="section-subtitle">Ringkasan angka utama transaksi pada periode aktif.</p>
        </div>

        <div class="stats-grid">
            <div class="stat">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value">{{ number_format((int) ($stats['transaction_count'] ?? 0), 0, ',', '.') }}</div>
                <div class="stat-desc">Semua transaksi pada filter aktif.</div>
            </div>

            <div class="stat">
                <div class="stat-label">Total Sales</div>
                <div class="stat-value">Rp {{ number_format((float) ($stats['total_sales'] ?? 0), 0, ',', '.') }}</div>
                <div class="stat-desc">Akumulasi grand total transaksi completed.</div>
            </div>

            <div class="stat">
                <div class="stat-label">Items Sold</div>
                <div class="stat-value">{{ number_format((float) ($stats['items_sold'] ?? 0), 0, ',', '.') }}</div>
                <div class="stat-desc">Total qty item yang terjual.</div>
            </div>

            <div class="stat">
                <div class="stat-label">Average Order</div>
                <div class="stat-value">Rp {{ number_format((float) ($stats['average_order'] ?? 0), 0, ',', '.') }}</div>
                <div class="stat-desc">Rata-rata nilai order.</div>
            </div>
        </div>

        <div class="content-pad">
            <div class="info-grid">
                <div class="info-box">
                    <h3 class="info-title">Status Breakdown</h3>
                    <div class="info-row">
                        <span>Completed</span>
                        <strong>{{ number_format((int) ($stats['completed_transaction_count'] ?? 0), 0, ',', '.') }}</strong>
                    </div>
                    <div class="info-row">
                        <span>Void</span>
                        <strong>{{ number_format((int) ($stats['void_transaction_count'] ?? 0), 0, ',', '.') }}</strong>
                    </div>
                </div>

                <div class="info-box">
                    <h3 class="info-title">Payment Summary</h3>
                    @foreach(($stats['payment_summary'] ?? []) as $method => $row)
                        <div class="info-row">
                            <span>{{ strtoupper($method) }}</span>
                            <strong>Rp {{ number_format((float) ($row['total'] ?? 0), 0, ',', '.') }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-head">
            <h2 class="section-title">Top Products Table</h2>
            <p class="section-subtitle">Product / variant dengan sales tertinggi pada periode aktif.</p>
        </div>

        <div class="content-pad">
            @if($topProducts->count())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Product / Variant</th>
                                <th>Qty Sold</th>
                                <th>Total Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topProducts as $index => $product)
                                <tr>
                                    <td class="strong">#{{ $index + 1 }}</td>
                                    <td class="strong">{{ $product['name'] ?? '-' }}</td>
                                    <td>{{ number_format((float) ($product['qty'] ?? 0), 0, ',', '.') }}</td>
                                    <td class="strong">Rp {{ number_format((float) ($product['sales'] ?? 0), 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div>Tidak ada data top products.</div>
            @endif
        </div>
    </div>

    <div class="section">
        <div class="section-head">
            <h2 class="section-title">Inventory Summary</h2>
            <p class="section-subtitle">Ringkasan inventory pada scope lokasi aktif.</p>
        </div>

        <div class="stats-grid">
            <div class="stat">
                <div class="stat-label">Current Stock Rows</div>
                <div class="stat-value">{{ number_format((int) ($stats['current_stock_rows'] ?? 0), 0, ',', '.') }}</div>
                <div class="stat-desc">Jumlah row stock balance aktif.</div>
            </div>

            <div class="stat">
                <div class="stat-label">Total Qty On Hand</div>
                <div class="stat-value">{{ number_format((float) ($stats['total_qty_on_hand'] ?? 0), 0, ',', '.') }}</div>
                <div class="stat-desc">Total qty stock saat ini.</div>
            </div>

            <div class="stat">
                <div class="stat-label">Low Stock</div>
                <div class="stat-value">{{ number_format((int) ($stats['low_stock_count'] ?? 0), 0, ',', '.') }}</div>
                <div class="stat-desc">Item yang sudah menyentuh minimum stock.</div>
            </div>

            <div class="stat">
                <div class="stat-label">Out of Stock</div>
                <div class="stat-value">{{ number_format((int) ($stats['out_of_stock_count'] ?? 0), 0, ',', '.') }}</div>
                <div class="stat-desc">Item yang sudah habis.</div>
            </div>
        </div>

        <div class="content-pad">
            <div class="info-grid">
                <div class="info-box">
                    <h3 class="info-title">Movement Summary</h3>
                    <div class="info-row">
                        <span>Movement Logs</span>
                        <strong>{{ number_format((int) ($stats['stock_movement_count'] ?? 0), 0, ',', '.') }}</strong>
                    </div>
                    <div class="info-row">
                        <span>Total Qty In</span>
                        <strong>{{ number_format((float) ($stats['total_qty_in'] ?? 0), 0, ',', '.') }}</strong>
                    </div>
                    <div class="info-row">
                        <span>Total Qty Out</span>
                        <strong>{{ number_format((float) ($stats['total_qty_out'] ?? 0), 0, ',', '.') }}</strong>
                    </div>
                </div>

                <div class="info-box">
                    <h3 class="info-title">Low Stock Focus</h3>
                    @if($lowStockFocus->count())
                        @foreach($lowStockFocus->take(8) as $stock)
                            <div class="info-row">
                                <span>{{ $stock->ingredient->name ?? '-' }}</span>
                                <strong>{{ number_format((float) ($stock->qty_on_hand ?? 0), 0, ',', '.') }}</strong>
                            </div>
                        @endforeach
                    @else
                        <div>Tidak ada low stock / out of stock.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>