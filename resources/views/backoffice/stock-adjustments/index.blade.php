@extends('backoffice.layouts.app')

@php($pageTitle = 'Adjustment History - ATG POS')

@section('content')
<style>
    .adj-page{display:grid;gap:18px}.adj-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:20px}
    .adj-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}.adj-head h1{margin:0 0 6px}
    .filters{display:grid;grid-template-columns:repeat(4,minmax(140px,1fr));gap:12px}.field{display:grid;gap:6px}.field label{font-size:12px;font-weight:800;color:#64748b}.field input,.field select{border:1px solid #cbd5e1;border-radius:10px;padding:10px;background:#fff}
    .btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:12px;padding:10px 16px;background:#ea580c;color:#fff;font-weight:800;text-decoration:none;cursor:pointer}
    .table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:13px;border-bottom:1px solid #e2e8f0;text-align:left}a{color:#ea580c}
    @media(max-width:900px){.filters{grid-template-columns:1fr 1fr}}@media(max-width:560px){.filters{grid-template-columns:1fr}}
</style>
<div class="adj-page">
    <div class="adj-head">
        <div><h1>Adjustment History</h1><div style="color:#64748b">Riwayat koreksi stok untuk Active Outlet Backoffice yang sedang dipilih di kanan atas.</div></div>
        <a class="btn" href="{{ route('backoffice.stock-balances.adjustment.create') }}">Buat Adjustment</a>
    </div>

    <div class="adj-card">
        {{-- Location/outlet is driven only by the global Active Outlet selector in the top bar;
             no separate location dropdown here, so there is a single source of outlet context. --}}
        <form class="filters" method="GET">
            <div class="field"><label>Date From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="field"><label>Date To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="field"><label>User</label><select name="user_id"><option value="">Semua user</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div class="field"><label>Search reference / note</label><input name="search" value="{{ $filters['search'] ?? '' }}"><button class="btn" style="margin-top:8px">Apply</button></div>
        </form>
    </div>

    <div class="adj-card table-wrap">
        <table>
            <thead><tr><th>Reference</th><th>Date</th><th>Outlet / Location</th><th>User</th><th>Total Items</th><th>Note</th></tr></thead>
            <tbody>
            @forelse($adjustments as $adjustment)
                <tr>
                    <td><a href="{{ route('backoffice.stock-adjustments.show', $adjustment) }}">{{ $adjustment->reference }}</a></td>
                    <td>{{ $adjustment->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $adjustment->locationName() }}</td>
                    <td>{{ $adjustment->user?->name ?? '-' }}</td>
                    <td>{{ $adjustment->items->count() }}</td>
                    <td>{{ $adjustment->note ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#64748b;padding:32px">Belum ada adjustment untuk filter ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
