<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Shift - {{ $shift->id }}</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f6fa;
            color: #111827;
        }

        .page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        .print-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .btn {
            min-height: 42px;
            padding: 0 16px;
            border-radius: 12px;
            border: 0;
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-dark {
            background: #111827;
        }

        .btn-green {
            background: #166534;
        }

        .sheet {
            background: white;
            border-radius: 22px;
            border: 1px solid #e5e7eb;
            padding: 24px;
        }

        .title {
            margin: 0 0 8px;
            font-size: 32px;
            font-weight: 800;
            color: #111827;
        }

        .subtitle {
            margin: 0 0 20px;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.7;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .meta-card {
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 14px;
            background: #fafafa;
        }

        .meta-label {
            font-size: 11px;
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .meta-value {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            line-height: 1.4;
        }

        .section {
            margin-top: 24px;
        }

        .section-title {
            margin: 0 0 12px;
            font-size: 22px;
            font-weight: 800;
            color: #111827;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            overflow: hidden;
        }

        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #eef2f7;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            color: #6b7280;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .items-list div {
            margin-bottom: 4px;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .status-chip.completed {
            background: #e8fff1;
            color: #166534;
            border: 1px solid #ccefd8;
        }

        .status-chip.void {
            background: #fff1f1;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        @media print {
            body {
                background: white;
            }

            .print-actions {
                display: none;
            }

            .page {
                max-width: none;
                padding: 0;
            }

            .sheet {
                border: 0;
                border-radius: 0;
                padding: 0;
            }
        }

        @media (max-width: 900px) {
            .meta-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="print-actions">
            <button type="button" class="btn btn-green" onclick="window.print()">Print Sekarang</button>
            <a href="{{ route('cashier.index') }}" class="btn btn-dark">Kembali ke Cashier</a>
        </div>

        <div class="sheet">
            <h1 class="title">Shift Print Summary</h1>
            <p class="subtitle">
                Rekap semua transaksi dalam shift ini, termasuk transaksi completed dan void.
            </p>

            <div class="meta-grid">
                <div class="meta-card">
                    <div class="meta-label">Cashier</div>
                    <div class="meta-value">{{ $shift->user->name ?? '-' }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Outlet</div>
                    <div class="meta-value">{{ $shift->outlet->name ?? '-' }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Started At</div>
                    <div class="meta-value">{{ $shift->started_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Ended At</div>
                    <div class="meta-value">{{ $shift->ended_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Opening Cash</div>
                    <div class="meta-value">Rp {{ number_format((float) $shift->opening_cash, 0, ',', '.') }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Expected Cash</div>
                    <div class="meta-value">Rp {{ number_format((float) ($summary['expected_cash'] ?? 0), 0, ',', '.') }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Closing Cash Actual</div>
                    <div class="meta-value">
                        Rp {{ number_format((float) ($shift->closing_cash_actual ?? 0), 0, ',', '.') }}
                    </div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Closing Note</div>
                    <div class="meta-value">{{ $shift->closing_note ?: '-' }}</div>
                </div>
            </div>

            <div class="meta-grid">
                <div class="meta-card">
                    <div class="meta-label">Completed Transactions</div>
                    <div class="meta-value">{{ (int) ($summary['total_transactions'] ?? 0) }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Void Transactions</div>
                    <div class="meta-value">{{ (int) ($summary['void_transactions'] ?? 0) }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Total Sales</div>
                    <div class="meta-value">Rp {{ number_format((float) ($summary['total_sales'] ?? 0), 0, ',', '.') }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Cash Sales</div>
                    <div class="meta-value">Rp {{ number_format((float) ($summary['cash_sales'] ?? 0), 0, ',', '.') }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">QRIS Sales</div>
                    <div class="meta-value">Rp {{ number_format((float) ($summary['qris_sales'] ?? 0), 0, ',', '.') }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Transfer Sales</div>
                    <div class="meta-value">Rp {{ number_format((float) ($summary['transfer_sales'] ?? 0), 0, ',', '.') }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Debit Sales</div>
                    <div class="meta-value">Rp {{ number_format((float) ($summary['debit_sales'] ?? 0), 0, ',', '.') }}</div>
                </div>

                <div class="meta-card">
                    <div class="meta-label">Credit Sales</div>
                    <div class="meta-value">Rp {{ number_format((float) ($summary['credit_sales'] ?? 0), 0, ',', '.') }}</div>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title">Daftar Transaksi dalam Shift</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>No Transaksi</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Total</th>
                            <th>Items</th>
                            <th>Void Info</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                <td>{{ $transaction->transaction_number ?? '-' }}</td>
                                <td>
                                    @if(strtolower((string) $transaction->status) === 'void')
                                        <span class="status-chip void">Void</span>
                                    @else
                                        <span class="status-chip completed">{{ ucfirst((string) ($transaction->status ?? '-')) }}</span>
                                    @endif
                                </td>
                                <td>{{ strtoupper((string) ($transaction->payment_method ?? '-')) }}</td>
                                <td>Rp {{ number_format((float) ($transaction->grand_total ?? 0), 0, ',', '.') }}</td>
                                <td class="items-list">
                                    @forelse($transaction->items as $item)
                                        <div>
                                            {{ $item->product_name ?? '-' }}
                                            @if($item->variant_name)
                                                - {{ $item->variant_name }}
                                            @endif
                                            x {{ number_format((float) $item->qty, 0, ',', '.') }}
                                        </div>
                                    @empty
                                        <div>-</div>
                                    @endforelse
                                </td>
                                <td>
                                    @if(strtolower((string) $transaction->status) === 'void')
                                        <div><strong>At:</strong> {{ $transaction->void_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                                        <div><strong>Reason:</strong> {{ $transaction->void_reason ?? '-' }}</div>
                                        <div><strong>By:</strong> {{ $transaction->voidBy->name ?? '-' }}</div>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">Belum ada transaksi dalam shift ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>