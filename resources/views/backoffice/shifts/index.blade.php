@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Cashier Shifts - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .shifts-shell {
            display: grid;
            gap: 22px;
        }

        .shifts-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .shifts-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .shifts-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #dbe3ff;
            color: #3730a3;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .shifts-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .shifts-subtitle {
            margin: 0;
            max-width: 820px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .shifts-actions {
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
            background: linear-gradient(135deg, #ffffff 0%, #f8f7ff 70%, #eef2ff 100%);
            border: 1px solid #dbe3ff;
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
            background: radial-gradient(circle, rgba(91,75,209,0.14) 0%, rgba(91,75,209,0.03) 65%, rgba(91,75,209,0) 80%);
            pointer-events: none;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #dbe3ff;
            color: #3730a3;
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

        .filter-card {
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
            grid-template-columns: repeat(5, minmax(0, 1fr));
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
            border-color: rgba(91,75,209,0.55);
            box-shadow: 0 0 0 4px rgba(91,75,209,0.10);
        }

        .filter-actions {
            padding: 0 22px 22px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 16px;
        }

        .summary-wrap {
            margin: 20px 24px 0;
        }

        .summary-box {
            border-radius: 22px;
            padding: 20px;
            border: 1px solid #e8edf4;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            min-height: 140px;
            background: rgba(255,255,255,0.92);
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
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 16px;
        }

        .summary-value {
            font-size: 34px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 12px;
            color: #111827;
            word-break: break-word;
        }

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

        .table-wrap {
            padding: 24px 22px 22px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1300px;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e8edf4;
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
            padding: 28px 22px 30px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.8;
        }

        @media (max-width: 1280px) {
            .filter-grid,
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 780px) {
            .shifts-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .shifts-title {
                font-size: 32px;
            }

            .hero-heading {
                font-size: 28px;
            }

            .filter-grid,
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .hero-card,
            .filter-card,
            .summary-wrap,
            .table-card {
                margin-left: 18px;
                margin-right: 18px;
            }
        }
    </style>

    <div class="shifts-shell">
        <div class="shifts-topbar">
            <div class="shifts-title-block">

                <h1 class="shifts-title">Back Office - Cashier Shifts</h1>

            </div>

            <div class="shifts-actions">
                <a href="{{ route('backoffice.transactions.index') }}" class="btn btn-green">Transactions</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Dashboard</a>
            </div>
        </div>

        <div class="card">


            <div class="filter-card">
                <div class="section-head">
                    <h2 class="section-title">Filter Shifts</h2>

                </div>

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

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-green">Apply Filter</button>
                        <a href="{{ route('backoffice.shifts.index') }}" class="btn btn-dark">Reset</a>
                    </div>
                </form>
            </div>

            <div class="summary-wrap">
                <div class="summary-grid">
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
            </div>

            <div class="table-card">
                <div class="section-head">
                    <h2 class="section-title">Shift List</h2>

                </div>

                @if($shiftRows->isEmpty())
                    <div class="empty-box">
                        Belum ada data shift yang cocok dengan filter.
                    </div>
                @else
                    <div class="table-wrap">
                        <table class="table-center">
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
                                        <td>{{ $shift->started_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td>{{ $shift->ended_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td><strong>{{ $shift->user->name ?? '-' }}</strong></td>
                                        <td>{{ $shift->user->role->name ?? '-' }}</td>
                                        <td>{{ $shift->outlet->name ?? '-' }}</td>
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
                                            <a href="{{ route('backoffice.shifts.show', $shift->id) }}" class="btn btn-dark">Detail</a>
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