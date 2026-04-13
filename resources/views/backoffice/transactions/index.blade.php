<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Back Office ATG POS</title>
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

        .btn-green {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
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
            max-width: 640px;
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

        .session-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.6;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 18px;
        }

        .stats-grid-secondary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 26px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            padding: 22px;
            box-shadow: var(--shadow-soft);
            min-height: 160px;
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
            font-size: 44px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 12px;
        }

        .orange .stat-value { color: var(--brand-dark); }
        .green .stat-value { color: var(--green); }
        .blue .stat-value { color: var(--blue); }
        .violet .stat-value { color: var(--violet); }
        .red .stat-value { color: var(--red); }

        .stat-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
            max-width: 240px;
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
            max-width: 820px;
        }

        .filter-grid {
            padding: 24px 26px 26px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr 1fr auto auto;
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
        }

        .field input:focus,
        .field select:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .button-stack {
            display: flex;
            gap: 10px;
            align-items: end;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            margin-bottom: 22px;
        }

        .mini-list {
            padding: 24px 26px 26px;
        }

        .mini-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            padding: 16px 16px;
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
            color: var(--blue);
            white-space: nowrap;
        }

        .table-wrap {
            padding: 24px 26px 26px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1320px;
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

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-ok {
            background: var(--green-soft);
            color: var(--green);
        }

        .status-problem {
            background: var(--red-soft);
            color: var(--red);
        }

        .empty-box {
            padding: 28px 26px 30px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.8;
        }

        .bottom-bar {
            margin-top: 22px;
            padding: 15px 16px;
            border-radius: 18px;
            background: #eef2ff;
            color: #3730a3;
            border: 1px solid #dbe3ff;
            font-weight: 700;
            font-size: 14px;
        }

        @media (max-width: 1320px) {
            .hero {
                grid-template-columns: 1fr;
            }

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

            .stats-grid,
            .stats-grid-secondary,
            .panel-grid,
            .filter-grid {
                grid-template-columns: 1fr;
            }

            .table-wrap,
            .mini-list {
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
                        <div class="brand-sub">Transactions Workspace</div>
                    </div>
                </div>

                <div class="top-actions">
                    <div class="mini-info">
                        {{ $user->name }} • {{ $user->role->name ?? '-' }}
                    </div>
                    <a href="{{ route('backoffice.transactions.export.csv', request()->query()) }}" class="btn btn-brand">Export CSV</a>
                    <a href="{{ route('backoffice.transactions.print', request()->query()) }}" class="btn btn-green" target="_blank">Print Summary</a>
                    <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Back Office</a>
                </div>
            </div>

            <div class="content">
                <div class="hero">
                    <div class="hero-main">
                        <div class="hero-kicker">Sales Summary</div>
                        <h1 class="hero-title">Monitor transaksi cashier dengan tampilan yang lebih clean dan valid.</h1>
                        <p class="hero-subtitle">
                            Dashboard ringkas penjualan yang sudah dibersihkan dari transaksi bermasalah agar summary lebih valid untuk monitoring operasional, audit, dan presentasi ke client.
                        </p>
                    </div>

                    <div class="session-card">
                        <div>
                            <div class="session-title">Session Info</div>
                            <div class="session-line"><span class="label">User:</span>{{ $user->name }}</div>
                            <div class="session-line"><span class="label">Role:</span>{{ $user->role->name ?? '-' }}</div>
                            <div class="session-line"><span class="label">Outlet:</span>{{ $user->outlet->name ?? '-' }}</div>
                        </div>

                        <div class="session-actions">
                            <a href="{{ route('backoffice.shifts.index') }}" class="btn btn-brand">Shifts</a>
                            <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Back</a>
                        </div>
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
                    <div class="section-card">
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

                    <div class="section-card">
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
                                        <th>Waktu</th>
                                        <th>Transaction Number</th>
                                        <th>Kasir</th>
                                        <th>Outlet</th>
                                        <th>Member</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Subtotal</th>
                                        <th>Grand Total</th>
                                        <th>Amount Paid</th>
                                        <th>Change</th>
                                        <th>Void By</th>
                                        <th>Void Reason</th>
                                        <th>Items</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transactions as $transaction)
                                        @php
                                            $isProblem = in_array(strtolower((string) $transaction->status), ['stock_blocked', 'void'])
                                                || (float) ($transaction->grand_total ?? 0) <= 0;
                                        @endphp
                                        <tr>
                                            <td>{{ $transaction->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                            <td><strong>{{ $transaction->transaction_number }}</strong></td>
                                            <td>{{ $transaction->user->name ?? '-' }}</td>
                                            <td>{{ $transaction->outlet->name ?? '-' }}</td>
                                            <td>{{ $transaction->member->name ?? '-' }}</td>
                                            <td>{{ strtoupper($transaction->payment_method ?? 'UNSET') }}</td>
                                            <td>
                                                @if($isProblem)
                                                    <span class="status-badge status-problem">{{ ucfirst($transaction->status ?? '-') }}</span>
                                                @else
                                                    <span class="status-badge status-ok">{{ ucfirst($transaction->status ?? '-') }}</span>
                                                @endif
                                            </td>
                                            <td class="money">Rp{{ number_format((float) $transaction->subtotal, 0, ',', '.') }}</td>
                                            <td class="money">Rp{{ number_format((float) $transaction->grand_total, 0, ',', '.') }}</td>
                                            <td class="money">Rp{{ number_format((float) $transaction->amount_paid, 0, ',', '.') }}</td>
                                            <td class="money">Rp{{ number_format((float) $transaction->change_amount, 0, ',', '.') }}</td>
                                            <td>{{ $transaction->voidBy->name ?? '-' }}</td>
                                            <td>{{ $transaction->void_reason ?? '-' }}</td>
                                            <td>
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

                <div class="bottom-bar">
                    Sales Summary active: halaman transaksi sekarang sudah satu bahasa visual dengan Back Office Dashboard, Shift List, dan Shift Detail, jadi monitoring operasional terasa lebih konsisten.
                </div>
            </div>
        </div>
    </div>
</body>
</html>