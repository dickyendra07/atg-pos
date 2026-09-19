@extends('backoffice.layouts.app')

@php($pageTitle = 'Detail Adjustment - ATG POS')

@section('content')
<style>.detail{display:grid;gap:18px}.card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:20px}.meta{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.meta div{display:grid;gap:4px}.meta span{font-size:12px;color:#64748b;font-weight:800}.wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:13px;border-bottom:1px solid #e2e8f0;text-align:left}a{color:#ea580c}.up{color:#15803d;font-weight:800}.down{color:#b91c1c;font-weight:800}@media(max-width:700px){.meta{grid-template-columns:1fr}}</style>
@php($fmt = fn ($v) => \App\Support\QuantityFormatter::twoDecimals($v))
<div class="detail">
    <div><a href="{{ route('backoffice.stock-adjustments.index') }}">← Adjustment History</a><h1>Detail Adjustment</h1></div>

    @if(session('success'))<div class="card" style="background:#ecfdf5;border-color:#a7f3d0;color:#065f46;font-weight:700">{{ session('success') }}</div>@endif

    <div class="card meta">
        <div><span>Reference</span><strong>{{ $adjustment->reference }}</strong></div>
        <div><span>Date</span><strong>{{ $adjustment->created_at->format('Y-m-d H:i') }}</strong></div>
        <div><span>Outlet / Location</span><strong>{{ $adjustment->locationName() }}</strong></div>
        <div><span>User</span><strong>{{ $adjustment->user?->name ?? '-' }}</strong></div>
        <div><span>Total Items</span><strong>{{ $adjustment->items->count() }}</strong></div>
        <div><span>Note</span><strong>{{ $adjustment->note ?: '-' }}</strong></div>
    </div>

    <div class="card wrap">
        <table>
            <thead><tr><th>Ingredient</th><th>Category</th><th>Unit</th><th>Stock System Before</th><th>Stock Actual</th><th>Difference</th><th>Movement</th></tr></thead>
            <tbody>
            @foreach($adjustment->items as $item)
                <tr>
                    <td>{{ $item->ingredient?->name ?? '-' }}</td>
                    <td>{{ $item->ingredient?->category?->name ?? '-' }}</td>
                    <td>{{ $item->unit ?: ($item->ingredient?->unit ?? '-') }}</td>
                    <td>{{ $fmt($item->system_qty) }}</td>
                    <td>{{ $fmt($item->actual_qty) }}</td>
                    <td class="{{ $item->difference > 0 ? 'up' : ($item->difference < 0 ? 'down' : '') }}">{{ $item->difference > 0 ? '+' : '' }}{{ $fmt($item->difference) }}</td>
                    <td>{{ $item->stock_movement_id ? 'MOV-'.$item->stock_movement_id : 'Tidak ada perubahan' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
