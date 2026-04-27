@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Productions - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .productions-shell {
            display: grid;
            gap: 22px;
        }

        .productions-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .productions-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .productions-kicker {
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

        .productions-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .productions-subtitle {
            margin: 0;
            max-width: 820px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .productions-actions {
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

        .btn-green {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
        }

        .btn-blue {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
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
            max-width: 760px;
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
            font-size: 36px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 12px;
        }

        .summary-card.orange .summary-value { color: #c9552a; }
        .summary-card.green .summary-value { color: #166534; }
        .summary-card.blue .summary-value { color: #1d4ed8; }
        .summary-card.violet .summary-value { color: #5b4bd1; }

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
            min-width: 1180px;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 18px;
            overflow: hidden;
        }

        th, td {
            text-align: left;
            padding: 16px 14px;
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
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .badge-output {
            background: #fff7ed;
            color: #c2410c;
        }

        .badge-status {
            background: #e8fff1;
            color: #17663a;
        }

        .btn-small {
            min-height: 38px;
            padding: 0 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            color: white;
            text-decoration: none;
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            box-shadow: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        @media (max-width: 780px) {
            .productions-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .productions-title {
                font-size: 32px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .hero-heading {
                font-size: 28px;
            }

            .hero-card,
            .table-card {
                margin-left: 18px;
                margin-right: 18px;
            }
        }
    </style>

    <div class="productions-shell">
        <div class="productions-topbar">
            <div class="productions-title-block">
                <div class="productions-kicker">Productions Workspace</div>
                <h1 class="productions-title">Back Office - Productions</h1>
                <p class="productions-subtitle">
                    Histori produksi stok bahan mentah menjadi bahan setengah jadi.
                </p>
            </div>

            <div class="productions-actions">
                <a href="{{ route('backoffice.productions.create') }}" class="btn btn-green">Buat Produksi</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Dashboard</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="hero-card">
                <div class="hero-kicker">Productions</div>
                <h2 class="hero-heading">Eksekusi produksi stok raw ke semi-finished dalam satu flow.</h2>
                <p class="hero-text">
                    Saat produksi disimpan, stok bahan mentah akan berkurang, stok output setengah jadi akan bertambah, dan movement produksi akan langsung tercatat.
                </p>
            </div>

            <div class="summary-grid">
                <div class="summary-card orange">
                    <div class="summary-label">Total Productions</div>
                    <div class="summary-value">{{ $productions->count() }}</div>
                    <div class="summary-desc">Jumlah seluruh histori produksi yang tersimpan di sistem.</div>
                </div>

                <div class="summary-card green">
                    <div class="summary-label">Completed Status</div>
                    <div class="summary-value">{{ $productions->where('status', 'completed')->count() }}</div>
                    <div class="summary-desc">Produksi yang sudah selesai dan tercatat lengkap di sistem.</div>
                </div>

                <div class="summary-card blue">
                    <div class="summary-label">Total Batch Qty</div>
                    <div class="summary-value">{{ number_format((float) $productions->sum('batch_qty'), 0, ',', '.') }}</div>
                    <div class="summary-desc">Akumulasi batch produksi dari seluruh histori yang tampil.</div>
                </div>

                <div class="summary-card violet">
                    <div class="summary-label">Total Output Qty</div>
                    <div class="summary-value">{{ number_format((float) $productions->sum('output_qty'), 0, ',', '.') }}</div>
                    <div class="summary-desc">Akumulasi output setengah jadi yang berhasil diproduksi.</div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-head">
                    <h2 class="table-title">Production History</h2>
                    <p class="table-subtitle">
                        Gunakan histori ini untuk audit kapan produksi dijalankan, siapa yang menjalankan, di lokasi mana, dan output apa yang dihasilkan.
                    </p>
                </div>

                @if($productions->count())
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Recipe</th>
                                    <th>Output</th>
                                    <th>Location</th>
                                    <th>Batch Qty</th>
                                    <th>Output Qty</th>
                                    <th>Produced By</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($productions as $production)
                                    <tr>
                                        <td>{{ $production->produced_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td>{{ $production->recipe->name ?? '-' }}</td>
                                        <td>
                                            <span class="badge badge-output">
                                                {{ $production->outputIngredient->name ?? '-' }}
                                            </span>
                                        </td>
                                        <td>{{ ucfirst($production->location_type) }} · {{ $production->location_name }}</td>
                                        <td>{{ number_format((float) $production->batch_qty, 2, ',', '.') }}</td>
                                        <td>{{ number_format((float) $production->output_qty, 2, ',', '.') }} {{ $production->output_unit }}</td>
                                        <td>{{ $production->producedBy->name ?? '-' }}</td>
                                        <td><span class="badge badge-status">{{ ucfirst($production->status ?? 'completed') }}</span></td>
                                        <td>
                                            <a href="{{ route('backoffice.productions.show', $production->id) }}" class="btn-small">Detail</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty">
                        Belum ada histori produksi.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection