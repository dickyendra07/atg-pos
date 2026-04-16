@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Inventory Control - Back Office';
@endphp

@section('content')
    <style>
        .inventory-shell {
            display: grid;
            gap: 22px;
        }

        .inventory-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .inventory-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .inventory-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #dbe7ff;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .inventory-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .inventory-subtitle {
            margin: 0;
            max-width: 860px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .inventory-actions {
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

        .btn-blue {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
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
            background: #ffe8e8;
            color: #9b1c1c;
            border: 1px solid #fecaca;
        }

        .card {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 30px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .hero-wrap {
            padding: 24px 24px 0;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 20px;
        }

        .hero-card {
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
            background: radial-gradient(circle, rgba(232,106,58,0.14) 0%, rgba(232,106,58,0.03) 65%, rgba(232,106,58,0) 80%);
            pointer-events: none;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #f2dfd4;
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 14px;
            position: relative;
            z-index: 1;
        }

        .hero-heading {
            margin: 0 0 10px;
            font-size: 34px;
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.03em;
            color: #111827;
            position: relative;
            z-index: 1;
        }

        .hero-text {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
            max-width: 760px;
            position: relative;
            z-index: 1;
        }

        .rule-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e8edf4;
            border-radius: 28px;
            padding: 24px;
        }

        .rule-title {
            margin: 0 0 14px;
            font-size: 18px;
            font-weight: 800;
            color: #111827;
        }

        .rule-line {
            font-size: 14px;
            line-height: 1.9;
            color: #374151;
        }

        .rule-line strong {
            color: #111827;
        }

        .stats-grid {
            padding: 20px 24px 0;
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
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
            background: linear-gradient(180deg, #fff5f5 0%, #ffffff 100%);
            border-color: #fecaca;
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
            color: #111827;
        }

        .stat-card.orange .stat-value { color: #c9552a; }
        .stat-card.green .stat-value { color: #166534; }
        .stat-card.blue .stat-value { color: #1d4ed8; }
        .stat-card.violet .stat-value { color: #5b4bd1; }
        .stat-card.red .stat-value { color: #b91c1c; }

        .stat-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
        }

        .action-grid {
            padding: 20px 24px 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .action-card {
            text-decoration: none;
            color: inherit;
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 24px;
            padding: 20px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            min-height: 170px;
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
            width: 56px;
            height: 56px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            border: 1px solid transparent;
        }

        .icon-orange { background: #fff3eb; border-color: #f3d7c9; }
        .icon-green { background: #eefaf1; border-color: #d8f0de; }
        .icon-blue { background: #eff6ff; border-color: #dbe7ff; }
        .icon-violet { background: #f4f3ff; border-color: #e3deff; }

        .action-icon svg {
            width: 24px;
            height: 24px;
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
            margin-top: 14px;
            font-size: 13px;
            font-weight: 800;
            color: #c9552a;
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
            margin: 0 0 18px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
        }

        .filter-form {
            padding: 0 22px 22px;
            display: grid;
            grid-template-columns: 1.3fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
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

        .field select:focus,
        .field input:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .table-wrap {
            padding: 0 22px 22px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1260px;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 18px;
            overflow: hidden;
        }

        th, td {
            text-align: left;
            padding: 15px 14px;
            border-bottom: 1px solid #edf1f6;
            vertical-align: middle;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            color: #6b7280;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        tbody tr:hover {
            background: #fcfcfd;
        }

        .qty-value {
            font-weight: 800;
            color: #1d4ed8;
        }

        .qty-zero {
            color: #b91c1c !important;
            background: #fff1f1;
            border-radius: 10px;
            padding: 8px 10px;
            display: inline-flex;
            align-items: center;
            font-weight: 800;
        }

        .movement-plus { color: #166534; font-weight: 800; }
        .movement-minus { color: #b91c1c; font-weight: 800; }
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
        .status-out { background: #fff1f1; color: #b91c1c; }

        .empty {
            margin: 0 22px 22px;
            padding: 18px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 16px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }

        .note-list {
            padding: 0 22px 22px;
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

        .note-highlight {
            color: #111827;
            font-weight: 700;
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
            line-height: 1.7;
        }

        .panel-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 20px;
        }

        @media (max-width: 1320px) {
            .hero-wrap,
            .panel-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr 1fr;
            }

            .action-grid {
                grid-template-columns: 1fr 1fr;
            }

            .filter-form {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 860px) {
            .inventory-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .inventory-title {
                font-size: 32px;
            }

            .hero-heading {
                font-size: 28px;
            }

            .stats-grid,
            .action-grid,
            .filter-form {
                grid-template-columns: 1fr;
            }

            .hero-wrap,
            .stats-grid,
            .action-grid {
                padding-left: 18px;
                padding-right: 18px;
            }

            .section-card,
            .bottom-bar {
                margin-left: 18px;
                margin-right: 18px;
            }
        }
    </style>

    <div class="inventory-shell">
        <div class="inventory-topbar">
            <div class="inventory-title-block">
                <div class="inventory-kicker">Inventory Workspace</div>
                <h1 class="inventory-title">Back Office - Inventory Control</h1>
                <p class="inventory-subtitle">
                    Pusat kontrol stok seluruh lokasi untuk penerimaan barang, import opening stock, adjustment, opname, dan pembacaan stock summary operasional dalam satu halaman yang konsisten dengan sidebar back office.
                </p>
            </div>

            <div class="inventory-actions">
                <a href="{{ route('backoffice.stock-balances.export.csv', request()->query()) }}" class="btn btn-blue">Export CSV</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Dashboard</a>
            </div>
        </div>

        <div class="card">
            <div class="hero-wrap">
                <div class="hero-card">
                    <div class="hero-kicker">Inventory Control</div>
                    <h2 class="hero-heading">Control stock actions and read stock summary in one place.</h2>
                    <p class="hero-text">
                        Halaman ini adalah pusat kontrol stok seluruh lokasi. Pakai area ini untuk penerimaan barang dari luar, adjustment stok, import opening stock, dan membaca summary stok model operasional.
                    </p>
                </div>

                <div class="rule-card">
                    <h3 class="rule-title">Inventory Rule</h3>
                    <div class="rule-line"><strong>Barang dari luar:</strong> Penerimaan Barang</div>
                    <div class="rule-line"><strong>Barang antar lokasi:</strong> Transfer Barang</div>
                    <div class="rule-line"><strong>Selisih stok:</strong> Adjustment</div>
                    <div class="rule-line"><strong>Opname formal:</strong> Warehouse saja</div>
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
                    <div class="stat-desc">Stok yang perlu dipantau untuk restock, transfer, atau adjustment.</div>
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
            </div>

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">Stock Summary</h2>
                    <p class="section-subtitle">
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

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">All Stock Balances</h2>
                    <p class="section-subtitle">
                        Monitor seluruh stok lintas lokasi. Stok bernilai 0 otomatis ditandai merah supaya lebih cepat terlihat.
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
                        <button type="submit" class="btn btn-brand">Apply</button>
                        <a href="{{ route('backoffice.stock-balances.index') }}" class="btn btn-dark">Reset</a>
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

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">Rule Reminder</h2>
                    <p class="section-subtitle">
                        Gunakan rule ini supaya user dan client tidak bingung membedakan inventory action dengan transfer internal.
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
                </div>
            </div>

            <div class="bottom-bar">
                Inventory Control sekarang sudah ikut layout sidebar back office, jadi navigasi tidak hilang saat pindah halaman dan tampilannya tetap konsisten, clean, dan elegant.
            </div>
        </div>
    </div>
@endsection