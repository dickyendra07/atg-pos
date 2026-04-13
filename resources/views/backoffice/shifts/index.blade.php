<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Shifts - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
        }

        .title {
            font-size: 34px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 8px;
        }

        .subtitle {
            font-size: 15px;
            color: #6b7280;
            line-height: 1.7;
            max-width: 760px;
        }

        .top-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            background: #111827;
            color: white;
            padding: 12px 18px;
            border-radius: 12px;
            font-weight: bold;
            display: inline-block;
            border: 0;
            cursor: pointer;
        }

        .btn-green {
            background: #166534;
        }

        .card {
            background: white;
            border-radius: 22px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
            margin-bottom: 22px;
        }

        .filter-grid,
        .summary-grid {
            display: grid;
            gap: 16px;
        }

        .filter-grid {
            grid-template-columns: repeat(5, minmax(0, 1fr));
            align-items: end;
        }

        .summary-grid {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }

        .field label {
            display: block;
            font-size: 12px;
            color: #6b7280;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .field input,
        .field select {
            width: 100%;
            min-height: 46px;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 0 14px;
            font-size: 14px;
            background: #fff;
        }

        .summary-box {
            border-radius: 18px;
            padding: 18px;
            border: 1px solid #e5e7eb;
        }

        .summary-box.soft-orange {
            background: linear-gradient(180deg, #fff8f4 0%, #ffffff 100%);
            border-color: #f5ddd0;
        }

        .summary-box.soft-green {
            background: linear-gradient(180deg, #f2fbf5 0%, #ffffff 100%);
            border-color: #d8f0de;
        }

        .summary-box.soft-blue {
            background: linear-gradient(180deg, #f4f8ff 0%, #ffffff 100%);
            border-color: #dbe7ff;
        }

        .summary-label {
            font-size: 11px;
            font-weight: bold;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .summary-value {
            font-size: 30px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 6px;
        }

        .summary-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.5;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1300px;
        }

        th, td {
            text-align: left;
            padding: 15px 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background: #f9fafb;
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
        }

        .money {
            font-weight: bold;
            color: #1d4ed8;
        }

        .status-badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-open {
            background: #eefaf1;
            color: #166534;
        }

        .status-closed {
            background: #eef4ff;
            color: #1d4ed8;
        }

        .mini-text {
            color: #6b7280;
            font-size: 12px;
            line-height: 1.6;
        }

        .empty-box {
            padding: 32px 20px;
            text-align: center;
            color: #6b7280;
            font-size: 15px;
        }

        .section-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 16px;
            color: #111827;
        }

        @media (max-width: 1100px) {
            .filter-grid,
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 700px) {
            .filter-grid,
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div>
                <div class="title">Back Office - Cashier Shifts</div>
                <div class="subtitle">
                    Pantau shift kasir, opening cash, expected cash, closing cash actual, dan performa transaksi per shift.
                </div>
            </div>

            <div class="top-actions">
                <a href="{{ route('backoffice.transactions.index') }}" class="btn btn-green">Transactions</a>
                <a href="{{ route('backoffice.index') }}" class="btn">Kembali</a>
            </div>
        </div>

        <div class="card">
            <form method="GET" action="{{ route('backoffice.shifts.index') }}">
                <div class="filter-grid">
                    <div class="field">
                        <label>Date From</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    </div>

                    <div class="field">
                        <label>Date To</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </div>

                    <div class="field">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Semua status</option>
                            <option value="open" @selected(($filters['status'] ?? '') === 'open')>Open</option>
                            <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Closed</option>
                        </select>
                    </div>

                    @if(($user->role?->code ?? null) !== 'admin_outlet')
                        <div class="field">
                            <label>Outlet</label>
                            <select name="outlet_id">
                                <option value="">Semua outlet</option>
                                @foreach($outletOptions as $outlet)
                                    <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>
                                        {{ $outlet->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="field">
                            <label>Outlet</label>
                            <input type="text" value="{{ $user->outlet->name ?? '-' }}" disabled>
                        </div>
                    @endif

                    <div class="field">
                        <label>User Keyword</label>
                        <input type="text" name="user_keyword" value="{{ $filters['user_keyword'] ?? '' }}" placeholder="Cari nama kasir">
                    </div>
                </div>

                <div style="display:flex; gap:12px; margin-top:16px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-green">Apply Filter</button>
                    <a href="{{ route('backoffice.shifts.index') }}" class="btn">Reset</a>
                </div>
            </form>
        </div>

        <div class="summary-grid" style="margin-bottom:22px;">
            <div class="summary-box soft-orange">
                <div class="summary-label">Total Shifts</div>
                <div class="summary-value">{{ $summary['total_shifts'] ?? 0 }}</div>
                <div class="summary-desc">Jumlah seluruh shift yang tampil di filter sekarang.</div>
            </div>

            <div class="summary-box soft-green">
                <div class="summary-label">Open Shifts</div>
                <div class="summary-value">{{ $summary['open_shifts'] ?? 0 }}</div>
                <div class="summary-desc">Shift yang masih aktif dan belum ditutup.</div>
            </div>

            <div class="summary-box soft-blue">
                <div class="summary-label">Closed Shifts</div>
                <div class="summary-value">{{ $summary['closed_shifts'] ?? 0 }}</div>
                <div class="summary-desc">Shift yang sudah selesai ditutup.</div>
            </div>

            <div class="summary-box soft-green">
                <div class="summary-label">Total Sales</div>
                <div class="summary-value">Rp{{ number_format((float) ($summary['total_sales'] ?? 0), 0, ',', '.') }}</div>
                <div class="summary-desc">Akumulasi sales dari transaksi completed.</div>
            </div>

            <div class="summary-box soft-blue">
                <div class="summary-label">Expected Cash</div>
                <div class="summary-value">Rp{{ number_format((float) ($summary['total_expected_cash'] ?? 0), 0, ',', '.') }}</div>
                <div class="summary-desc">Opening cash + cash sales dari semua shift terfilter.</div>
            </div>
        </div>

        <div class="card">
            <div class="section-title">Shift List</div>

            @if($shiftRows->isEmpty())
                <div class="empty-box">
                    Belum ada data shift yang cocok dengan filter.
                </div>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Started At</th>
                                <th>Ended At</th>
                                <th>Kasir</th>
                                <th>Role</th>
                                <th>Outlet</th>
                                <th>Status</th>
                                <th>Opening Cash</th>
                                <th>Cash Sales</th>
                                <th>Total Sales</th>
                                <th>Expected Cash</th>
                                <th>Closing Actual</th>
                                <th>Difference</th>
                                <th>Transactions</th>
                                <th>Void</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($shiftRows as $row)
                                @php
                                    $shift = $row['model'];
                                    $metrics = $row['metrics'];
                                @endphp
                                <tr>
                                    <td>
                                        {{ $shift->started_at?->format('Y-m-d H:i:s') ?? '-' }}
                                    </td>
                                    <td>
                                        {{ $shift->ended_at?->format('Y-m-d H:i:s') ?? '-' }}
                                    </td>
                                    <td>
                                        <strong>{{ $shift->user->name ?? '-' }}</strong>
                                    </td>
                                    <td>
                                        {{ $shift->user->role->name ?? '-' }}
                                    </td>
                                    <td>
                                        {{ $shift->outlet->name ?? '-' }}
                                    </td>
                                    <td>
                                        @if($shift->status === 'open')
                                            <span class="status-badge status-open">Open</span>
                                        @else
                                            <span class="status-badge status-closed">Closed</span>
                                        @endif
                                    </td>
                                    <td class="money">
                                        Rp{{ number_format((float) $metrics['opening_cash'], 0, ',', '.') }}
                                    </td>
                                    <td class="money">
                                        Rp{{ number_format((float) $metrics['cash_sales'], 0, ',', '.') }}
                                    </td>
                                    <td class="money">
                                        Rp{{ number_format((float) $metrics['total_sales'], 0, ',', '.') }}
                                    </td>
                                    <td class="money">
                                        Rp{{ number_format((float) $metrics['expected_cash'], 0, ',', '.') }}
                                    </td>
                                    <td class="money">
                                        @if($metrics['closing_cash_actual'] !== null)
                                            Rp{{ number_format((float) $metrics['closing_cash_actual'], 0, ',', '.') }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($metrics['difference'] !== null)
                                            <strong style="color: {{ $metrics['difference'] >= 0 ? '#166534' : '#b42318' }}">
                                                Rp{{ number_format((float) $metrics['difference'], 0, ',', '.') }}
                                            </strong>
                                        @else
                                            <span class="mini-text">Belum ditutup</span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $metrics['completed_transactions_count'] }}</strong>
                                    </td>
                                    <td>
                                        <strong>{{ $metrics['void_transactions_count'] }}</strong>
                                    </td>
                                    <td>
                                        <a href="{{ route('backoffice.shifts.show', $shift->id) }}" class="btn">Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</body>
</html>