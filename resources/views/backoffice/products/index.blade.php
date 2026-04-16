@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Products - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .products-shell {
            display: grid;
            gap: 22px;
        }

        .products-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .products-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .products-kicker {
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

        .products-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .products-subtitle {
            margin: 0;
            max-width: 760px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .products-actions {
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
            background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
        }

        .btn-orange {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
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

        .card-head {
            padding: 24px 24px 0;
        }

        .info-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 16px 18px;
            font-size: 14px;
            line-height: 1.8;
            color: #374151;
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

        .table-wrap {
            padding: 24px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1360px;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 22px;
            overflow: hidden;
        }

        thead th {
            text-align: left;
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 16px 14px;
            background: #f8fafc;
            border-bottom: 1px solid #e8edf4;
            white-space: nowrap;
        }

        tbody td {
            padding: 16px 14px;
            border-bottom: 1px solid #edf1f6;
            vertical-align: top;
            font-size: 14px;
            color: #111827;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .product-name {
            font-weight: 800;
            color: #111827;
            font-size: 15px;
        }

        .code-pill {
            display: inline-flex;
            align-items: center;
            padding: 7px 10px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #374151;
            font-size: 12px;
            font-weight: 800;
        }

        .variant-pill {
            display: inline-flex;
            align-items: center;
            background: #eef2ff;
            color: #3730a3;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            margin: 2px 6px 2px 0;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-active {
            background: #e8fff1;
            color: #17663a;
        }

        .status-inactive {
            background: #fff1f1;
            color: #b42318;
        }

        .action-stack {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            min-width: 150px;
        }

        .btn-small {
            min-height: 34px;
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 10px;
            box-shadow: none;
        }

        .btn-small-blue {
            background: #2563eb;
            color: white;
        }

        .btn-small-red {
            background: #dc2626;
            color: white;
        }

        .inline-form {
            display: inline-block;
            margin: 0;
        }

        .empty {
            margin: 24px;
            padding: 18px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 16px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }

        .note {
            margin: 0 24px 24px;
            background: #eef2ff;
            color: #3730a3;
            padding: 16px 18px;
            border-radius: 16px;
            font-weight: 700;
            border: 1px solid #dbe3ff;
            line-height: 1.7;
        }

        @media (max-width: 1280px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 780px) {
            .products-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .products-title {
                font-size: 32px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .table-wrap,
            .card-head,
            .summary-grid {
                padding-left: 18px;
                padding-right: 18px;
            }

            .note,
            .empty {
                margin-left: 18px;
                margin-right: 18px;
            }
        }
    </style>

    <div class="products-shell">
        <div class="products-topbar">
            <div class="products-title-block">
                <div class="products-kicker">Products Workspace</div>
                <h1 class="products-title">Back Office - Products</h1>
                <p class="products-subtitle">
                    Kelola product utama, category, brand, status aktif, serta akses import dan export CSV dalam satu halaman yang lebih rapi dan konsisten dengan sidebar back office.
                </p>
            </div>

            <div class="products-actions">
                <a href="{{ route('backoffice.products.export.csv') }}" class="btn btn-blue">Export CSV</a>
                <a href="{{ route('backoffice.products.import') }}" class="btn btn-orange">Import CSV</a>
                <a href="{{ route('backoffice.products.create') }}" class="btn btn-green">Tambah Product</a>
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

        @if(session('import_errors') && count(session('import_errors')) > 0)
            <div class="alert alert-error">
                Detail baris yang dilewati:
                <div style="margin-top:10px; font-weight:600;">
                    @foreach(session('import_errors') as $importError)
                        <div style="margin-bottom:6px;">• {{ $importError }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-head">
                <div class="info-box">
                    <strong>User:</strong> {{ $user->name }}<br>
                    <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                    <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card orange">
                    <div class="summary-label">Total Products</div>
                    <div class="summary-value">{{ $products->count() }}</div>
                    <div class="summary-desc">Jumlah seluruh product yang tercatat di sistem back office.</div>
                </div>

                <div class="summary-card green">
                    <div class="summary-label">Active Products</div>
                    <div class="summary-value">{{ $products->where('is_active', true)->count() }}</div>
                    <div class="summary-desc">Product aktif yang masih bisa dipakai untuk operasional dan penjualan.</div>
                </div>

                <div class="summary-card blue">
                    <div class="summary-label">Inactive Products</div>
                    <div class="summary-value">{{ $products->where('is_active', false)->count() }}</div>
                    <div class="summary-desc">Product nonaktif yang masih tersimpan sebagai data historis.</div>
                </div>

                <div class="summary-card violet">
                    <div class="summary-label">Total Variants</div>
                    <div class="summary-value">{{ $products->sum(fn($product) => $product->variants->count()) }}</div>
                    <div class="summary-desc">Jumlah variant yang saat ini menempel ke semua product.</div>
                </div>
            </div>

            @if($products->count())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Brand</th>
                                <th>Category</th>
                                <th>Product</th>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Variants</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $product)
                                <tr>
                                    <td>{{ $product->brand->name ?? '-' }}</td>
                                    <td>{{ $product->category->name ?? '-' }}</td>
                                    <td>
                                        <div class="product-name">{{ $product->name }}</div>
                                    </td>
                                    <td>
                                        <span class="code-pill">{{ $product->code }}</span>
                                    </td>
                                    <td>{{ $product->description ?? '-' }}</td>
                                    <td>
                                        @forelse($product->variants as $variant)
                                            <span class="variant-pill">
                                                {{ $variant->name }} - Rp{{ number_format((float) $variant->price, 0, ',', '.') }}
                                            </span>
                                        @empty
                                            -
                                        @endforelse
                                    </td>
                                    <td>
                                        @if($product->is_active)
                                            <span class="status-badge status-active">Active</span>
                                        @else
                                            <span class="status-badge status-inactive">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="action-stack">
                                            <a href="{{ route('backoffice.products.edit', $product->id) }}" class="btn btn-small btn-small-blue">Edit</a>

                                            <form method="POST" action="{{ route('backoffice.products.destroy', $product->id) }}" class="inline-form" onsubmit="return confirm('Yakin mau hapus product ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-small btn-small-red">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">
                    Belum ada product tersimpan.
                </div>
            @endif

            <div class="note">
                Products sekarang sudah terhubung dengan sidebar back office dan tetap membawa fitur penting seperti tambah product, import CSV, export CSV, edit, dan hapus dengan pengaman jika masih punya variants.
            </div>
        </div>
    </div>
@endsection