<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Detail - Back Office ATG POS</title>
    <style>
        :root {
            --bg: #f3f5fa;
            --surface: rgba(255,255,255,0.94);
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --brand: #e86a3a;
            --brand-dark: #c9552a;
            --brand-soft: #fff3eb;
            --navy: #0f172a;
            --green: #166534;
            --green-soft: #eefaf1;
            --blue: #1d4ed8;
            --blue-soft: #eff6ff;
            --violet: #5b4bd1;
            --violet-soft: #f4f3ff;
            --red: #b42318;
            --red-soft: #fff1f1;
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.14);
            --shadow-soft: 0 16px 34px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(232,106,58,0.10), transparent 20%),
                linear-gradient(180deg, #f7f8fc 0%, #eef2f8 100%);
            color: var(--text);
        }

        .page {
            min-height: 100vh;
            padding: 24px;
        }

        .shell {
            max-width: 1480px;
            margin: 0 auto;
            background: rgba(255,255,255,0.56);
            border: 1px solid rgba(255,255,255,0.90);
            border-radius: 34px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 24px 28px 0;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.78);
            border: 1px solid #eceff5;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .brand-logo {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: var(--brand-soft);
            border: 1px solid #f3d7c9;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .brand-logo img {
            width: 22px;
            height: 22px;
            object-fit: contain;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .brand-name {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: #111827;
        }

        .brand-sub {
            font-size: 11px;
            color: #9ca3af;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .mini-info {
            font-size: 13px;
            color: var(--muted);
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.80);
            border: 1px solid #e5e7eb;
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
            background: linear-gradient(135deg, var(--brand) 0%, #f08a57 100%);
        }

        .content {
            padding: 14px 28px 28px;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 22px;
            margin-bottom: 26px;
        }

        .hero-main {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #ffffff 0%, #fff9f5 58%, #fff1ea 100%);
            border: 1px solid #f0e1d8;
            border-radius: 30px;
            padding: 34px;
            min-height: 250px;
        }

        .hero-main::after {
            content: "";
            position: absolute;
            right: -70px;
            top: -70px;
            width: 240px;
            height: 240px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(232,106,58,0.14) 0%, rgba(232,106,58,0.04) 55%, rgba(232,106,58,0) 78%);
            pointer-events: none;
        }

        .hero-kicker {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #f1e3da;
            color: var(--brand-dark);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .hero-title {
            position: relative;
            z-index: 1;
            margin: 0 0 14px;
            font-size: 44px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
            max-width: 720px;
        }

        .hero-subtitle {
            position: relative;
            z-index: 1;
            margin: 0;
            max-width: 620px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .session-card {
            background: rgba(255,255,255,0.84);
            border: 1px solid #eceff5;
            border-radius: 30px;
            padding: 24px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .session-title {
            font-size: 15px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 12px;
        }

        .session-line {
            font-size: 14px;
            line-height: 1.9;
            color: #374151;
        }

        .label {
            color: #6b7280;
            font-weight: 700;
            margin-right: 6px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-open {
            background: var(--green-soft);
            color: var(--green);
        }

        .status-closed {
            background: var(--blue-soft);
            color: var(--blue);
        }

        .status-void {
            background: var(--red-soft);
            color: var(--red);
        }

        .session-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 26px;
        }

        .summary-card {
            position: relative;
            overflow: hidden;
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            padding: 22px;
            box-shadow: var(--shadow-soft);
            min-height: 150px;
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
            font-size: 34px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 10px;
            color: #111827;
        }

        .summary-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
        }

        .section-card {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 30px;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            margin-bottom: 22px;
        }

        .section-head {
            padding: 26px 26px 0;
        }

        .section-title {
            margin: 0 0 8px;
            font-size: 28px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .section-subtitle {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
            max-width: 780px;
        }

        .info-grid {
            padding: 24px 26px 26px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .info-box {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e7edf5;
            border-radius: 22px;
            padding: 18px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .info-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin-bottom: 12px;
        }

        .info-value {
            font-size: 22px;
            font-weight: 800;
            color: #111827;
            line-height: 1.45;
        }

        .note-box {
            margin: 24px 26px 26px;
            padding: 18px;
            border-radius: 18px;
            background: #fff8f4;
            border: 1px solid #f2d9cb;
            color: #9a3412;
            font-size: 14px;
            line-height: 1.8;
            font-weight: 700;
        }

        .table-wrap {
            padding: 24px 26px 26px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1180px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
        }

        th, td {
            text-align: left;
            padding: 15px 14px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        tbody tr:hover {
            background: #fcfcfd;
        }

        .money {
            font-weight: 800;
            color: var(--blue);
        }

        .muted {
            color: #6b7280;
        }

        .empty-box {
            padding: 28px 26px 30px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.8;
        }

        @media (max-width: 1280px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }

            .info-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 780px) {
            .page {
                padding: 14px;
            }

            .topbar,
            .content {
                padding-left: 18px;
                padding-right: 18px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .hero-title {
                font-size: 34px;
            }

            .summary-grid,
            .info-grid {
                grid-template-columns: 1fr;
            }

            .table-wrap {
                padding-left: 18px;
                padding-right: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="shell">
            <div class="topbar">
                <div class="brand">
                    <div class="brand-logo">
                        <img src="{{ asset('images/atg-icon.png') }}" alt="ATG Logo">
                    </div>
                    <div class="brand-text">
                        <div class="brand-name">ATG POS</div>
                        <div class="brand-sub">Shift Detail Workspace</div>
                    </div>
                </div>

                <div class="top-actions">
                    <div class="mini-info">
                        {{ $user->name }} • {{ $user->role->name ?? '-' }}
                    </div>
                    <a href="{{ route('backoffice.shifts.index') }}" class="btn btn-brand">Shift List</a>
                    <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Back Office</a>
                </div>
            </div>

            <div class="content">
                <div class="hero">
                    <div class="hero-main">

                        <h1 class="hero-title">Detail shift kasir dalam satu tampilan yang lebih clean.</h1>

                    </div>

                    <div class="session-card">
                        <div>
                            <div class="session-title">Shift Snapshot</div>
                            <div class="session-line"><span class="label">Kasir:</span>{{ $shift->user->name ?? '-' }}</div>
                            <div class="session-line"><span class="label">Role:</span>{{ $shift->user->role->name ?? '-' }}</div>
                            <div class="session-line"><span class="label">Outlet:</span>{{ $shift->outlet->name ?? '-' }}</div>
                            <div class="session-line">
                                <span class="label">Status:</span>
                                @if($shift->status === 'open')
                                    <span class="status-badge status-open">Open</span>
                                @else
                                    <span class="status-badge status-closed">Closed</span>
                                @endif
                            </div>
                            <div class="session-line"><span class="label">Started At:</span>{{ $shift->started_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                            <div class="session-line"><span class="label">Ended At:</span>{{ $shift->ended_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                        </div>

                        <div class="session-actions">
                            <a href="{{ route('backoffice.transactions.index') }}" class="btn btn-brand">Transactions</a>
                            <a href="{{ route('backoffice.shifts.index') }}" class="btn btn-dark">Back</a>
                        </div>
                    </div>
                </div>

                <div class="summary-grid">
                    <div class="summary-card orange">
                        <div class="summary-label">Opening Cash</div>
                        <div class="summary-value">Rp{{ number_format((float) $metrics['opening_cash'], 0, ',', '.') }}</div>
                        <div class="summary-desc">Nominal awal saat shift dibuka.</div>
                    </div>

                    <div class="summary-card green">
                        <div class="summary-label">Cash Sales</div>
                        <div class="summary-value">Rp{{ number_format((float) $metrics['cash_sales'], 0, ',', '.') }}</div>
                        <div class="summary-desc">Akumulasi transaksi cash completed.</div>
                    </div>

                    <div class="summary-card blue">
                        <div class="summary-label">Expected Cash</div>
                        <div class="summary-value">Rp{{ number_format((float) $metrics['expected_cash'], 0, ',', '.') }}</div>
                        <div class="summary-desc">Opening cash + cash sales shift ini.</div>
                    </div>

                    <div class="summary-card violet">
                        <div class="summary-label">Closing Actual</div>
                        <div class="summary-value">
                            @if($metrics['closing_cash_actual'] !== null)
                                Rp{{ number_format((float) $metrics['closing_cash_actual'], 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </div>
                        <div class="summary-desc">Nominal cash aktual saat end shift.</div>
                    </div>

                    <div class="summary-card {{ ($metrics['difference'] ?? 0) >= 0 ? 'green' : 'orange' }}">
                        <div class="summary-label">Difference</div>
                        <div class="summary-value">
                            @if($metrics['difference'] !== null)
                                Rp{{ number_format((float) $metrics['difference'], 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </div>
                        <div class="summary-desc">Selisih antara expected cash dan closing actual.</div>
                    </div>
                </div>

                <div class="summary-grid" style="margin-top:-8px; margin-bottom:26px;">
                    <div class="summary-card blue">
                        <div class="summary-label">Total Sales</div>
                        <div class="summary-value">Rp{{ number_format((float) $metrics['total_sales'], 0, ',', '.') }}</div>
                        <div class="summary-desc">Semua sales completed di shift ini.</div>
                    </div>

                    <div class="summary-card violet">
                        <div class="summary-label">QRIS Sales</div>
                        <div class="summary-value">Rp{{ number_format((float) $metrics['qris_sales'], 0, ',', '.') }}</div>
                        <div class="summary-desc">Total transaksi QRIS.</div>
                    </div>

                    <div class="summary-card violet">
                        <div class="summary-label">Transfer Sales</div>
                        <div class="summary-value">Rp{{ number_format((float) $metrics['transfer_sales'], 0, ',', '.') }}</div>
                        <div class="summary-desc">Total transaksi transfer.</div>
                    </div>

                    <div class="summary-card green">
                        <div class="summary-label">Completed Transactions</div>
                        <div class="summary-value">{{ $metrics['completed_transactions_count'] }}</div>
                        <div class="summary-desc">Jumlah transaksi selesai dalam shift ini.</div>
                    </div>

                    <div class="summary-card orange">
                        <div class="summary-label">Void Transactions</div>
                        <div class="summary-value">{{ $metrics['void_transactions_count'] }}</div>
                        <div class="summary-desc">Jumlah transaksi void dalam shift ini.</div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-head">
                        <h2 class="section-title">Shift Information</h2>

                    </div>

                    <div class="info-grid">

                            <div class="info-value">{{ $shift->user->name ?? '-' }}</div>
                        </div>


                            <div class="info-value">{{ $shift->user->role->name ?? '-' }}</div>
                        </div>


                            <div class="info-value">{{ $shift->outlet->name ?? '-' }}</div>
                        </div>


                            <div class="info-value">{{ $shift->started_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                        </div>


                            <div class="info-value">{{ $shift->ended_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                        </div>


                            <div class="info-value">
                                @if($shift->status === 'open')
                                    <span class="status-badge status-open">Open</span>
                                @else
                                    <span class="status-badge status-closed">Closed</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($shift->closing_note)
                        <div class="note-box">
                            <strong>Closing Note:</strong><br>
                            {{ $shift->closing_note }}
                        </div>
                    @endif
                </div>

                <div class="section-card">
                    <div class="section-head">
                        <h2 class="section-title">Transactions in This Shift</h2>

                    </div>

                    @if($shift->salesTransactions->isEmpty())
                        <div class="empty-box">
                            Belum ada transaksi di shift ini.
                        </div>
                    @else
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Transaction Number</th>
                                        <th>Member</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Subtotal</th>
                                        <th>Grand Total</th>
                                        <th>Amount Paid</th>
                                        <th>Change</th>
                                        <th>Items</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($shift->salesTransactions->sortByDesc('created_at') as $transaction)
                                        <tr>
                                            <td>{{ $transaction->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                            <td>
                                                    @php
                                                        $rawTransactionNumber = $transaction->transaction_number ?? null;
                                                        $displayTransactionNumber = '-';

                                                        if (! empty($rawTransactionNumber)) {
                                                            $parts = explode('-', $rawTransactionNumber);
                                                            $lastPart = end($parts);

                                                            if (is_numeric($lastPart)) {
                                                                $displayTransactionNumber = 'ATG ' . str_pad((string) ((int) $lastPart), 3, '0', STR_PAD_LEFT);
                                                            } else {
                                                                $displayTransactionNumber = $rawTransactionNumber;
                                                            }
                                                        }
                                                    @endphp
                                                    <strong>{{ $displayTransactionNumber }}</strong>
                                                </td>
                                            <td>{{ $transaction->member->name ?? '-' }}</td>
                                            <td>{{ strtoupper($transaction->payment_method ?? '-') }}</td>
                                            <td>
                                                @if($transaction->status === 'void')
                                                    <span class="status-badge status-void">Void</span>
                                                @elseif($transaction->status === 'completed')
                                                    <span class="status-badge status-open">Completed</span>
                                                @else
                                                    <span class="status-badge status-closed">{{ ucfirst($transaction->status ?? '-') }}</span>
                                                @endif
                                            </td>
                                            <td class="money">Rp{{ number_format((float) $transaction->subtotal, 0, ',', '.') }}</td>
                                            <td class="money">Rp{{ number_format((float) $transaction->grand_total, 0, ',', '.') }}</td>
                                            <td class="money">Rp{{ number_format((float) $transaction->amount_paid, 0, ',', '.') }}</td>
                                            <td class="money">Rp{{ number_format((float) $transaction->change_amount, 0, ',', '.') }}</td>
                                            <td class="muted">
                                                @foreach($transaction->items as $item)
                                                    <div style="margin-bottom:6px;">
                                                        {{ $item->product_name ?? '-' }}
                                                        @if($item->variant_name)
                                                            - {{ $item->variant_name }}
                                                        @endif
                                                        x {{ number_format((float) $item->qty, 0, ',', '.') }}
                                                    </div>
                                                @endforeach
                                            </td>
                                            <td>
                                                <a href="{{ route('backoffice.transactions.show', $transaction->id) }}" class="btn btn-dark">Detail</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="bottom-bar">
                    Shift Detail active: kamu sekarang bisa audit performa kasir, cash movement, closing difference, dan daftar transaksi per shift dalam tampilan yang setara dengan dashboard Back Office utama.
                </div>
            </div>
        </div>
    </div>
</body>
</html>