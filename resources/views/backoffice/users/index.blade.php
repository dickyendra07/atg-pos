@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Users - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .users-shell {
            display: grid;
            gap: 22px;
        }

        .users-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .users-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .users-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #e3deff;
            color: #5b4bd1;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .users-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .users-subtitle {
            margin: 0;
            max-width: 820px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .users-actions {
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
            background: linear-gradient(135deg, #ffffff 0%, #fbfaff 70%, #f4f3ff 100%);
            border: 1px solid #e3deff;
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
            border: 1px solid #e3deff;
            color: #5b4bd1;
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
            color: #111827;
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
            border-collapse: collapse;
            min-width: 980px;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 18px;
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
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.05em;
        }

        tbody tr:hover {
            background: #fcfcfd;
        }

        .user-name {
            font-weight: 800;
            color: #111827;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .status-active {
            background: #e8fff1;
            color: #17663a;
        }

        .status-inactive {
            background: #fff1f1;
            color: #b42318;
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
            background: #eef2ff;
            color: #3730a3;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #dbe3ff;
            line-height: 1.7;
        }

        @media (max-width: 1280px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 860px) {
            .users-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .users-title {
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

    <div class="users-shell">
        <div class="users-topbar">
            <div class="users-title-block">

                <h1 class="users-title">Back Office - User Management</h1>

            </div>

            <div class="users-actions">
                <a href="{{ route('backoffice.users.create') }}" class="btn btn-green">Tambah User</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Dashboard</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <div class="card">


            <div class="summary-grid">
                <div class="summary-card orange">
                    <div class="summary-label">Total Users</div>
                    <div class="summary-value">{{ $users->count() }}</div>
                    <div class="summary-desc">Total user terdaftar.</div>
                </div>

                <div class="summary-card green">
                    <div class="summary-label">Active Users</div>
                    <div class="summary-value">{{ $users->where('is_active', true)->count() }}</div>
                    <div class="summary-desc">User aktif.</div>
                </div>

                <div class="summary-card blue">
                    <div class="summary-label">Inactive Users</div>
                    <div class="summary-value">{{ $users->where('is_active', false)->count() }}</div>
                    <div class="summary-desc">User nonaktif.</div>
                </div>

                <div class="summary-card violet">
                    <div class="summary-label">With Outlet</div>
                    <div class="summary-value">{{ $users->filter(fn($managedUser) => $managedUser->outlets->count() > 0)->count() }}</div>
                    <div class="summary-desc">User dengan outlet.</div>
                </div>
            </div>

            <div class="table-card">
                <div class="table-head">
                    <h2 class="table-title">All Users</h2>

                </div>

                @if($users->count())
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Outlet</th>
                                    <th>Status</th>
                                    <th>Dibuat</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($users as $managedUser)
                                    <tr>
                                        <td><span class="user-name">{{ $managedUser->name }}</span></td>
                                        <td>{{ $managedUser->email }}</td>
                                        <td>{{ $managedUser->phone ?? '-' }}</td>
                                        <td>{{ $managedUser->role->name ?? '-' }}</td>
                                        <td>@if($managedUser->outlets->count())
                                                {{ $managedUser->outlets->pluck('name')->implode(', ') }}
                                            @else
                                                Semua Outlet
                                            @endif</td>
                                        <td>
                                            @if($managedUser->is_active)
                                                <span class="status-badge status-active">Active</span>
                                            @else
                                                <span class="status-badge status-inactive">Inactive</span>
                                            @endif
                                        </td>
                                        <td>{{ $managedUser->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                        <td>
                                            <a href="{{ route('backoffice.users.edit', $managedUser->id) }}" class="btn btn-blue">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty">
                        Belum ada user.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection