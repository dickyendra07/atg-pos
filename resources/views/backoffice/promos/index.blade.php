@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Promos - Back Office ATG POS';

    $variantLabel = function ($variant) {
        if (! $variant) {
            return '-';
        }

        $productName = $variant->product->name ?? 'Product';
        $variantName = $variant->name ?? 'Variant';

        return $productName . ' - ' . $variantName;
    };

    $rewardLabel = function ($reward) use ($variantLabel) {
        if ($reward->reward_type === 'discount_percent') {
            return number_format((float) $reward->reward_value, 0, ',', '.') . '% Discount';
        }

        if ($reward->reward_type === 'discount_amount') {
            return 'Rp ' . number_format((float) $reward->reward_value, 0, ',', '.') . ' Discount';
        }

        if ($reward->reward_type === 'free_item') {
            return 'Free ' . number_format((float) $reward->qty, 0, ',', '.') . 'x ' . $variantLabel($reward->variant);
        }

        return '-';
    };
@endphp

@section('content')
    <style>
        .page-shell { display: grid; gap: 22px; }
        .topbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
        .kicker {
            display: inline-flex; align-items: center; padding: 8px 12px; border-radius: 999px;
            background: rgba(255,255,255,0.88); border: 1px solid #f1e3da; color: #c9552a;
            font-size: 12px; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; margin-bottom: 14px;
        }
        .page-title { margin: 0 0 10px; font-size: 38px; line-height: 1; font-weight: 800; letter-spacing: -0.04em; color: #111827; }
        .page-subtitle { margin: 0; max-width: 920px; color: #6b7280; font-size: 15px; line-height: 1.9; }
        .btn {
            border: 0; cursor: pointer; min-height: 42px; padding: 0 16px; border-radius: 14px; color: white;
            font-size: 13px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center;
            justify-content: center; box-shadow: 0 10px 20px rgba(15,23,42,0.10);
        }
        .btn-brand { background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%); }
        .btn-dark { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); }
        .btn-red { background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%); }
        .btn-soft { background: #f3f4f6; color: #374151; box-shadow: none; }
        .card {
            background: rgba(255,255,255,0.92); border: 1px solid #e8edf4; border-radius: 28px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08); padding: 22px;
        }
        .alert { border-radius: 18px; padding: 16px 18px; font-size: 14px; font-weight: 700; line-height: 1.7; }
        .alert-success { background: #e8fff1; color: #17663a; border: 1px solid #ccefd8; }
        .filter-grid { display: grid; grid-template-columns: 1.4fr 1fr 1fr auto; gap: 14px; align-items: end; }
        .field label {
            display: block; font-size: 12px; font-weight: 800; color: #6b7280; margin-bottom: 8px;
            text-transform: uppercase; letter-spacing: 0.05em;
        }
        .field input, .field select {
            width: 100%; min-height: 48px; border: 1px solid #d7dce5; border-radius: 14px; background: white;
            padding: 0 14px; font-size: 14px; color: #111827; outline: none; box-sizing: border-box;
        }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; min-width: 1180px; border-collapse: collapse; }
        th {
            text-align: center; font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;
            padding: 14px 12px; background: #f8fafc; border-bottom: 1px solid #e8edf4; white-space: nowrap;
        }
        td {
            padding: 14px 12px; border-bottom: 1px solid #edf1f6; vertical-align: middle; font-size: 14px;
            color: #111827; text-align: center;
        }
        tr:last-child td { border-bottom: 0; }
        .name { font-weight: 800; text-align: left; }
        .rule-box {
            display: grid; gap: 8px; text-align: left; line-height: 1.5; max-width: 320px; margin: 0 auto;
        }
        .rule-item {
            padding: 9px 10px; border-radius: 12px; background: #f8fafc; border: 1px solid #edf1f6;
            font-size: 13px; font-weight: 800;
        }
        .rule-label {
            font-size: 11px; font-weight: 800; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;
        }
        .badge {
            display: inline-flex; align-items: center; justify-content: center; padding: 7px 10px; border-radius: 999px;
            font-size: 11px; font-weight: 800; text-transform: uppercase;
        }
        .badge-active { background: #e8fff1; color: #166534; border: 1px solid #ccefd8; }
        .badge-draft { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
        .badge-discontinued { background: #fff1f1; color: #b91c1c; border: 1px solid #fecaca; }
        .actions { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
        .empty { padding: 22px; text-align: center; color: #6b7280; font-weight: 700; }
        .days-list { display: flex; justify-content: center; gap: 6px; flex-wrap: wrap; }
        .day-pill {
            display: inline-flex; padding: 5px 8px; border-radius: 999px; background: #eef2ff;
            color: #3730a3; font-size: 11px; font-weight: 800;
        }
        @media (max-width: 1100px) { .filter-grid { grid-template-columns: 1fr 1fr; } }
    </style>

    <div class="page-shell">
        <div class="topbar">
            <div>
                <div class="kicker">Backoffice Promos</div>
                <h1 class="page-title">Promos</h1>
                <p class="page-subtitle">
                    Master promo untuk campaign bulanan, promo khusus product, purchase requirements, rewards, periode promo, jam aktif, hari aktif, dan outlet.
                </p>
            </div>

            <a href="{{ route('backoffice.promos.create') }}" class="btn btn-brand">Create Promo</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <form method="GET" action="{{ route('backoffice.promos.index') }}" class="filter-grid">
                <div class="field">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" value="{{ $filters['search'] }}" placeholder="Cari nama promo">
                </div>

                <div class="field">
                    <label for="outlet_id">Outlet</label>
                    <select name="outlet_id" id="outlet_id">
                        <option value="">Semua Outlet</option>
                        @foreach($outletOptions as $outlet)
                            <option value="{{ $outlet->id }}" @selected((string) $filters['outlet_id'] === (string) $outlet->id)>
                                {{ $outlet->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">Semua Status</option>
                        <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                        <option value="active" @selected($filters['status'] === 'active')>Active</option>
                        <option value="discontinued" @selected($filters['status'] === 'discontinued')>Discontinued</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-dark">Filter</button>
                    <a href="{{ route('backoffice.promos.index') }}" class="btn btn-soft">Reset</a>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Promo Name</th>
                            <th>Outlet</th>
                            <th>Purchase Requirements</th>
                            <th>Rewards</th>
                            <th>Time Period</th>
                            <th>Hour</th>
                            <th>Active Days</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($promos as $promo)
                            @php
                                $activeDays = collect($promo->active_days ?? [])
                                    ->map(fn ($day) => $dayOptions[$day] ?? $day)
                                    ->values();
                            @endphp
                            <tr>
                                <td class="name">{{ $promo->name }}</td>
                                <td>{{ $promo->outlet->name ?? 'All Outlets' }}</td>
                                <td>
                                    <div class="rule-box">
                                        <div class="rule-label">Buy</div>
                                        @forelse($promo->requirements as $requirement)
                                            <div class="rule-item">
                                                {{ number_format((float) $requirement->qty, 0, ',', '.') }}x
                                                {{ $variantLabel($requirement->variant) }}
                                            </div>
                                        @empty
                                            <div class="rule-item">-</div>
                                        @endforelse
                                    </div>
                                </td>
                                <td>
                                    <div class="rule-box">
                                        <div class="rule-label">Get</div>
                                        @forelse($promo->rewards as $reward)
                                            <div class="rule-item">{{ $rewardLabel($reward) }}</div>
                                        @empty
                                            <div class="rule-item">-</div>
                                        @endforelse
                                    </div>
                                </td>
                                <td>
                                    {{ $promo->start_date?->format('d M Y') ?? '-' }}
                                    -
                                    {{ $promo->end_date?->format('d M Y') ?? '-' }}
                                </td>
                                <td>
                                    {{ $promo->start_time ? substr($promo->start_time, 0, 5) : '-' }}
                                    -
                                    {{ $promo->end_time ? substr($promo->end_time, 0, 5) : '-' }}
                                </td>
                                <td>
                                    @if($activeDays->isEmpty())
                                        Every day
                                    @else
                                        <div class="days-list">
                                            @foreach($activeDays as $day)
                                                <span class="day-pill">{{ $day }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($promo->status === 'active')
                                        <span class="badge badge-active">Active</span>
                                    @elseif($promo->status === 'discontinued')
                                        <span class="badge badge-discontinued">Discontinued</span>
                                    @else
                                        <span class="badge badge-draft">Draft</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="{{ route('backoffice.promos.edit', $promo) }}" class="btn btn-dark">Edit</a>
                                        <form method="POST" action="{{ route('backoffice.promos.destroy', $promo) }}" onsubmit="return confirm('Hapus promo ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-red">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="empty">Belum ada promo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
