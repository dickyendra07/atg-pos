<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Sales Summary - ATG POS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #111827;
            margin: 24px;
            background: #ffffff;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 16px;
            margin-bottom: 22px;
        }

        .title {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .subtitle {
            font-size: 14px;
            color: #4b5563;
            line-height: 1.6;
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
            background: #111827;
            color: white;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
        }

        .btn-print {
            background: #166534;
        }

        .info-box,
        .section-box {
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 18px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .summary-card {
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 14px;
        }

        .summary-card .label {
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .summary-card .value {
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 6px;
        }

        .summary-card .desc {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5;
        }

        .section-title {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .mini-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 18px;
        }

        .list-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        .list-row:last-child {
            border-bottom: 0;
        }

        .list-name {
            font-weight: 700;
            color: #111827;
        }

        .list-meta {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }

        .list-value {
            font-weight: 800;
            color: #111827;
            white-space: nowrap;
        }

        .print-note {
            font-size: 12px;
            color: #6b7280;
            margin-top: 16px;
        }

        @media print {
            .actions {
                display: none;
            }

            body {
                margin: 0;
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div>
            <div class="title">Sales Summary Report</div>
            <div class="subtitle">
                Report valid sales untuk kebutuhan monitoring internal, presentasi, dan pengiriman ke client.
            </div>
        </div>

        <div class="actions">
            <button onclick="window.print()" class="btn btn-print">Print</button>
            <a href="{{ route('backoffice.transactions.index', request()->query()) }}" class="btn">Kembali</a>
        </div>
    </div>

    <div class="info-box">
        <strong>User:</strong> {{ $user->name }}<br>
        <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
        <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}<br>
        <strong>Date From:</strong> {{ $filters['date_from'] ?: '-' }}<br>
        <strong>Date To:</strong> {{ $filters['date_to'] ?: '-' }}<br>
        <strong>Payment Method:</strong> {{ $filters['payment_method'] ?: 'ALL' }}<br>
        <strong>Status:</strong> {{ $filters['status'] ?: 'ALL' }}<br>
        <strong>Outlet Filter:</strong> {{ $filters['outlet_id'] ?: 'ALL' }}
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="label">Valid Sales</div>
            <div class="value">Rp{{ number_format($totalSales, 0, ',', '.') }}</div>
            <div class="desc">Sudah exclude transaksi bermasalah.</div>
        </div>

        <div class="summary-card">
            <div class="label">Valid Transactions</div>
            <div class="value">{{ number_format($totalTransactions, 0, ',', '.') }}</div>
            <div class="desc">Jumlah transaksi valid dalam report.</div>
        </div>

        <div class="summary-card">
            <div class="label">Average Order Value</div>
            <div class="value">Rp{{ number_format($averageOrderValue, 0, ',', '.') }}</div>
            <div class="desc">Rata-rata nilai transaksi valid.</div>
        </div>

        <div class="summary-card">
            <div class="label">Items Sold</div>
            <div class="value">{{ number_format($totalItemsSold, 0, ',', '.') }}</div>
            <div class="desc">Total item terjual dari transaksi valid.</div>
        </div>
    </div>

    <div class="summary-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
        <div class="summary-card">
            <div class="label">Total Rows</div>
            <div class="value">{{ number_format($transactions->count(), 0, ',', '.') }}</div>
            <div class="desc">Semua transaksi pada tabel hasil filter.</div>
        </div>

        <div class="summary-card">
            <div class="label">Valid Rows</div>
            <div class="value">{{ number_format($validTransactionsCount, 0, ',', '.') }}</div>
            <div class="desc">Yang dipakai ke summary utama.</div>
        </div>

        <div class="summary-card">
            <div class="label">Problem Rows</div>
            <div class="value">{{ number_format($problemTransactionsCount, 0, ',', '.') }}</div>
            <div class="desc">
                Stock blocked: {{ number_format($blockedTransactionsCount, 0, ',', '.') }} |
                Total nol: {{ number_format($zeroAmountTransactionsCount, 0, ',', '.') }}
            </div>
        </div>
    </div>

    <div class="mini-grid">
        <div class="section-box">
            <div class="section-title">Payment Method Summary</div>

            @if($paymentSummary->count())
                @foreach($paymentSummary as $method => $summary)
                    <div class="list-row">
                        <div>
                            <div class="list-name">{{ $method }}</div>
                            <div class="list-meta">{{ number_format($summary['count'], 0, ',', '.') }} transaksi valid</div>
                        </div>
                        <div class="list-value">Rp{{ number_format($summary['total'], 0, ',', '.') }}</div>
                    </div>
                @endforeach
            @else
                <div class="list-meta">Belum ada data.</div>
            @endif
        </div>

        <div class="section-box">
            <div class="section-title">Top Selling Products</div>

            @if($topProducts->count())
                @foreach($topProducts as $product)
                    <div class="list-row">
                        <div>
                            <div class="list-name">{{ $product['name'] }}</div>
                            <div class="list-meta">Qty terjual: {{ number_format($product['qty'], 0, ',', '.') }}</div>
                        </div>
                        <div class="list-value">Rp{{ number_format($product['sales'], 0, ',', '.') }}</div>
                    </div>
                @endforeach
            @else
                <div class="list-meta">Belum ada data.</div>
            @endif
        </div>
    </div>

    <div class="print-note">
        Generated at {{ now()->format('Y-m-d H:i:s') }} | ATG POS Sales Summary Print View
    </div>
</body>
</html>