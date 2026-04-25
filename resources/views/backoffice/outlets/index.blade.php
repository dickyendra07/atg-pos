@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Outlets - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .outlets-shell {
            display: grid;
            gap: 22px;
        }

        .outlets-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .outlets-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .outlets-kicker {
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

        .outlets-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .outlets-subtitle {
            margin: 0;
            max-width: 860px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .outlets-actions {
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

        .btn-primary {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        }

        .btn-dark {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .btn-info {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
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
            background: linear-gradient(135deg, #ffffff 0%, #fff9f5 70%, #fff1ea 100%);
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
            max-width: 780px;
            position: relative;
            z-index: 1;
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
            background: white;
            min-width: 1080px;
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

        .action-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .delete-form {
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

        @media (max-width: 1280px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 860px) {
            .outlets-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .outlets-title {
                font-size: 32px;
            }

            .hero-heading {
                font-size: 28px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .hero-card,
            .table-card {
                margin-left: 18px;
                margin-right: 18px;
            }
        }
    </style>

    <div class="outlets-shell">
        <div class="outlets-topbar">
            <div class="outlets-title-block">
                <div class="outlets-kicker">Outlets Workspace</div>
                <h1 class="outlets-title">Back Office - Outlets</h1>
                <p class="outlets-subtitle">
                    Kelola outlet aktif sebagai lokasi operasional penjualan dan inventory dengan tampilan yang sekarang sudah konsisten dengan sidebar back office.
                </p>
            </div>

            <div class="outlets-actions">
                <a href="{{ route('backoffice.outlets.create') }}" class="btn btn-primary">Tambah Outlet</a>
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
                <div class="hero-kicker">Outlet Master</div>
                <h2 class="hero-heading">Kelola lokasi outlet operasional dalam satu halaman yang lebih rapi.</h2>
                <p class="hero-text">
                    Data outlet di halaman ini dipakai untuk cashier, stock movement, transfer antar lokasi, dan referensi operasional outlet.
                </p>
            </div>

            <div class="summary-grid">
                <div class="summary-card orange">
                    <div class="summary-label">Total Outlets</div>
                    <div class="summary-value">{{ $outlets->count() }}</div>
                    <div class="summary-desc">Jumlah seluruh outlet yang tersimpan di sistem.</div>
                </div>

                <div class="summary-card green">
                    <div class="summary-label">Active Outlets</div>
                    <div class="summary-value">{{ $outlets->where('is_active', true)->count() }}</div>
                    <div class="summary-desc">Outlet aktif yang siap dipakai untuk operasional harian.</div>
                </div>

                <div class="summary-card blue">
                    <div class="summary-label">Inactive Outlets</div>
                    <div class="summary-value">{{ $outlets->where('is_active', false)->count() }}</div>
                    <div class="summary-desc">Outlet nonaktif yang masih tersimpan sebagai data historis.</div>
                </div>

                <div class="summary-card red">
                    <div class="summary-label">Need Review</div>
                    <div class="summary-value">{{ $outlets->filter(fn($outlet) => blank($outlet->address) || blank($outlet->phone))->count() }}</div>
                    <div class="summary-desc">Outlet yang address atau phone-nya masih kosong dan perlu dicek lagi.</div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-head">
                    <h2 class="table-title">All Outlets</h2>
                    <p class="table-subtitle">
                        Lihat nama outlet, kode, alamat, phone, dan status aktif dalam satu tabel yang lebih nyaman dibaca.
                    </p>
                </div>

                @if($outlets->count())
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama Outlet</th>
                                    <th>Kode</th>
                                    <th>Alamat</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($outlets as $outlet)
                                    <tr>
                                        <td>{{ $outlet->name }}</td>
                                        <td><span class="code-text">{{ $outlet->code }}</span></td>
                                        <td>{{ $outlet->address ?? '-' }}</td>
                                        <td>{{ $outlet->phone ?? '-' }}</td>
                                        <td>
                                            @if($outlet->is_active)
                                                <span class="badge badge-active">Active</span>
                                            @else
                                                <span class="badge badge-inactive">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="action-row">
                                                <a href="{{ route('backoffice.outlets.edit', $outlet) }}" class="btn btn-info">Edit</a>

                                                <form method="POST" action="{{ route('backoffice.outlets.destroy', $outlet) }}" class="delete-form" onsubmit="return confirm('Yakin hapus outlet ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger">Hapus</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty">Belum ada outlet tersimpan.</div>
                @endif
            </div>
        </div>
    </div>
@endsection