@extends('backoffice.layouts.app')

@php($pageTitle = $cfg['title'].' - ATG POS')

@section('content')
<style>
    .cat-page{display:grid;gap:18px}.cat-card{background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:20px}
    .cat-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap}.cat-head h1{margin:0 0 6px}
    .cat-search{display:flex;gap:10px;flex-wrap:wrap}.cat-search input{flex:1;min-width:200px;border:1px solid #cbd5e1;border-radius:10px;padding:10px}
    .btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:12px;padding:10px 16px;background:#ea580c;color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.btn-dark{background:#111827}
    .btn-small{padding:8px 14px;font-size:13px;border-radius:10px}
    .btn-danger{background:linear-gradient(135deg,#dc2626 0%,#ef4444 100%)}
    .delete-form{display:inline-block;margin:0}
    .action-row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:13px;border-bottom:1px solid #e2e8f0;text-align:left}a.link{color:#ea580c;font-weight:700}
    .badge{display:inline-block;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:800}.badge-on{background:#dcfce7;color:#166534}.badge-off{background:#f1f5f9;color:#64748b}
</style>
<div class="cat-page">
    <div class="cat-head">
        <div><h1>{{ $cfg['title'] }}</h1><div style="color:#64748b">{{ $cfg['kicker'] }} — master data dipakai bersama oleh semua outlet. Ketersediaan item tetap diatur per outlet.</div></div>
        <a class="btn" href="{{ route($cfg['route'].'.create') }}">Tambah Category</a>
    </div>

    @if(session('success'))<div class="cat-card" style="background:#ecfdf5;border-color:#a7f3d0;color:#065f46;font-weight:700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="cat-card" style="background:#ffe8e8;border-color:#fecaca;color:#9b1c1c;font-weight:700">{{ session('error') }}</div>@endif

    <div class="cat-card">
        <form method="GET" class="cat-search">
            <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama category">
            <button class="btn">Cari</button>
            <a class="btn btn-dark" href="{{ route($cfg['route'].'.index') }}">Reset</a>
        </form>
    </div>

    <div class="cat-card table-wrap">
        <table>
            <thead><tr><th>Nama</th><th>Kode</th>@if($cfg['needs_brand'])<th>Brand</th>@endif<th>{{ $cfg['usage_label'] }}</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($categories as $category)
                <tr>
                    <td><strong>{{ $category->name }}</strong></td>
                    <td>{{ $category->code }}</td>
                    @if($cfg['needs_brand'])<td>{{ $category->brand?->name ?? '-' }}</td>@endif
                    <td>{{ $category->{$usageCountKey} }}</td>
                    <td><span class="badge {{ $category->is_active ? 'badge-on' : 'badge-off' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td>
                        <div class="action-row">
                            <a class="link" href="{{ route($cfg['route'].'.edit', $category->id) }}">Edit</a>
                            @if($cfg['deletable'] ?? false)
                                <form method="POST" action="{{ route($cfg['route'].'.destroy', $category->id) }}" class="delete-form" onsubmit="return confirm('Yakin hapus category ini? Tindakan ini tidak bisa dibatalkan.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-small">Hapus</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#64748b;padding:32px">Belum ada category.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
