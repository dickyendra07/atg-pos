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
            min-width: 1180px;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 22px;
            overflow: hidden;
        }

        thead th {
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 16px 14px;
            background: #f8fafc;
            border-bottom: 1px solid #e8edf4;
            white-space: nowrap;
            vertical-align: middle;
        }

        tbody td {
            padding: 16px 14px;
            border-bottom: 1px solid #edf1f6;
            vertical-align: middle;
            font-size: 14px;
            color: #111827;
            text-align: center;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .product-name {
            font-weight: 800;
            color: #111827;
            font-size: 15px;
        }

        .variant-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef2ff;
            color: #3730a3;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            margin: 3px;
            white-space: nowrap;
        }

        .variants-wrap {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            justify-content: center;
            align-items: center;
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

        .btn-small-orange {
            background: #ea580c;
            color: white;
        }

        .btn-small-muted {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .inline-form {
            display: inline-block;
            margin: 0;
        }

        /* The Edit action is the way into the Product Workspace: the table fits its card (Variants wrap)
           instead of a fixed 1180px that pushed Edit behind a horizontal scroll, and on phones each row
           stacks as a card so every action stays reachable. */
        .products-clean-table {
            min-width: 0;
        }

        .products-clean-table .action-stack {
            min-width: 0;
        }

        @media (max-width: 780px) {
            .products-clean-table thead {
                display: none;
            }

            .products-clean-table,
            .products-clean-table tbody,
            .products-clean-table tr,
            .products-clean-table td {
                display: block;
                width: 100%;
                box-sizing: border-box;
            }

            .products-clean-table tr {
                padding: 12px 6px;
                border-bottom: 1px solid #edf1f6;
            }

            .products-clean-table tbody tr:last-child {
                border-bottom: 0;
            }

            .products-clean-table td {
                border: 0;
                padding: 6px 10px;
                text-align: left;
            }

            .products-clean-table .variants-wrap,
            .products-clean-table .action-stack {
                justify-content: flex-start;
            }
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


        .product-filter-card {
            margin: 20px 24px 0;
            padding: 18px;
            border: 1px solid #e8edf4;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.04);
        }

        .product-filter-form {
            display: grid;
            grid-template-columns: 1.4fr 1fr auto auto;
            gap: 12px;
            align-items: end;
        }

        .filter-field label {
            display: block;
            font-size: 11px;
            font-weight: 900;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
        }

        .filter-field input,
        .filter-field select {
            width: 100%;
            min-height: 46px;
            box-sizing: border-box;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: #ffffff;
            padding: 0 14px;
            font-size: 14px;
            color: #111827;
            outline: none;
        }

        .filter-field input:focus,
        .filter-field select:focus {
            border-color: rgba(232,106,58,0.70);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .product-category-section {
            margin: 24px;
            border: 1px solid #e8edf4;
            border-radius: 24px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.04);
        }

        .product-category-heading {
            margin: 0;
            font: inherit;
        }

        .product-category-head {
            appearance: none;
            width: 100%;
            margin: 0;
            border: 0;
            border-bottom: 1px solid #f1e3da;
            font: inherit;
            text-align: left;
            color: inherit;
            cursor: default;
            padding: 18px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, #ffffff 0%, #fff8f4 100%);
            transition: border-color 0.2s ease, box-shadow 0.18s ease;
        }

        .bo-js .product-category-section:not(.is-open) .product-category-head {
            border-bottom-color: transparent;
        }

        .bo-js .product-category-head {
            cursor: pointer;
        }

        .bo-js .product-category-head:hover {
            background: linear-gradient(135deg, #ffffff 0%, #fff1ea 100%);
        }

        .product-category-head:focus {
            outline: none;
        }

        .product-category-head:focus-visible {
            box-shadow: inset 0 0 0 3px rgba(232, 106, 58, 0.45);
        }

        .product-category-title {
            font-size: 18px;
            font-weight: 900;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .product-category-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .product-category-count {
            font-size: 12px;
            font-weight: 900;
            color: #c9552a;
            background: #fff1ea;
            border: 1px solid #f5d5c7;
            border-radius: 999px;
            padding: 7px 10px;
            white-space: nowrap;
        }

        .product-category-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid #f1e3da;
            color: #c9552a;
            font-size: 16px;
            font-weight: 900;
            line-height: 1;
            transition: transform 0.2s ease;
        }

        /* Pointing right only means "closed" when JS can open it again; without JS the panel is open. */
        .bo-js .product-category-section:not(.is-open) .product-category-toggle {
            transform: rotate(-90deg);
        }

        /* Accordion panel: same grid-rows technique as the sidebar groups. It only collapses once JS is
           known to be running (.bo-js), so without JS every category stays open and reachable. Closed
           content is also made invisible (after the animation) so its links and buttons leave the tab order. */
        .product-category-panel {
            display: grid;
            grid-template-rows: 1fr;
        }

        .product-category-panel-inner {
            min-height: 0;
            overflow: hidden;
        }

        .bo-js .product-category-panel {
            grid-template-rows: 0fr;
            transition: grid-template-rows 0.22s ease;
        }

        .bo-js .product-category-section.is-open .product-category-panel {
            grid-template-rows: 1fr;
        }

        .bo-js .product-category-section:not(.is-open) .product-category-panel-inner {
            visibility: hidden;
            transition: visibility 0s linear 0.22s;
        }

        /* A record revealed by navigation (bo:reveal) opens at once so the scroll position stays right. */
        .product-category-section.is-instant .product-category-panel,
        .product-category-section.is-instant .product-category-panel-inner,
        .product-category-section.is-instant .product-category-toggle,
        .product-category-section.is-instant .product-category-head {
            transition: none;
        }

        .product-category-tools {
            display: none;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            margin: 24px 24px -8px;
        }

        .bo-js .product-category-tools {
            display: flex;
        }

        @media (prefers-reduced-motion: reduce) {
            .product-category-head,
            .product-category-toggle,
            .bo-js .product-category-panel,
            .bo-js .product-category-section:not(.is-open) .product-category-panel-inner {
                transition: none;
            }
        }

        @media (max-width: 900px) {
            .product-filter-form {
                grid-template-columns: 1fr;
            }

            .product-filter-card,
            .product-category-section,
            .product-category-tools {
                margin-left: 18px;
                margin-right: 18px;
            }
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

            .empty {
                margin-left: 18px;
                margin-right: 18px;
            }
        }
    </style>

    <div class="products-shell">
        <div class="products-topbar">
            <div class="products-title-block">

                <h1 class="products-title">Back Office - Products</h1>

            </div>

            <div class="products-actions">
                <a href="{{ route('backoffice.products.export.csv') }}" class="btn btn-secondary">Export CSV</a>
                <a href="{{ route('backoffice.products.import') }}" class="btn btn-secondary">Import CSV</a>
                <a href="{{ route('backoffice.products.create', ['return_to' => $listReturnTo]) }}" class="btn btn-primary">Tambah Product</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-secondary">Dashboard</a>
            </div>
        </div>



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

            <div class="product-filter-card">
                <form method="GET" action="{{ route('backoffice.products.index') }}" class="product-filter-form">
                    <div class="filter-field">
                        <label for="search">Search</label>
                        <input
                            type="text"
                            name="search"
                            id="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Cari product / brand / category / variant"
                        >
                    </div>

                    <div class="filter-field">
                        <label for="category_id">Category</label>
                        <select name="category_id" id="category_id">
                            <option value="">Semua Category</option>
                            @foreach(($categories ?? collect()) as $category)
                                <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('backoffice.products.index') }}" class="btn btn-secondary">Reset</a>
                </form>
            </div>

            @if($products->count())
                {{-- Every category starts closed. With a search or a category filter active the page only lists
                     categories that hold a match, so those start open and the results stay visible.
                     Closing is done by CSS + JS only (.bo-js). Without JS every panel is visible, so the markup
                     says aria-expanded="true"; the script below sets the real state as soon as it runs. --}}
                @php
                    $categoriesStartOpen = filled($filters['search'] ?? null) || filled($filters['category_id'] ?? null);
                @endphp

                @if(($productGroups ?? collect())->count() > 1)
                    <div class="product-category-tools">
                        <button type="button" class="btn btn-secondary btn-sm" data-product-category-expand-all>Expand All</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-product-category-collapse-all>Collapse All</button>
                    </div>
                @endif

                @foreach(($productGroups ?? collect()) as $categoryName => $categoryProducts)
                    <div class="product-category-section {{ $categoriesStartOpen ? 'is-open' : '' }}" data-product-category-section>
                        <h2 class="product-category-heading">
                            <button type="button"
                                    class="product-category-head"
                                    id="product-category-head-{{ $loop->index }}"
                                    aria-expanded="true"
                                    aria-controls="product-category-panel-{{ $loop->index }}"
                                    data-product-category-toggle>
                                <span class="product-category-title">{{ $categoryName }}</span>
                                <span class="product-category-meta">
                                    <span class="product-category-count">{{ $categoryProducts->count() }} {{ $categoryProducts->count() === 1 ? 'Product' : 'Products' }}</span>
                                    <span class="product-category-toggle" aria-hidden="true">⌄</span>
                                </span>
                            </button>
                        </h2>

                        <div class="product-category-panel"
                             id="product-category-panel-{{ $loop->index }}"
                             role="region"
                             aria-labelledby="product-category-head-{{ $loop->index }}">
                        <div class="product-category-panel-inner">
                        <div class="table-wrap">
                            <table class="products-clean-table table-center">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Variants</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($categoryProducts as $product)
                                        <tr id="product-{{ $product->id }}">
                                            <td>
                                                <div class="product-name">{{ $product->name }}</div>
                                            </td>
                                            <td>
                                                <div class="variants-wrap">
                                                    @forelse($product->variants as $variant)
                                                        <span class="variant-pill">
                                                            {{ $variant->name }} - Rp {{ number_format((float) ($variant->price_dine_in ?? $variant->price), 0, ',', '.') }}
                                                        </span>
                                                    @empty
                                                        -
                                                    @endforelse
                                                </div>
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
                                                    <a href="{{ route('backoffice.products.edit', [$product->id, 'return_to' => $listReturnTo]) }}" class="btn btn-secondary btn-sm">Edit</a>

                                                    @if($product->is_active)
                                                        <form method="POST" action="{{ route('backoffice.products.destroy', $product->id) }}" class="inline-form"
                                                              data-bo-confirm
                                                              data-bo-confirm-title="Nonaktifkan Product?"
                                                              data-bo-confirm-body="Product tidak akan tampil di Cashier, tetapi histori dan data terkait tetap disimpan."
                                                              data-bo-confirm-label="Nonaktifkan"
                                                              data-bo-confirm-tone="warning">
                                                            @csrf
                                                            @method('DELETE')
                                                            @include('backoffice.partials.return-to-field', ['returnTo' => $listReturnTo])
                                                            <button type="submit" class="btn btn-warning btn-sm">Nonaktifkan</button>
                                                        </form>
                                                    @endif

                                                    {{-- The only delete: the temporary cleanup action (flag + owner/admin pusat, typed confirmation, tombstone). --}}
                                                    @include('backoffice.partials.cleanup-button', ['type' => 'product', 'id' => $product->id, 'name' => $product->name, 'return' => $listReturnTo, 'testid' => 'cleanup-product-'.$product->id])
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="empty">
                    Belum ada product yang cocok dengan filter aktif.
                </div>
            @endif
        </div>
    </div>

    <script>
        (function () {
            var sections = Array.prototype.slice.call(document.querySelectorAll('[data-product-category-section]'));
            var motionTimer = null;

            function setOpen(section, open, instant) {
                var button = section.querySelector('[data-product-category-toggle]');

                if (instant) {
                    // No animation, so a reveal followed by a scroll measures the final layout.
                    section.classList.add('is-instant');
                    window.clearTimeout(motionTimer);
                    motionTimer = window.setTimeout(function () {
                        sections.forEach(function (item) { item.classList.remove('is-instant'); });
                    }, 60);
                }

                section.classList.toggle('is-open', open);

                if (button) {
                    button.setAttribute('aria-expanded', open ? 'true' : 'false');
                }
            }

            // The markup describes the no-JS page (everything visible). Now that JS runs, make aria-expanded
            // match what the CSS actually shows: collapsed unless the server marked the category open.
            sections.forEach(function (section) {
                var button = section.querySelector('[data-product-category-toggle]');

                if (button) {
                    button.setAttribute('aria-expanded', section.classList.contains('is-open') ? 'true' : 'false');
                }
            });

            // A record brought back into view after save may sit in a collapsed category.
            document.addEventListener('bo:reveal', function (event) {
                var section = event.target.closest('[data-product-category-section]');

                if (section) {
                    setOpen(section, true, true);
                }
            });

            sections.forEach(function (section) {
                var button = section.querySelector('[data-product-category-toggle]');

                if (!button) {
                    return;
                }

                // A real <button>: Enter and Space already activate it.
                button.addEventListener('click', function () {
                    setOpen(section, !section.classList.contains('is-open'), false);
                });
            });

            var expandAll = document.querySelector('[data-product-category-expand-all]');
            var collapseAll = document.querySelector('[data-product-category-collapse-all]');

            if (expandAll) {
                expandAll.addEventListener('click', function () {
                    sections.forEach(function (section) { setOpen(section, true, false); });
                });
            }

            if (collapseAll) {
                collapseAll.addEventListener('click', function () {
                    sections.forEach(function (section) { setOpen(section, false, false); });
                });
            }
        })();
    </script>

@endsection
