@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Transfers - Back Office';
@endphp

@section('content')
    <style>
        .transfer-shell {
            display: grid;
            gap: 22px;
        }

        .transfer-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .transfer-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .transfer-kicker {
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

        .transfer-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .transfer-subtitle {
            margin: 0;
            max-width: 900px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .transfer-actions {
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

        .btn-secondary {
            background: linear-gradient(135deg, #475569 0%, #64748b 100%);
        }

        .btn-danger {
            background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
        }

        .btn-warning {
            background: linear-gradient(135deg, #b45309 0%, #d97706 100%);
        }

        .btn-small {
            min-height: 36px;
            padding: 0 12px;
            border-radius: 12px;
            font-size: 12px;
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
            grid-template-columns: 1.05fr 0.95fr;
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

        .info-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e8edf4;
            border-radius: 28px;
            padding: 24px;
        }

        .info-title {
            margin: 0 0 14px;
            font-size: 18px;
            font-weight: 800;
            color: #111827;
        }

        .info-line {
            font-size: 14px;
            line-height: 1.9;
            color: #374151;
        }

        .info-line strong {
            color: #111827;
        }

        .alert {
            margin: 20px 24px 0;
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
            background: #ffe8e8;
            color: #9b1c1c;
            border: 1px solid #fecaca;
        }

        .summary-grid {
            margin: 20px 24px 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .summary-card {
            border-radius: 24px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
            background: white;
        }

        .summary-label {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 10px;
            color: #6b7280;
        }

        .summary-value {
            font-size: 34px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 8px;
        }

        .summary-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
        }

        .summary-total {
            background: linear-gradient(180deg, #fff9f6 0%, #ffffff 100%);
            border-color: #f4ddd0;
        }

        .summary-total .summary-value {
            color: #c9552a;
        }

        .summary-transit {
            background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
            border-color: #bfdbfe;
        }

        .summary-transit .summary-value {
            color: #1d4ed8;
        }

        .summary-received {
            background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
            border-color: #bbf7d0;
        }

        .summary-received .summary-value {
            color: #166534;
        }

        .summary-cancelled {
            background: linear-gradient(180deg, #fff1f2 0%, #ffffff 100%);
            border-color: #fecdd3;
        }

        .summary-cancelled .summary-value {
            color: #b91c1c;
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
            grid-template-columns: 1fr 1fr 1fr auto auto;
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

        .field select:focus {
            border-color: rgba(232,106,58,0.55);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .table-wrap {
            padding: 0 22px 22px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 2200px;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 18px;
            overflow: hidden;
        }

        th, td {
            text-align: left;
            padding: 15px 14px;
            border-bottom: 1px solid #edf1f6;
            vertical-align: top;
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
            white-space: nowrap;
        }

        .badge-pending {
            background: #fff7ed;
            color: #9a3412;
        }

        .badge-in-transit {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .badge-received {
            background: #e8fff1;
            color: #17663a;
        }

        .badge-cancelled {
            background: #ffe8e8;
            color: #9b1c1c;
        }

        .status-note {
            margin-top: 6px;
            font-size: 12px;
            color: #6b7280;
            line-height: 1.6;
            max-width: 220px;
        }

        .action-stack {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            min-width: 230px;
        }

        .inline-form {
            display: inline-block;
            margin: 0;
        }

        .empty {
            margin: 0 22px 22px;
            padding: 18px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 16px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }


        .transfer-group-row {
            cursor: pointer;
        }

        .transfer-group-row:hover td {
            background: #fbfcff;
        }

        .group-detail-row {
            display: none;
        }

        .group-detail-row.open {
            display: table-row;
        }

        .group-detail-cell {
            background: #fbfcfe;
            padding: 0 !important;
        }

        .group-detail-box {
            margin: 0;
            padding: 18px;
            border-top: 1px solid #e8edf4;
        }

        .group-detail-title {
            margin: 0 0 12px;
            font-size: 14px;
            font-weight: 900;
            color: #111827;
        }

        .detail-table {
            min-width: 920px;
            border: 1px solid #e8edf4;
            border-radius: 16px;
            overflow: hidden;
        }

        .detail-table th,
        .detail-table td {
            font-size: 13px;
        }

        .btn-toggle-detail {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .badge-mixed {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
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

        @media (max-width: 1380px) {
            .hero-wrap {
                grid-template-columns: 1fr;
            }

            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }

            .filter-form {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 860px) {
            .transfer-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .transfer-title {
                font-size: 32px;
            }

            .hero-heading {
                font-size: 28px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .hero-wrap {
                padding-left: 18px;
                padding-right: 18px;
            }

            .section-card,
            .alert,
            .summary-grid,
    
        .transfer-group-row {
            cursor: pointer;
        }

        .transfer-group-row:hover td {
            background: #fbfcff;
        }

        .group-detail-row {
            display: none;
        }

        .group-detail-row.open {
            display: table-row;
        }

        .group-detail-cell {
            background: #fbfcfe;
            padding: 0 !important;
        }

        .group-detail-box {
            margin: 0;
            padding: 18px;
            border-top: 1px solid #e8edf4;
        }

        .group-detail-title {
            margin: 0 0 12px;
            font-size: 14px;
            font-weight: 900;
            color: #111827;
        }

        .detail-table {
            min-width: 920px;
            border: 1px solid #e8edf4;
            border-radius: 16px;
            overflow: hidden;
        }

        .detail-table th,
        .detail-table td {
            font-size: 13px;
        }

        .btn-toggle-detail {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .badge-mixed {
            background: #fff7ed;
            color: #c2410c;
            border: 1px solid #fed7aa;
        }

        .bottom-bar {
                margin-left: 18px;
                margin-right: 18px;
            }
        }
    
        .filter-form {
            grid-template-columns: repeat(5, minmax(0, 1fr));
            align-items: end;
        }

        .filter-form .field input[type="date"] {
            width: 100%;
            min-height: 52px;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
            padding: 0 14px;
            font-size: 14px;
            color: #111827;
            outline: none;
            box-sizing: border-box;
            font-family: inherit;
            appearance: auto;
            -webkit-appearance: auto;
        }

        .filter-form .btn {
            width: 100%;
            min-height: 52px;
        }

        .transfer-master-detail {
            display: grid;
            grid-template-columns: minmax(0, 0.95fr) minmax(480px, 1.05fr);
            gap: 18px;
            align-items: start;
        }

        .transfer-master-panel,
        .transfer-side-panel {
            min-width: 0;
        }

        .transfer-side-panel {
            position: sticky;
            top: 18px;
            align-self: start;
        }

        .transfer-side-placeholder {
            border: 1px dashed #d7dce5;
            background: #fbfcfe;
            border-radius: 22px;
            padding: 28px;
            color: #6b7280;
            font-weight: 800;
            line-height: 1.7;
        }

        .transfer-side-card {
            display: none;
            border: 1px solid #e8edf4;
            background: #ffffff;
            border-radius: 22px;
            padding: 18px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
        }

        .transfer-side-card.is-active {
            display: block;
        }

        .transfer-side-card .table-wrap {
            padding: 0;
            overflow-x: auto;
        }

        .transfer-side-card .detail-table {
            min-width: 900px;
        }

        .transfer-group-row {
            cursor: pointer;
        }

        .transfer-group-row.is-active {
            background: #f8faff;
        }

        .group-detail-row {
            display: none !important;
        }

        @media (max-width: 1280px) {
            .filter-form {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .transfer-master-detail {
                grid-template-columns: 1fr;
            }

            .transfer-side-panel {
                position: static;
            }
        }

        @media (max-width: 760px) {
            .filter-form {
                grid-template-columns: 1fr;
            }
        }

    </style>

    <div class="transfer-shell">
        <div class="transfer-topbar">
            <div class="transfer-title-block">

                <h1 class="transfer-title">Back Office - Transfers</h1>

            </div>

            <div class="transfer-actions">
                <a href="{{ route('backoffice.transfers.export.csv', request()->query()) }}" class="btn btn-blue">Export CSV</a>
                <a href="{{ route('backoffice.transfers.create') }}" class="btn btn-green">Buat Transfer</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Dashboard</a>
            </div>
        </div>

        <div class="card">

                    <div class="info-line">
</div>
                    <div class="info-line">
</div>
                    <div class="info-line">
</div>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            <div class="summary-grid">
                <div class="summary-card summary-total">
                    <div class="summary-label">Total Transfer Item</div>
                    <div class="summary-value">{{ $summary['total'] ?? 0 }}</div>
                    <div class="summary-desc">Total semua item transfer yang tampil sesuai filter aktif.</div>
                </div>

                <div class="summary-card summary-transit">
                    <div class="summary-label">In Transit</div>
                    <div class="summary-value">{{ $summary['in_transit'] ?? 0 }}</div>
                    <div class="summary-desc">Item transfer yang masih dalam proses pengiriman.</div>
                </div>

                <div class="summary-card summary-received">
                    <div class="summary-label">Received</div>
                    <div class="summary-value">{{ $summary['received'] ?? 0 }}</div>
                    <div class="summary-desc">Item transfer yang sudah diterima di lokasi tujuan.</div>
                </div>

                <div class="summary-card summary-cancelled">
                    <div class="summary-label">Cancelled</div>
                    <div class="summary-value">{{ $summary['cancelled'] ?? 0 }}</div>
                    <div class="summary-desc">Item transfer yang dibatalkan dan stoknya sudah di-rollback.</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">Filter Transfer</h2>

                </div>

                <form method="GET" action="{{ route('backoffice.transfers.index') }}" class="filter-form">
                    <div class="field">
                        <label>Dari Tipe</label>
                        <select name="from_location_type">
                            <option value="">Semua asal</option>
                            <option value="warehouse" @selected(($filters['from_location_type'] ?? '') === 'warehouse')>warehouse</option>
                            <option value="outlet" @selected(($filters['from_location_type'] ?? '') === 'outlet')>outlet</option>
                        </select>
                    </div>

                    <div class="field">
                        <label>Ke Tipe</label>
                        <select name="to_location_type">
                            <option value="">Semua tujuan</option>
                            <option value="warehouse" @selected(($filters['to_location_type'] ?? '') === 'warehouse')>warehouse</option>
                            <option value="outlet" @selected(($filters['to_location_type'] ?? '') === 'outlet')>outlet</option>
                        </select>
                    </div>

                    <div class="field">
                        <label>Dari Tanggal</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                    </div>

                    <div class="field">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
                    </div>

                    <div class="field">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Semua status</option>
                            <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>pending</option>
                            <option value="in_transit" @selected(($filters['status'] ?? '') === 'in_transit')>in_transit</option>
                            <option value="received" @selected(($filters['status'] ?? '') === 'received')>received</option>
                            <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>cancelled</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-green">Apply Filter</button>
                    <a href="{{ route('backoffice.transfers.index') }}" class="btn btn-dark">Reset</a>
                </form>
            </div>

            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">Transfer List</h2>

                </div>

                @if(($transferGroups ?? collect())->count())
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Transfer No</th>
                                    <th>Date</th>
                                    <th>Dari</th>
                                    <th>Ke</th>
                                    <th>Total Item</th>
                                    <th>Total Qty</th>
                                    <th>Status</th>
                                    <th>Dikirim Oleh</th>
                                    <th>User Input</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transferGroups as $groupIndex => $group)
                                    @php
                                        $groupStatus = $group['status'] ?? '-';
                                        $badgeClass = match($groupStatus) {
                                            'pending' => 'badge-pending',
                                            'received' => 'badge-received',
                                            'cancelled' => 'badge-cancelled',
                                            'mixed' => 'badge-mixed',
                                            default => 'badge-in-transit',
                                        };

                                        $detailId = 'transfer-detail-' . $groupIndex;
                                    @endphp

                                    <tr class="transfer-group-row" data-transfer-toggle="{{ $detailId }}">
                                        <td class="number">{{ $group['group_number'] ?? '-' }}</td>
                                        <td>{{ $group['date'] ? $group['date']->format('Y-m-d H:i:s') : '-' }}</td>
                                        <td>
                                            <strong>{{ $group['from_location_name'] ?? '-' }}</strong><br>
                                            <span style="color:#6b7280; font-size:12px;">{{ $group['from_location_type'] ?? '-' }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $group['to_location_name'] ?? '-' }}</strong><br>
                                            <span style="color:#6b7280; font-size:12px;">{{ $group['to_location_type'] ?? '-' }}</span>
                                        </td>
                                        <td>{{ number_format((int) ($group['item_count'] ?? 0), 0, ',', '.') }} item</td>
                                        <td class="qty">{{ number_format((float) ($group['total_qty'] ?? 0), 0, ',', '.') }}</td>
                                        <td>
                                            <span class="badge {{ $badgeClass }}">{{ strtoupper($groupStatus) }}</span>
                                        </td>
                                        <td>{{ $group['sender_name'] ?? '-' }}</td>
                                        <td>{{ $group['transferred_by'] ?? '-' }}</td>
                                        <td>
                                            <button type="button" class="btn btn-small btn-toggle-detail" data-transfer-toggle="{{ $detailId }}">
                                                Detail
                                            </button>
                                        </td>
                                    </tr>

                                    <tr id="{{ $detailId }}" class="group-detail-row">
                                        <td colspan="10" class="group-detail-cell">
                                            <div class="group-detail-box">
                                                <h3 class="group-detail-title">Detail Item Transfer</h3>

                                                <div class="table-wrap" style="padding:0;">
                                                    <table class="detail-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Transfer Item No</th>
                                                                <th>Category</th>
                                                                <th>Ingredient</th>
                                                                <th>Qty</th>
                                                                <th>Status</th>
                                                                <th>Diterima Oleh</th>
                                                                <th>Tanggal Dikirim</th>
                                                                <th>Tanggal Diterima</th>
                                                                <th>Note</th>
                                                                <th>Aksi Item</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($group['items'] as $transfer)
                                                                @php
                                                                    $itemBadgeClass = match($transfer->status) {
                                                                        'pending' => 'badge-pending',
                                                                        'received' => 'badge-received',
                                                                        'cancelled' => 'badge-cancelled',
                                                                        default => 'badge-in-transit',
                                                                    };
                                                                @endphp

                                                                <tr>
                                                                    <td class="number">{{ $transfer->transfer_number ?? '-' }}</td>
                                                                    <td>{{ $transfer->ingredient->category->name ?? '-' }}</td>
                                                                    <td>{{ $transfer->ingredient->name ?? '-' }}</td>
                                                                    <td class="qty">{{ number_format((float) $transfer->qty, 0, ',', '.') }}</td>
                                                                    <td>
                                                                        <span class="badge {{ $itemBadgeClass }}">{{ strtoupper($transfer->status ?? '-') }}</span>
                                                                    </td>
                                                                    <td>{{ $transfer->receiver_name ?? '-' }}</td>
                                                                    <td>{{ $transfer->sent_at ? $transfer->sent_at->format('Y-m-d H:i') : '-' }}</td>
                                                                    <td>{{ $transfer->received_at ? $transfer->received_at->format('Y-m-d H:i') : '-' }}</td>
                                                                    <td>{{ $transfer->note ?? '-' }}</td>
                                                                    <td>
                                                                        <div class="action-stack">
                                                                            @if($transfer->status === 'pending')
                                                                                <form method="POST" action="{{ route('backoffice.transfers.mark-in-transit', $transfer) }}" class="inline-form" onsubmit="return confirm('Ubah item transfer ini menjadi in transit?');">
                                                                                    @csrf
                                                                                    <button type="submit" class="btn btn-blue btn-small">Kirimkan Item</button>
                                                                                </form>

                                                                                <form method="POST" action="{{ route('backoffice.transfers.mark-cancelled', $transfer) }}" class="inline-form" onsubmit="return confirm('Batalkan item transfer ini dan rollback stok?');">
                                                                                    @csrf
                                                                                    <button type="submit" class="btn btn-danger btn-small">Batalkan Item</button>
                                                                                </form>
                                                                            @elseif($transfer->status === 'in_transit')
                                                                                <form method="POST" action="{{ route('backoffice.transfers.mark-received', $transfer) }}" class="inline-form" onsubmit="return confirm('Tandai item transfer ini sebagai diterima?');">
                                                                                    @csrf
                                                                                    <button type="submit" class="btn btn-blue btn-small">Tandai Diterima</button>
                                                                                </form>

                                                                                <form method="POST" action="{{ route('backoffice.transfers.mark-cancelled', $transfer) }}" class="inline-form" onsubmit="return confirm('Batalkan item transfer ini dan rollback stok?');">
                                                                                    @csrf
                                                                                    <button type="submit" class="btn btn-danger btn-small">Batalkan Item</button>
                                                                                </form>
                                                                            @elseif($transfer->status === 'received')
                                                                                <form method="POST" action="{{ route('backoffice.transfers.mark-in-transit', $transfer) }}" class="inline-form" onsubmit="return confirm('Kembalikan item transfer ini ke in transit?');">
                                                                                    @csrf
                                                                                    <button type="submit" class="btn btn-warning btn-small">Kembalikan ke In Transit</button>
                                                                                </form>
                                                                            @elseif($transfer->status === 'cancelled')
                                                                                <form method="POST" action="{{ route('backoffice.transfers.mark-in-transit', $transfer) }}" class="inline-form" onsubmit="return confirm('Aktifkan lagi item transfer ini ke status in transit? Stock akan dipindahkan lagi.');">
                                                                                    @csrf
                                                                                    <button type="submit" class="btn btn-secondary btn-small">Aktifkan Lagi</button>
                                                                                </form>
                                                                            @else
                                                                                <span style="color:#6b7280; font-size:12px; font-weight:700;">Tidak ada aksi</span>
                                                                            @endif
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty">
                        Belum ada transfer tersimpan.
                    </div>
                @endif
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-transfer-toggle]');

            if (!trigger) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const targetId = trigger.getAttribute('data-transfer-toggle');
            const target = document.getElementById(targetId);

            if (target) {
                target.classList.toggle('open');
            }
        });
    </script>


    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const title = Array.from(document.querySelectorAll('.section-title')).find(function (item) {
                return item.textContent.trim() === 'Transfer List';
            });

            if (!title) {
                return;
            }

            const section = title.closest('.section-card');

            if (!section) {
                return;
            }

            let tableWrap = section.querySelector(':scope > .table-wrap');
            let masterDetail = section.querySelector('.transfer-master-detail');
            let masterPanel = section.querySelector('.transfer-master-panel');
            let sidePanel = section.querySelector('.transfer-side-panel');
            let placeholder = section.querySelector('#transfer-side-placeholder');

            if (!masterDetail && tableWrap) {
                masterDetail = document.createElement('div');
                masterDetail.className = 'transfer-master-detail';

                masterPanel = document.createElement('div');
                masterPanel.className = 'transfer-master-panel';

                sidePanel = document.createElement('div');
                sidePanel.className = 'transfer-side-panel';

                placeholder = document.createElement('div');
                placeholder.className = 'transfer-side-placeholder';
                placeholder.id = 'transfer-side-placeholder';
                placeholder.textContent = 'Pilih salah satu transfer di kiri untuk melihat detail item transfer.';

                sidePanel.appendChild(placeholder);

                section.insertBefore(masterDetail, tableWrap);
                masterPanel.appendChild(tableWrap);
                masterDetail.appendChild(masterPanel);
                masterDetail.appendChild(sidePanel);
            }

            if (!sidePanel) {
                return;
            }

            if (!placeholder) {
                placeholder = document.createElement('div');
                placeholder.className = 'transfer-side-placeholder';
                placeholder.id = 'transfer-side-placeholder';
                placeholder.textContent = 'Pilih salah satu transfer di kiri untuk melihat detail item transfer.';
                sidePanel.prepend(placeholder);
            }

            section.querySelectorAll('.group-detail-row').forEach(function (detailRow) {
                const detailId = detailRow.id;
                const detailBox = detailRow.querySelector('.group-detail-box');

                if (!detailId || !detailBox) {
                    detailRow.remove();
                    return;
                }

                let card = sidePanel.querySelector('#' + CSS.escape(detailId));

                if (!card) {
                    card = document.createElement('div');
                    card.className = 'transfer-side-card';
                    card.id = detailId;
                    card.setAttribute('data-transfer-side-card', '1');
                    sidePanel.appendChild(card);
                }

                card.innerHTML = '';
                card.appendChild(detailBox);
                detailRow.remove();
            });

            function openTransferDetail(targetId) {
                if (!targetId) {
                    return;
                }

                const target = sidePanel.querySelector('#' + CSS.escape(targetId));

                sidePanel.querySelectorAll('[data-transfer-side-card]').forEach(function (card) {
                    card.classList.remove('is-active');
                });

                section.querySelectorAll('.transfer-group-row').forEach(function (row) {
                    row.classList.remove('is-active');
                });

                if (target) {
                    target.classList.add('is-active');
                    placeholder.style.display = 'none';
                }

                const activeRow = section.querySelector('.transfer-group-row[data-transfer-toggle="' + targetId + '"]');

                if (activeRow) {
                    activeRow.classList.add('is-active');
                }
            }

            section.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-transfer-toggle]');

                if (!trigger || !section.contains(trigger)) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                openTransferDetail(trigger.getAttribute('data-transfer-toggle'));
            });

            section.querySelectorAll('.transfer-group-row[data-transfer-toggle]').forEach(function (row) {
                row.setAttribute('tabindex', '0');

                row.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        openTransferDetail(row.getAttribute('data-transfer-toggle'));
                    }
                });
            });

            const firstRow = section.querySelector('.transfer-group-row[data-transfer-toggle]');

            if (firstRow) {
                openTransferDetail(firstRow.getAttribute('data-transfer-toggle'));
            }
        });
    </script>

@endsection