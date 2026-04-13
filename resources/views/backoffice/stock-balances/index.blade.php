<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Control - Back Office</title>
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
            --amber: #b45309;
            --amber-soft: #fff7ed;
            --red: #b91c1c;
            --red-soft: #fef2f2;
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

        .page { min-height: 100vh; padding: 24px; }
        .shell {
            max-width: 1580px;
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

        .btn:hover { transform: translateY(-1px); opacity: 0.96; }
        .btn-dark { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); }

        .content { padding: 14px 28px 28px; }

        .hero {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 22px;
            margin-bottom: 24px;
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
            line-height: 1.02;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
            max-width: 700px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            padding: 22px;
            box-shadow: var(--shadow-soft);
            min-height: 150px;
        }

        .stat-card.orange { background: linear-gradient(180deg, #fff9f6 0%, #ffffff 100%); border-color: #f4ddd0; }
        .stat-card.green { background: linear-gradient(180deg, #f5fcf7 0%, #ffffff 100%); border-color: #d8f0de; }
        .stat-card.blue { background: linear-gradient(180deg, #f7faff 0%, #ffffff 100%); border-color: #dbe7ff; }
        .stat-card.violet { background: linear-gradient(180deg, #f8f7ff 0%, #ffffff 100%); border-color: #e3deff; }
        .stat-card.red { background: linear-gradient(180deg, #fff5f5 0%, #ffffff 100%); border-color: #fecaca; }

        .stat-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .stat-value {
            font-size: 38px;
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
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 24px;
        }

        .action-card {
            text-decoration: none;
            color: inherit;
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 24px;
            padding: 20px;
            box-shadow: var(--shadow-soft);
            min-height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 26px rgba(15, 23, 42, 0.08);
        }

        .action-icon {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            border: 1px solid transparent;
            box-shadow: 0 10px 20px rgba(15,23,42,0.05);
        }

        .icon-orange { background: #fff3eb; border-color: #f3d7c9; }
        .icon-green { background: #eefaf1; border-color: #d8f0de; }
        .icon-blue { background: #eff6ff; border-color: #dbe7ff; }
        .icon-violet { background: #f4f3ff; border-color: #e3deff; }

        .action-icon svg {
            width: 26px;
            height: 26px;
            stroke: #111827;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .action-title {
            font-size: 22px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 8px;
            letter-spacing: -0.02em;
        }

        .action-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.75;
        }

        .action-link {
            margin-top: 16px;
            font-size: 13px;
            font-weight: 800;
            color: var(--brand-dark);
        }

        .summary-panel,
        .panel {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 30px;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 22px;
        }

        .panel-head {
            padding: 24px 24px 0;
        }

        .panel-title {
            margin: 0 0 8px;
            font-size: 28px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .panel-subtitle {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
        }

        .filter-form {
            padding: 22px 24px 22px;
            display: grid;
            grid-template-columns: 1.3fr 1fr 1fr 1fr auto;
            gap: 14px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 8px;
        }

        .field select,
        .field input {
            width: 100%;
            min-height: 48px;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
            padding: 0 14px;
            font-size: 14px;
            outline: none;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .mini-button {
            min-height: 48px;
            padding: 0 16px;
            border-radius: 14px;
            border: 0;
            cursor: pointer;
            color: white;
            font-size: 13px;
            font-weight: 800;
        }

        .mini-button.apply { background: linear-gradient(135deg, #166534 0%, #1f7a44 100%); }

        .table-wrap {
            padding: 0 24px 24px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1320px;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 20px;
            overflow: hidden;
        }

        thead th {
            text-align: left;
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 16px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e8edf4;
            white-space: nowrap;
        }

        tbody td {
            padding: 16px 16px;
            border-bottom: 1px solid #edf1f6;
            vertical-align: middle;
            font-size: 14px;
            color: #111827;
        }

        tbody tr:last-child td { border-bottom: 0; }

        .qty-value { font-weight: 800; color: #1d4ed8; }
        .qty-zero {
            color: var(--red) !important;
            background: var(--red-soft);
            border-radius: 10px;
            padding: 8px 10px;
            display: inline-flex;
            align-items: center;
            font-weight: 800;
        }

        .movement-plus { color: var(--green); font-weight: 800; }
        .movement-minus { color: var(--red); font-weight: 800; }
        .movement-neutral { color: #6b7280; font-weight: 700; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-safe { background: #eefaf1; color: #166534; }
        .status-low { background: #fff7ed; color: #b45309; }
        .status-out { background: #fef2f2; color: #b91c1c; }

        .note-list {
            padding: 18px 24px 24px;
            display: grid;
            gap: 14px;
        }

        .note-card {
            border: 1px solid #e8edf4;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border-radius: 20px;
            padding: 16px;
        }

        .note-title {
            font-size: 16px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 8px;
        }

        .note-desc {
            font-size: 13px;
            line-height: 1.75;
            color: #6b7280;
        }

        .note-highlight { color: #111827; font-weight: 700; }

        @media (max-width: 1280px) {
            .hero,
            .panel-grid { grid-template-columns: 1fr; }
            .stats-grid,
            .action-grid { grid-template-columns: 1fr 1fr; }
            .filter-form { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 780px) {
            .page { padding: 14px; }
            .topbar,
            .content { padding-left: 18px; padding-right: 18px; }
            .topbar { flex-direction: column; align-items: flex-start; }
            .hero-title { font-size: 34px; }
            .stats-grid,
            .action-grid,
            .filter-form { grid-template-columns: 1fr; }
            .filter-actions { flex-direction: column; }
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
                    <div class="brand-sub">Inventory Control</div>
                </div>
            </div>

            <div class="top-actions">
                <div class="mini-info">
                    {{ $user->name ?? 'Owner ATG POS' }} • {{ $user->role->name ?? '-' }}
                </div>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Back Office</a>
            </div>
        </div>

        <div class="content">
            <div class="hero">
                <div class="hero-main">
                    <div class="hero-kicker">Inventory Control</div>
                    <h1 class="hero-title">Control stock actions and read stock summary in one place.</h1>
                    <p class="hero-subtitle">
                        Halaman ini adalah pusat kontrol stok seluruh lokasi. Pakai area ini untuk penerimaan barang dari luar, adjustment stok, import opening stock, opname gudang, produksi setengah jadi, dan membaca summary stok model operasional.
                    </p>
                </div>

                <div class="session-card">
                    <div>
                        <div class="session-title">Inventory Rule</div>
                        <div class="session-line"><span class="label">Barang dari luar:</span>Penerimaan Barang</div>
                        <div class="session-line"><span class="label">Barang antar lokasi:</span>Transfer Barang</div>
                        <div class="session-line"><span class="label">Selisih stok:</span>Adjustment</div>
                        <div class="session-line"><span class="label">Opname formal:</span>Warehouse saja</div>
                        <div class="session-line"><span class="label">Produksi:</span>Raw jadi semi-finished</div>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card orange">
                    <div class="stat-label">Locations</div>
                    <div class="stat-value">{{ $summary['locations'] ?? 0 }}</div>
                    <div class="stat-desc">Total lokasi unik yang tampil berdasarkan filter aktif.</div>
                </div>

                <div class="stat-card green">
                    <div class="stat-label">Stock Rows</div>
                    <div class="stat-value">{{ $summary['stock_rows'] ?? 0 }}</div>
                    <div class="stat-desc">Jumlah baris stock balance yang tampil di inventory control.</div>
                </div>

                <div class="stat-card blue">
                    <div class="stat-label">Safe Stock</div>
                    <div class="stat-value">{{ $summary['safe_stock'] ?? 0 }}</div>
                    <div class="stat-desc">Stok yang masih aman di atas minimum stock.</div>
                </div>

                <div class="stat-card violet">
                    <div class="stat-label">Need Action</div>
                    <div class="stat-value">{{ $summary['need_action'] ?? 0 }}</div>
                    <div class="stat-desc">Stok yang perlu dipantau untuk restock, transfer, adjustment, atau produksi ulang.</div>
                </div>

                <div class="stat-card red">
                    <div class="stat-label">Zero Stock</div>
                    <div class="stat-value">{{ $summary['zero_stock'] ?? 0 }}</div>
                    <div class="stat-desc">Baris stok bernilai 0 yang sekarang ditandai merah agar cepat terlihat.</div>
                </div>
            </div>

            <div class="action-grid">
                <a href="{{ route('backoffice.stock-balances.create') }}" class="action-card">
                    <div>
                        <div class="action-icon icon-orange">
                            <svg viewBox="0 0 24 24"><path d="M12 5v14"></path><path d="M5 12h14"></path></svg>
                        </div>
                        <div class="action-title">Penerimaan Barang</div>
                        <div class="action-desc">Untuk barang masuk dari luar sistem internal, baik ke warehouse maupun outlet.</div>
                    </div>
                    <div class="action-link">Open action</div>
                </a>

                <a href="{{ route('backoffice.stock-balances.import') }}" class="action-card">
                    <div>
                        <div class="action-icon icon-blue">
                            <svg viewBox="0 0 24 24"><path d="M12 3v12"></path><path d="m8 11 4 4 4-4"></path><path d="M4 21h16"></path></svg>
                        </div>
                        <div class="action-title">Import Opening Stock</div>
                        <div class="action-desc">Input massal stok awal dengan target lokasi yang jelas di file import.</div>
                    </div>
                    <div class="action-link">Open action</div>
                </a>

                <a href="{{ route('backoffice.stock-balances.adjustment.create') }}" class="action-card">
                    <div>
                        <div class="action-icon icon-green">
                            <svg viewBox="0 0 24 24"><path d="M4 7h16"></path><path d="M7 12h10"></path><path d="M10 17h4"></path></svg>
                        </div>
                        <div class="action-title">Adjustment</div>
                        <div class="action-desc">Koreksi stok warehouse atau outlet untuk selisih hitung, rusak, atau salah input.</div>
                    </div>
                    <div class="action-link">Open action</div>
                </a>

                <a href="{{ route('backoffice.stock-balances.opname.create') }}" class="action-card">
                    <div>
                        <div class="action-icon icon-violet">
                            <svg viewBox="0 0 24 24"><path d="M8 4h8"></path><path d="M9 4v4"></path><path d="M15 4v4"></path><path d="M7 9h10l1 9H6l1-9z"></path></svg>
                        </div>
                        <div class="action-title">Opname Gudang</div>
                        <div class="action-desc">Stock opname formal khusus warehouse, bukan untuk outlet.</div>
                    </div>
                    <div class="action-link">Open action</div>
                </a>

                <a href="{{ route('backoffice.productions.create') }}" class="action-card">
                    <div>
                        <div class="action-icon icon-orange">
                            <svg viewBox="0 0 24 24"><path d="M6 20h12"></path><path d="M8 20V9l4-5 4 5v11"></path><path d="M9 13h6"></path></svg>
                        </div>
                        <div class="action-title">Produksi</div>
                        <div class="action-desc">Jalankan produksi raw menjadi semi-finished dan update stok otomatis di lokasi aktif.</div>
                    </div>
                    <div class="action-link">Open action</div>
                </a>
            </div>

            <div class="summary-panel">
                <div class="panel-head">
                    <h2 class="panel-title">Stock Summary</h2>
                    <p class="panel-subtitle">
                        Summary ini mengikuti cara baca stok operasional: saldo awal, penerimaan, transfer, produksi, adjustment, lalu stok akhir per bahan.
                    </p>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                        <tr>
                            <th>Category</th>
                            <th>Ingredient</th>
                            <th>Unit</th>
                            <th>Saldo Awal</th>
                            <th>Penerimaan</th>
                            <th>Transfer Masuk</th>
                            <th>Transfer Keluar</th>
                            <th>Produksi Masuk</th>
                            <th>Produksi Keluar</th>
                            <th>Adjust Masuk</th>
                            <th>Adjust Keluar</th>
                            <th>Stok Akhir</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse(($stockSummaryRows ?? []) as $row)
                            @php
                                $endingQty = (float) ($row['ending_stock'] ?? 0);
                                $minimum = (float) ($row['minimum_stock'] ?? 0);

                                if ($endingQty <= 0) {
                                    $summaryStatusLabel = 'Out of Stock';
                                    $summaryStatusClass = 'status-out';
                                } elseif ($endingQty <= $minimum) {
                                    $summaryStatusLabel = 'Low Stock';
                                    $summaryStatusClass = 'status-low';
                                } else {
                                    $summaryStatusLabel = 'Safe';
                                    $summaryStatusClass = 'status-safe';
                                }
                            @endphp
                            <tr>
                                <td>{{ $row['category_name'] ?? '-' }}</td>
                                <td>{{ $row['ingredient_name'] ?? '-' }}</td>
                                <td>{{ $row['unit'] ?? '-' }}</td>
                                <td class="movement-neutral">{{ number_format((float) ($row['opening_balance'] ?? 0), 0, ',', '.') }}</td>
                                <td class="movement-plus">+{{ number_format((float) ($row['stock_in'] ?? 0), 0, ',', '.') }}</td>
                                <td class="movement-plus">+{{ number_format((float) ($row['transfer_in'] ?? 0), 0, ',', '.') }}</td>
                                <td class="movement-minus">-{{ number_format((float) ($row['transfer_out'] ?? 0), 0, ',', '.') }}</td>
                                <td class="movement-plus">+{{ number_format((float) ($row['production_in'] ?? 0), 0, ',', '.') }}</td>
                                <td class="movement-minus">-{{ number_format((float) ($row['production_out'] ?? 0), 0, ',', '.') }}</td>
                                <td class="movement-plus">+{{ number_format((float) ($row['adjustment_in'] ?? 0), 0, ',', '.') }}</td>
                                <td class="movement-minus">-{{ number_format((float) ($row['adjustment_out'] ?? 0), 0, ',', '.') }}</td>
                                <td>
                                    @if($endingQty <= 0)
                                        <span class="qty-zero">{{ number_format($endingQty, 0, ',', '.') }}</span>
                                    @else
                                        <span class="qty-value">{{ number_format($endingQty, 0, ',', '.') }}</span>
                                    @endif
                                </td>
                                <td><span class="status-badge {{ $summaryStatusClass }}">{{ $summaryStatusLabel }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13">Belum ada data summary stok.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel-grid">
                <div class="panel">
                    <div class="panel-head">
                        <h2 class="panel-title">All Stock Balances</h2>
                        <p class="panel-subtitle">
                            Monitor seluruh stok lintas lokasi. Stok bernilai 0 sekarang otomatis ditandai merah supaya lebih cepat terlihat.
                        </p>
                    </div>

                    <form method="GET" action="{{ route('backoffice.stock-balances.index') }}" class="filter-form">
                        <div class="field">
                            <label for="ingredient_id">Ingredient</label>
                            <select name="ingredient_id" id="ingredient_id">
                                <option value="">Semua ingredient</option>
                                @foreach(($ingredients ?? []) as $ingredient)
                                    <option value="{{ $ingredient->id }}" {{ request('ingredient_id') == $ingredient->id ? 'selected' : '' }}>
                                        {{ $ingredient->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label for="location_type">Location Type</label>
                            <select name="location_type" id="location_type">
                                <option value="">Semua location type</option>
                                <option value="warehouse" {{ request('location_type') === 'warehouse' ? 'selected' : '' }}>Warehouse</option>
                                <option value="outlet" {{ request('location_type') === 'outlet' ? 'selected' : '' }}>Outlet</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="status">Stock Status</label>
                            <select name="status" id="status">
                                <option value="">Semua status</option>
                                <option value="safe" {{ request('status') === 'safe' ? 'selected' : '' }}>Safe</option>
                                <option value="low" {{ request('status') === 'low' ? 'selected' : '' }}>Low</option>
                                <option value="out" {{ request('status') === 'out' ? 'selected' : '' }}>Out of Stock</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="keyword">Search</label>
                            <input type="text" name="keyword" id="keyword" value="{{ request('keyword') }}" placeholder="Cari ingredient / lokasi">
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="mini-button apply">Apply</button>
                            <a href="{{ route('backoffice.stock-balances.index') }}" class="btn btn-dark" style="min-height:48px;">Reset</a>
                        </div>
                    </form>

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Category</th>
                                <th>Ingredient</th>
                                <th>Minimum Stock</th>
                                <th>Location Type</th>
                                <th>Location</th>
                                <th>Qty On Hand</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse(($stocks ?? []) as $stock)
                                @php
                                    $qty = (float) ($stock->qty_on_hand ?? 0);
                                    $min = (float) ($stock->ingredient->minimum_stock ?? 0);

                                    if ($qty <= 0) {
                                        $statusLabel = 'Out of Stock';
                                        $statusClass = 'status-out';
                                    } elseif ($qty <= $min) {
                                        $statusLabel = 'Low Stock';
                                        $statusClass = 'status-low';
                                    } else {
                                        $statusLabel = 'Safe';
                                        $statusClass = 'status-safe';
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $stock->ingredient->category->name ?? '-' }}</td>
                                    <td>{{ $stock->ingredient->name ?? '-' }}</td>
                                    <td>{{ number_format((float) ($stock->ingredient->minimum_stock ?? 0), 0, ',', '.') }}</td>
                                    <td>{{ ucfirst($stock->location_type ?? '-') }}</td>
                                    <td>
                                        @if(($stock->location_type ?? null) === 'warehouse')
                                            {{ $stock->warehouse->name ?? ('Warehouse ID ' . ($stock->location_id ?? '-')) }}
                                        @elseif(($stock->location_type ?? null) === 'outlet')
                                            {{ $stock->outlet->name ?? ('Outlet ID ' . ($stock->location_id ?? '-')) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($qty <= 0)
                                            <span class="qty-zero">{{ number_format($qty, 0, ',', '.') }}</span>
                                        @else
                                            <span class="qty-value">{{ number_format($qty, 0, ',', '.') }}</span>
                                        @endif
                                    </td>
                                    <td><span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7">Belum ada data stock balance.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <h2 class="panel-title">Rule Reminder</h2>
                        <p class="panel-subtitle">
                            Gunakan rule ini supaya user dan client tidak bingung membedakan inventory action dengan transfer internal dan produksi.
                        </p>
                    </div>

                    <div class="note-list">
                        <div class="note-card">
                            <div class="note-title">Penerimaan Barang</div>
                            <div class="note-desc">
                                Dipakai saat barang datang dari <span class="note-highlight">luar sistem internal</span>, misalnya supplier, vendor, atau pembelian darurat outlet.
                            </div>
                        </div>

                        <div class="note-card">
                            <div class="note-title">Transfer Barang</div>
                            <div class="note-desc">
                                Dipakai saat barang berpindah dari <span class="note-highlight">lokasi internal ke lokasi internal lain</span>, misalnya warehouse ke outlet atau outlet ke outlet.
                            </div>
                        </div>

                        <div class="note-card">
                            <div class="note-title">Adjustment</div>
                            <div class="note-desc">
                                Dipakai untuk <span class="note-highlight">koreksi stok</span>, baik di warehouse maupun di outlet, misalnya karena selisih hitung, rusak, atau salah input.
                            </div>
                        </div>

                        <div class="note-card">
                            <div class="note-title">Opname Gudang</div>
                            <div class="note-desc">
                                Dipakai untuk <span class="note-highlight">stock opname formal khusus warehouse</span>. Outlet tidak perlu opname formal, cukup gunakan adjustment jika ada selisih stok.
                            </div>
                        </div>

                        <div class="note-card">
                            <div class="note-title">Produksi</div>
                            <div class="note-desc">
                                Dipakai saat <span class="note-highlight">bahan mentah diproses menjadi bahan setengah jadi</span>. Sistem akan membuat movement <span class="note-highlight">production_out</span> dan <span class="note-highlight">production_in</span>.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>