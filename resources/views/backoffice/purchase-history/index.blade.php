@extends('backoffice.layouts.app')

@php($pageTitle = 'Purchase History - ATG POS')

@section('content')
<style>
    .purchase-shell{display:grid;gap:18px}.purchase-head{display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap}
    .purchase-head h1{margin:0;font-size:34px;color:#111827}.purchase-head p{margin:6px 0 0;color:#64748b}.purchase-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:18px;box-shadow:0 10px 28px rgba(15,23,42,.05)}
    .filters{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:12px}.field{display:grid;gap:6px}.field label{font-size:12px;font-weight:800;color:#64748b}.field input,.field select{border:1px solid #cbd5e1;border-radius:10px;padding:10px;background:#fff}
    .btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:10px;padding:11px 15px;background:#f97316;color:#fff;text-decoration:none;font-weight:800;cursor:pointer}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:13px;border-bottom:1px solid #e2e8f0;text-align:left;white-space:nowrap}th{font-size:12px;color:#64748b}.badge{padding:5px 9px;border-radius:999px;background:#dcfce7;color:#166534;font-weight:800;font-size:12px}
    @media(max-width:900px){.filters{grid-template-columns:1fr 1fr}}@media(max-width:560px){.filters{grid-template-columns:1fr}}
</style>
<div class="purchase-shell">
    <div class="purchase-head"><div><h1>Purchase / Goods Receipt History</h1><p>Riwayat penerimaan barang yang benar-benar tersimpan.</p></div><a class="btn" href="{{ route('backoffice.stock-balances.create') }}">Penerimaan Baru</a></div>
    <div class="purchase-card">
        <form class="filters" method="GET">
            <div class="field"><label>Outlet / Location</label><select name="outlet_id"><option value="all">Semua lokasi</option><option value="warehouse" @selected(($filters['outlet_id']??'')==='warehouse')>Semua Warehouse</option>@foreach($backofficeOutletOptions as $outlet)<option value="{{ $outlet->id }}" @selected((string)($filters['outlet_id']??$activeBackofficeOutlet?->id)===(string)$outlet->id)>{{ $outlet->name }}</option>@endforeach</select></div>
            <div class="field"><label>Date From</label><input type="date" name="date_from" value="{{ $filters['date_from']??'' }}"></div>
            <div class="field"><label>Date To</label><input type="date" name="date_to" value="{{ $filters['date_to']??'' }}"></div>
            <div class="field"><label>Status</label><select name="status"><option value="">Semua status</option><option value="received" @selected(($filters['status']??'')==='received')>Received</option></select></div>
            <div class="field"><label>Search reference / supplier</label><input name="search" value="{{ $filters['search']??'' }}"><button class="btn" style="margin-top:8px">Apply</button></div>
        </form>
    </div>
    <div class="purchase-card table-wrap"><table><thead><tr><th>Reference</th><th>Date</th><th>Supplier</th><th>Destination</th><th>Total</th><th>Status</th></tr></thead><tbody>
        @forelse($receipts as $receipt)<tr><td><a href="{{ route('backoffice.purchase-history.show',$receipt) }}">{{ $receipt->reference_number }}</a></td><td>{{ $receipt->received_date->format('Y-m-d') }}</td><td>{{ $receipt->supplier_name ?: '-' }}</td><td>{{ $receipt->destinationName() }}</td><td>Rp {{ number_format((float)$receipt->total_amount,2,',','.') }}</td><td><span class="badge">{{ ucfirst($receipt->status) }}</span></td></tr>
        @empty<tr><td colspan="6" style="text-align:center;color:#64748b;padding:32px">Belum ada penerimaan untuk filter ini.</td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection
