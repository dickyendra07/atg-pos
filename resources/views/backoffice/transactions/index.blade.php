@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Transactions - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .transactions-shell {
            display: grid;
            gap: 22px;
        }

        .transactions-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .transactions-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .transactions-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #f1e3da;
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .transactions-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .transactions-subtitle {
            margin: 0;
            max-width: 860px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .transactions-actions {
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
            background: linear-gradient(135deg, #ffffff 0%, #fff9f5 58%, #fff1ea 100%);
            border: 1px solid #f0e1d8;
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
            background: radial-gradient(circle, rgba(232,106,58,0.14) 0%, rgba(232,106,58,0.04) 65%, rgba(232,106,58,0) 80%);
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
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .hero-heading {
            position: relative;
            z-index: 1;
            margin: 0 0 10px;
            font-size: 34px;
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.03em;
            color: #111827;
        }

        .hero-text {
            position: relative;
            z-index: 1;
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
            max-width: 760px;
        }

        .info-box {
            margin: 20px 24px 0;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 16px 18px;
            font-size: 14px;
            line-height: 1.8;
            color: #374151;
        }

        .stats-grid {
            padding: 20px 24px 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .stats-grid-secondary {
            padding: 16px 24px 0;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .stat-card {
            border-radius: 22px;
            padding: 20px;
            border: 1px solid #e8edf4;
            background: rgba(255,255,255,0.92);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            min-height: 140px;
        }

        .stat-card.orange {
            background: linear-gradient(180deg, #fff9f6 0%, #ffffff 100%);
            border-color: #f4ddd0;
        }

        .stat-card.green {
            background: linear-gradient(180deg, #f5fcf7 0%, #ffffff 100%);
            border-color: #d8f0de;
        }

        .stat-card.blue {
            background: linear-gradient(180deg, #f7faff 0%, #ffffff 100%);
            border-color: #dbe7ff;
        }

        .stat-card.violet {
            background: linear-gradient(180deg, #f8f7ff 0%, #ffffff 100%);
            border-color: #e3deff;
        }

        .stat-card.red {
            background: linear-gradient(180deg, #fff8f8 0%, #ffffff 100%);
            border-color: #f6d4d1;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 12px;
            word-break: break-word;
        }

        .orange .stat-value { color: #c9552a; }
        .green .stat-value { color: #166534; }
        .blue .stat-value { color: #1d4ed8; }
        .violet .stat-value { color: #5b4bd1; }
        .red .stat-value { color: #b42318; }

        .stat-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
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
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
            max-width: 820px;
        }

        .filter-grid {
            padding: 24px 22px 22px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr auto;
            gap: 14px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
        }

        .field input,
        .field select {
            width: 100%;
            min-height: 48px;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
            padding: 0 14px;
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
        }

        .field input:focus,
        .field select:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .button-stack {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .panel-grid {
            margin: 20px 24px 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .mini-list {
            padding: 24px 22px 22px;
        }

        .mini-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            padding: 16px;
            border: 1px solid #e8edf4;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            margin-bottom: 12px;
        }

        .mini-item:last-child {
            margin-bottom: 0;
        }

        .mini-title {
            font-size: 15px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 4px;
        }

        .mini-sub {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
        }

        .mini-value {
            font-size: 16px;
            font-weight: 800;
            color: #1d4ed8;
            white-space: nowrap;
        }

        .table-wrap {
            padding: 24px 22px 22px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1320px;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid #e8edf4;
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
            color: #1d4ed8;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-ok {
            background: #e8fff1;
            color: #166534;
        }

        .status-problem {
            background: #fff1f1;
            color: #b42318;
        }

        .empty-box {
            padding: 28px 22px 30px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.8;
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
        }

        @media (max-width: 1320px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .stats-grid-secondary,
            .panel-grid {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 780px) {
            .transactions-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .transactions-title {
                font-size: 32px;
            }

            .stats-grid,
            .stats-grid-secondary,
            .panel-grid,
            .filter-grid {
                grid-template-columns: 1fr;
            }

            .hero-heading {
                font-size: 28px;
            }

            .hero-card,
            .info-box,
            .section-card,
            .panel-grid,
            .bottom-bar {
                margin-left: 18px;
                margin-right: 18px;
            }
        }

        .table-wrap table th,
        .table-wrap table td {
            text-align: center !important;
            vertical-align: middle !important;
        }

        .table-wrap table td > strong,
        .table-wrap table td > div {
            text-align: center !important;
        }

        .table-wrap .money {
            text-align: center !important;
        }

        .table-wrap table td:last-child > div {
            justify-content: center !important;
        }

    </style>

    <div class="transactions-shell">
        <div class="transactions-topbar">
            <div class="transactions-title-block">
                <div class="transactions-kicker">Transactions Workspace</div>
                <h1 class="transactions-title">Back Office - Transactions</h1>
                <p class="transactions-subtitle">
                    Monitor transaksi cashier dengan tampilan yang lebih clean dan valid untuk audit, operasional, dan presentasi client, sekaligus tetap konsisten dengan sidebar back office.
                </p>
            </div>

            <div class="transactions-actions">
                <div style="font-size:13px; color:#6b7280; padding:10px 14px; border-radius:999px; background:rgba(255,255,255,0.80); border:1px solid #e5e7eb;">
                    {{ $user->name }} • {{ $user->role->name ?? '-' }}
                </div>
                <a href="{{ route('backoffice.transactions.export.csv', request()->query()) }}" class="btn btn-brand">Export CSV</a>
                <a href="{{ route('backoffice.transactions.print', request()->query()) }}" class="btn btn-green" target="_blank">Print Summary</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Dashboard</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-error">
                {{ session('error') }}
            </div>
        @endif

        <div class="card">
            <div class="hero-card">
                <div class="hero-kicker">Sales Summary</div>
                <h2 class="hero-heading">Monitor transaksi cashier dengan tampilan yang lebih clean dan valid.</h2>
                <p class="hero-text">
                    Dashboard ringkas penjualan yang sudah dibersihkan dari transaksi bermasalah agar summary lebih valid untuk monitoring operasional, audit, dan presentasi ke client.
                </p>
            </div>

            <div class="info-box">
                <strong>User:</strong> {{ $user->name }}<br>
                <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}
            </div>

            <div class="stats-grid">
                <div class="stat-card orange">
                    <div class="stat-label">Valid Sales</div>
                    <div class="stat-value">Rp{{ number_format((float) $totalSales, 0, ',', '.') }}</div>
                    <div class="stat-desc">Hanya dari transaksi valid, bukan stock_blocked dan bukan nominal nol.</div>
                </div>

                <div class="stat-card blue">
                    <div class="stat-label">Valid Transactions</div>
                    <div class="stat-value">{{ $totalTransactions }}</div>
                    <div class="stat-desc">Jumlah transaksi yang masuk hitungan report utama.</div>
                </div>

                <div class="stat-card green">
                    <div class="stat-label">Average Order Value</div>
                    <div class="stat-value">Rp{{ number_format((float) $averageOrderValue, 0, ',', '.') }}</div>
                    <div class="stat-desc">Rata-rata nilai transaksi valid untuk melihat kualitas basket penjualan.</div>
                </div>

                <div class="stat-card violet">
                    <div class="stat-label">Items Sold</div>
                    <div class="stat-value">{{ number_format((float) $totalItemsSold, 0, ',', '.') }}</div>
                    <div class="stat-desc">Total item terjual dari transaksi valid yang lolos filter report.</div>
                </div>
            </div>

            <div class="stats-grid-secondary">
                <div class="stat-card">
                    <div class="stat-label">Total Rows</div>
                    <div class="stat-value">{{ $transactions->count() }}</div>
                    <div class="stat-desc">Total transaksi yang tampil di tabel berdasarkan filter aktif.</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Valid Rows</div>
                    <div class="stat-value">{{ $validTransactionsCount }}</div>
                    <div class="stat-desc">Jumlah transaksi yang dipakai untuk sales summary utama.</div>
                </div>

                <div class="stat-card red">
                    <div class="stat-label">Problem Rows</div>
                    <div class="stat-value">{{ $problemTransactionsCount }}</div>
                    <div class="stat-desc">Stock blocked: {{ $blockedTransactionsCount }} | Total nol: {{ $zeroAmountTransactionsCount }}</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">Filter Sales Summary</h2>
                    <p class="section-subtitle">
                        Filter transaksi berdasarkan periode, payment method, status, dan outlet untuk mendapatkan summary yang lebih fokus.
                    </p>
                </div>

                <form method="GET" action="{{ route('backoffice.transactions.index') }}" class="filter-grid">
                    <div class="field">
                        <label for="date_from">Date From</label>
                        <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    </div>

                    <div class="field">
                        <label for="date_to">Date To</label>
                        <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </div>

                    <div class="field">
                        <label for="payment_method">Payment Method</label>
                        <select name="payment_method" id="payment_method">
                            <option value="">Semua payment method</option>
                            <option value="cash" @selected(($filters['payment_method'] ?? '') === 'cash')>Cash</option>
                            <option value="qris" @selected(($filters['payment_method'] ?? '') === 'qris')>QRIS</option>
                            <option value="transfer" @selected(($filters['payment_method'] ?? '') === 'transfer')>Transfer</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">Semua status</option>
                            <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed</option>
                            <option value="void" @selected(($filters['status'] ?? '') === 'void')>Void</option>
                            <option value="stock_blocked" @selected(($filters['status'] ?? '') === 'stock_blocked')>Stock Blocked</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="outlet_id">Outlet</label>
                        <select name="outlet_id" id="outlet_id">
                            <option value="">Semua outlet</option>
                            @foreach($outletOptions as $outlet)
                                <option value="{{ $outlet->id }}" @selected((string) ($filters['outlet_id'] ?? '') === (string) $outlet->id)>
                                    {{ $outlet->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="button-stack">
                        <button type="submit" class="btn btn-green">Apply Filter</button>
                        <a href="{{ route('backoffice.transactions.index') }}" class="btn btn-dark">Reset</a>
                    </div>
                </form>
            </div>

            <div class="panel-grid">
                <div class="section-card" style="margin:0;">
                    <div class="section-head">
                        <h2 class="section-title">Payment Method Summary</h2>
                        <p class="section-subtitle">
                            Breakdown transaksi valid berdasarkan metode pembayaran.
                        </p>
                    </div>

                    @if($paymentSummary->isEmpty())
                        <div class="empty-box">
                            Belum ada payment summary untuk filter saat ini.
                        </div>
                    @else
                        <div class="mini-list">
                            @foreach($paymentSummary as $paymentMethod => $paymentData)
                                <div class="mini-item">
                                    <div>
                                        <div class="mini-title">{{ $paymentMethod }}</div>
                                        <div class="mini-sub">{{ $paymentData['count'] }} transaksi valid</div>
                                    </div>
                                    <div class="mini-value">Rp{{ number_format((float) $paymentData['total'], 0, ',', '.') }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="section-card" style="margin:0;">
                    <div class="section-head">
                        <h2 class="section-title">Top Selling Products</h2>
                        <p class="section-subtitle">
                            Produk dengan quantity terjual tertinggi dari transaksi valid.
                        </p>
                    </div>

                    @if($topProducts->isEmpty())
                        <div class="empty-box">
                            Belum ada top selling product untuk filter saat ini.
                        </div>
                    @else
                        <div class="mini-list">
                            @foreach($topProducts as $product)
                                <div class="mini-item">
                                    <div>
                                        <div class="mini-title">{{ $product['name'] }}</div>
                                        <div class="mini-sub">Qty terjual: {{ number_format((float) $product['qty'], 0, ',', '.') }}</div>
                                    </div>
                                    <div class="mini-value">Rp{{ number_format((float) $product['sales'], 0, ',', '.') }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">Transactions Table</h2>
                    <p class="section-subtitle">
                        Seluruh transaksi berdasarkan filter aktif, termasuk transaksi valid dan problem rows untuk kebutuhan audit.
                    </p>
                </div>

                @if($transactions->isEmpty())
                    <div class="empty-box">
                        Belum ada transaksi yang cocok dengan filter saat ini.
                    </div>
                @else
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Outlet</th>
                                    <th>Date</th>
                                    <th>No</th>
                                    <th>Grand Total</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $transaction)
                                    @php
                                        $statusText = strtolower((string) ($transaction->status ?? '-'));
                                        $isProblem = in_array($statusText, ['stock_blocked', 'void'], true)
                                            || (float) ($transaction->grand_total ?? 0) <= 0;

                                        $paymentMethod = strtoupper(trim((string) ($transaction->payment_method ?? 'UNSET')));
                                        if ($paymentMethod === '' || $paymentMethod === '-') {
                                            $paymentMethod = 'UNSET';
                                        }
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $transaction->outlet->name ?? '-' }}</strong>
                                        </td>
                                        <td>
                                            {{ $transaction->created_at?->format('d M Y') ?? '-' }}
                                            <div style="font-size:12px; color:#6b7280; margin-top:4px;">
                                                {{ $transaction->created_at?->format('H:i') ?? '-' }}
                                            </div>
                                        </td>
                                        <td>
                                            <strong>{{ $transaction->transaction_number ?? '-' }}</strong>
                                        </td>
                                        <td class="money">
                                            Rp {{ number_format((float) $transaction->grand_total, 0, ',', '.') }}
                                        </td>
                                        <td>
                                            {{ $paymentMethod }}
                                        </td>
                                        <td>
                                            @if($isProblem)
                                                <span class="status-badge status-problem">{{ ucfirst($transaction->status ?? '-') }}</span>
                                            @else
                                                <span class="status-badge status-ok">{{ ucfirst($transaction->status ?? '-') }}</span>
                                            @endif

                                            @if(strtolower((string) ($transaction->status ?? '')) === 'void')
                                                <div style="font-size:12px; color:#991b1b; margin-top:6px;">
                                                    Void: {{ $transaction->void_reason ?? '-' }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                                <a href="{{ route('backoffice.transactions.show', $transaction->id) }}" class="btn btn-dark">Detail</a>
                                                <a href="{{ route('backoffice.transactions.receipt', $transaction->id) }}" class="btn btn-brand" target="_blank">Receipt</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
@endsection