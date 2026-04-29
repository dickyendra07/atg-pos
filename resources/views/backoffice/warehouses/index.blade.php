@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Warehouses - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .warehouses-shell {
            display: grid;
            gap: 22px;
        }

        .warehouses-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .warehouses-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .warehouses-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #d8f0de;
            color: #166534;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .warehouses-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .warehouses-subtitle {
            margin: 0;
            max-width: 860px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .warehouses-actions {
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

        .btn-primary { background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%); }
        .btn-dark { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); }
        .btn-info { background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); }
        .btn-green { background: linear-gradient(135deg, #166534 0%, #1f7a44 100%); }

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

        .card {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 30px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .hero-card {
            margin: 24px 24px 0;
            background: linear-gradient(135deg, #ffffff 0%, #f7fcf8 70%, #eefaf1 100%);
            border: 1px solid #d8f0de;
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
            background: radial-gradient(circle, rgba(22,101,52,0.12) 0%, rgba(22,101,52,0.03) 65%, rgba(22,101,52,0) 80%);
            pointer-events: none;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #d8f0de;
            color: #166534;
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
            max-width: 780px;
            position: relative;
            z-index: 1;
        }

        .helper {
            margin: 20px 24px 0;
            background: #eef2ff;
            color: #3730a3;
            padding: 15px 16px;
            border-radius: 16px;
            font-weight: 700;
            border: 1px solid #dbe3ff;
            line-height: 1.75;
        }

        .summary-grid {
            padding: 20px 24px 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .summary-card {
            border-radius: 22px;
            padding: 20px;
            border: 1px solid #e8edf4;
            background: rgba(255,255,255,0.92);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            min-height: 140px;
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

        .summary-card.red {
            background: linear-gradient(180deg, #fff8f8 0%, #ffffff 100%);
            border-color: #f6d4d1;
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
            font-size: 36px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 12px;
            color: #111827;
        }

        .summary-card.orange .summary-value { color: #c9552a; }
        .summary-card.green .summary-value { color: #166534; }
        .summary-card.blue .summary-value { color: #1d4ed8; }
        .summary-card.red .summary-value { color: #b42318; }

        .summary-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
        }

        .table-card {
            margin: 20px 24px 24px;
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }

        .table-head {
            padding: 22px 22px 0;
        }

        .table-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .table-subtitle {
            margin: 0 0 18px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
        }

        .table-wrap {
            padding: 0 22px 22px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1180px;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 18px;
            overflow: hidden;
        }

        th, td {
            text-align: left;
            padding: 15px 14px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
            font-size: 14px;
        }

        th {
            background: #f9fafb;
            font-size: 12px;
            color: #6b7280;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .code-text {
            font-weight: 800;
            color: #374151;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .badge-active {
            background: #e8fff1;
            color: #17663a;
        }

        .badge-inactive {
            background: #ffe8e8;
            color: #9b1c1c;
        }

        .action-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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

        .note {
            margin: 20px 22px 22px;
            background: #fff7ed;
            color: #b45309;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #fed7aa;
            line-height: 1.7;
        }

        @media (max-width: 1280px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 860px) {
            .warehouses-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .warehouses-title {
                font-size: 32px;
            }

            .hero-heading {
                font-size: 28px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .hero-card,
            .helper,
            .table-card {
                margin-left: 18px;
                margin-right: 18px;
            }
        }
    </style>

    <div class="warehouses-shell">
        <div class="warehouses-topbar">
            <div class="warehouses-title-block">

                <h1 class="warehouses-title">Back Office - Warehouses</h1>

            </div>

            <div class="warehouses-actions">
                <a href="{{ route('backoffice.transfers.index') }}" class="btn btn-green">Lihat Transfers</a>
                <a href="{{ route('backoffice.warehouses.create') }}" class="btn btn-primary">Tambah Warehouse</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Dashboard</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">


            <div class="summary-grid">
                <div class="summary-card orange">
                    <div class="summary-label">Total Warehouses</div>
                    <div class="summary-value">{{ $warehouses->count() }}</div>
                    <div class="summary-desc">Jumlah seluruh warehouse yang tersimpan di sistem.</div>
                </div>

                <div class="summary-card green">
                    <div class="summary-label">Active Warehouses</div>
                    <div class="summary-value">{{ $warehouses->where('is_active', true)->count() }}</div>
                    <div class="summary-desc">Warehouse aktif yang siap dipakai untuk flow operasional.</div>
                </div>

                <div class="summary-card blue">
                    <div class="summary-label">Inactive Warehouses</div>
                    <div class="summary-value">{{ $warehouses->where('is_active', false)->count() }}</div>
                    <div class="summary-desc">Warehouse nonaktif yang masih tersimpan sebagai histori data.</div>
                </div>

                <div class="summary-card red">
                    <div class="summary-label">Need Review</div>
                    <div class="summary-value">{{ $warehouses->filter(fn($warehouse) => blank($warehouse->address) || blank($warehouse->phone))->count() }}</div>
                    <div class="summary-desc">Warehouse yang address atau phone-nya masih kosong dan perlu dicek lagi.</div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-head">
                    <h2 class="table-title">All Warehouses</h2>

                </div>

                @if($warehouses->count())
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nama</th>
                                    <th>Kode</th>
                                    <th>Alamat</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($warehouses as $warehouse)
                                    <tr>
                                        <td>{{ $warehouse->id }}</td>
                                        <td>{{ $warehouse->name }}</td>
                                        <td><span class="code-text">{{ $warehouse->code }}</span></td>
                                        <td>{{ $warehouse->address ?: '-' }}</td>
                                        <td>{{ $warehouse->phone ?: '-' }}</td>
                                        <td>
                                            @if($warehouse->is_active)
                                                <span class="badge badge-active">Active</span>
                                            @else
                                                <span class="badge badge-inactive">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="action-group">
                                                <a href="{{ route('backoffice.transfers.create', ['from_location_type' => 'warehouse', 'from_location_id' => $warehouse->id]) }}" class="btn btn-primary">Transfer</a>
                                                <a href="{{ route('backoffice.warehouses.stock.index', $warehouse) }}" class="btn btn-green">Lihat Stock</a>
                                                <a href="{{ route('backoffice.warehouses.edit', $warehouse) }}" class="btn btn-info">Edit</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty">Belum ada warehouse tersimpan.</div>
                @endif
            </div>
        </div>
    </div>
@endsection