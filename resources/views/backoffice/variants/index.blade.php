@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Variants - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .variants-shell {
            display: grid;
            gap: 22px;
        }

        .variants-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .variants-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .variants-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #e8ddff;
            color: #5b4bd1;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            width: fit-content;
        }

        .variants-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .variants-subtitle {
            margin: 0;
            max-width: 820px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .variants-actions {
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

        .btn-small {
            min-height: 34px;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 12px;
            box-shadow: none;
        }

        .btn-small-red {
            background: #dc2626;
            color: white;
        }

        .btn-toggle {
            background: linear-gradient(135deg, #0f172a 0%, #334155 100%);
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

        .summary-grid {
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

        .search-card {
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 24px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            padding: 18px;
        }

        .search-form {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 12px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .field input {
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

        .field input:focus {
            border-color: rgba(91,75,209,0.65);
            box-shadow: 0 0 0 4px rgba(91,75,209,0.10);
        }

        .group-list {
            display: grid;
            gap: 14px;
        }

        .group-card {
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 24px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }

        .group-head {
            padding: 16px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .group-title {
            margin: 0 0 4px;
            font-size: 18px;
            font-weight: 800;
            color: #111827;
        }

        .group-meta {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
        }

        .group-meta-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
        }

        .group-meta-inline span {
            white-space: nowrap;
        }

        .group-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .group-body {
            padding: 0 18px 18px;
            overflow-x: auto;
            display: none;
        }

        .group-card.is-open .group-body {
            display: block;
        }

        .group-card.is-open .group-head {
            padding-bottom: 12px;
        }

        table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 18px;
            overflow: hidden;
        }

        thead th {
            text-align: left;
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 14px 12px;
            background: #f8fafc;
            border-bottom: 1px solid #e8edf4;
            white-space: nowrap;
        }

        tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid #edf1f6;
            vertical-align: top;
            font-size: 14px;
            color: #111827;
        }

        tbody tr:last-child td {
            border-bottom: 0;
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

        .price-text {
            font-weight: 800;
            color: #1d4ed8;
            white-space: nowrap;
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

        .inline-form {
            display: inline-block;
            margin: 0;
        }

        .empty {
            background: #fff7ed;
            color: #9a3412;
            border-radius: 18px;
            padding: 18px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }

        @media (max-width: 1280px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 900px) {
            .search-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 780px) {
            .variants-topbar,
            .group-head {
                flex-direction: column;
                align-items: flex-start;
            }

            .variants-title {
                font-size: 32px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .group-body,
            .group-head {
                padding-left: 16px;
                padding-right: 16px;
            }
        }
    </style>

    @php
        $search = trim((string) request('search'));

        $filteredGroups = $groupedProducts->filter(function ($group) use ($search) {
            if ($search === '') {
                return true;
            }

            $keyword = mb_strtolower($search);

            $productName = mb_strtolower((string) ($group['product']->name ?? ''));
            $brandName = mb_strtolower((string) ($group['product']->brand->name ?? ''));
            $categoryName = mb_strtolower((string) ($group['product']->category->name ?? ''));

            if (
                str_contains($productName, $keyword) ||
                str_contains($brandName, $keyword) ||
                str_contains($categoryName, $keyword)
            ) {
                return true;
            }

            foreach ($group['variants'] as $variant) {
                $variantName = mb_strtolower((string) ($variant->name ?? ''));
                $variantCode = mb_strtolower((string) ($variant->code ?? ''));

                if (
                    str_contains($variantName, $keyword) ||
                    str_contains($variantCode, $keyword)
                ) {
                    return true;
                }
            }

            return false;
        })->values();
    @endphp

    <div class="variants-shell">
        <div class="variants-topbar">
            <div class="variants-title-block">

                <h1 class="variants-title">Variants Group Management</h1>

            </div>

            <div class="variants-actions">
                <a href="{{ route('backoffice.variants.export.csv') }}" class="btn btn-blue">Export CSV</a>
                <a href="{{ route('backoffice.variants.import') }}" class="btn btn-orange">Import CSV</a>
                <a href="{{ route('backoffice.variants.create') }}" class="btn btn-green">Tambah Multi Variant</a>
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

        <div class="summary-grid">
            <div class="summary-card orange">
                <div class="summary-label">Total Product Group</div>
                <div class="summary-value">{{ $groupedProducts->count() }}</div>
                <div class="summary-desc">Jumlah product yang sudah punya group variant.</div>
            </div>

            <div class="summary-card green">
                <div class="summary-label">Total Variant Rows</div>
                <div class="summary-value">{{ $variants->count() }}</div>
                <div class="summary-desc">Semua row variant aktif dan non aktif yang tersimpan.</div>
            </div>

            <div class="summary-card blue">
                <div class="summary-label">Dine In Ready</div>
                <div class="summary-value">{{ $variants->filter(fn($v) => !is_null($v->price_dine_in))->count() }}</div>
                <div class="summary-desc">Variant yang sudah punya harga dine in.</div>
            </div>

            <div class="summary-card violet">
                <div class="summary-label">Delivery Ready</div>
                <div class="summary-value">{{ $variants->filter(fn($v) => !is_null($v->price_delivery))->count() }}</div>
                <div class="summary-desc">Variant yang sudah punya harga delivery.</div>
            </div>
        </div>

        <div class="search-card">
            <form method="GET" action="{{ route('backoffice.variants.index') }}" class="search-form">
                <div class="field">
                    <label for="search">Search Product / Brand / Category / Variant</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Contoh: Black Tea / Hazelnut / Regular / Lee Ong's Tea"
                    >
                </div>

                <button type="submit" class="btn btn-blue">Cari</button>
                <a href="{{ route('backoffice.variants.index') }}" class="btn btn-dark">Reset</a>
            </form>
        </div>

        @if($filteredGroups->count())
            <div class="group-list">
                @foreach($filteredGroups as $groupIndex => $group)
                    @php
                        $isOpen = $search !== '' || $groupIndex === 0;
                    @endphp

                    <div class="group-card {{ $isOpen ? 'is-open' : '' }}" data-group-card>
                        <div class="group-head">
                            <div>
                                <h2 class="group-title">{{ $group['product']->name ?? '-' }}</h2>
                                <div class="group-meta group-meta-inline">
                                    <span>Brand: {{ $group['product']->brand->name ?? '-' }}</span>
                                    <span>Category: {{ $group['product']->category->name ?? '-' }}</span>
                                    <span>Total Variant: {{ $group['variants']->count() }}</span>
                                    <span>Active: {{ $group['active_count'] }}</span>
                                </div>
                            </div>

                            <div class="group-actions">
                                <button type="button" class="btn btn-small btn-toggle" data-toggle-group>
                                    {{ $isOpen ? 'Tutup' : 'Variant' }}
                                </button>

                                <a href="{{ route('backoffice.variants.edit', $group['first_variant_id']) }}" class="btn btn-small btn-blue">
                                    Edit
                                </a>
                            </div>
                        </div>

                        <div class="group-body">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Variant</th>
                                        <th>Code</th>
                                        <th>Price Dine In</th>
                                        <th>Price Delivery</th>
                                        <th>Status</th>
                                        <th>Delete Single</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($group['variants'] as $variant)
                                        <tr>
                                            <td>{{ $variant->name }}</td>
                                            <td>
                                                <span class="code-pill">{{ $variant->code }}</span>
                                            </td>
                                            <td class="price-text">
                                                Rp {{ number_format((float) ($variant->price_dine_in ?? $variant->price ?? 0), 0, ',', '.') }}
                                            </td>
                                            <td class="price-text">
                                                Rp {{ number_format((float) ($variant->price_delivery ?? $variant->price ?? 0), 0, ',', '.') }}
                                            </td>
                                            <td>
                                                @if($variant->is_active)
                                                    <span class="status-badge status-active">Active</span>
                                                @else
                                                    <span class="status-badge status-inactive">Inactive</span>
                                                @endif
                                            </td>
                                            <td>
                                                <form method="POST" action="{{ route('backoffice.variants.destroy', $variant->id) }}" class="inline-form" onsubmit="return confirm('Yakin hapus variant ini?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-small btn-small-red">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty">
                Data variant tidak ditemukan.
            </div>
        @endif
    </div>

    <script>
        (function () {
            const toggleButtons = document.querySelectorAll('[data-toggle-group]');

            toggleButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const card = button.closest('[data-group-card]');

                    if (!card) {
                        return;
                    }

                    card.classList.toggle('is-open');

                    button.textContent = card.classList.contains('is-open')
                        ? 'Tutup'
                        : 'Variant';
                });
            });
        })();
    </script>
@endsection