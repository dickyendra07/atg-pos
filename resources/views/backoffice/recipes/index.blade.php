@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Recipes - Back Office ATG POS';
@endphp

@section('content')
    <style>
        .recipes-shell {
            display: grid;
            gap: 22px;
        }

        .recipes-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .recipes-title-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .recipes-kicker {
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

        .recipes-title {
            margin: 0;
            font-size: 38px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
        }

        .recipes-subtitle {
            margin: 0;
            max-width: 800px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .recipes-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
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
            padding: 12px 14px;
            background: #f8fafc;
            border-bottom: 1px solid #e8edf4;
            white-space: nowrap;
        }

        tbody td {
            padding: 10px 14px;
            border-bottom: 1px solid #edf1f6;
            vertical-align: middle;
            font-size: 14px;
            color: #111827;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .recipe-name {
            font-weight: 800;
            color: #111827;
            font-size: 15px;
        }

        .type-badge,
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
            margin: 2px 6px 2px 0;
        }

        .badge-raw {
            background: #fff7ed;
            color: #b45309;
        }

        .badge-semi {
            background: #eef2ff;
            color: #3730a3;
        }

        .status-active {
            background: #e8fff1;
            color: #17663a;
        }

        .status-inactive {
            background: #fff1f1;
            color: #b42318;
        }

        .error-list {
            margin: 10px 0 0 18px;
            padding: 0;
            font-weight: 600;
        }

        .error-list li {
            margin-bottom: 6px;
            line-height: 1.5;
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


        .recipe-filter-card {
            margin: 20px 24px 0;
            padding: 18px;
            border: 1px solid #e8edf4;
            border-radius: 22px;
            background: #ffffff;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.04);
        }

        .recipe-filter-form {
            display: grid;
            grid-template-columns: 1.4fr 0.8fr 0.9fr auto auto;
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

        @media (max-width: 980px) {
            .recipe-filter-form {
                grid-template-columns: 1fr;
            }

            .recipe-filter-card {
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
            .recipes-topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .recipes-title {
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

        /* Recipe list: Name | Ingredient Type | Status | Action. Recipe Items are viewed/edited in the Recipe editor. */
        .recipe-table {
            table-layout: fixed;
            min-width: 720px;
        }

        .recipe-table th:nth-child(1) { width: 38%; text-align: left; }
        .recipe-table th:nth-child(2) { width: 18%; }
        .recipe-table th:nth-child(3) { width: 12%; }
        .recipe-table th:nth-child(4) { width: 32%; }

        .recipe-table td { text-align: center; vertical-align: middle; }
        .recipe-table td.recipe-name-cell { text-align: left; }

        .recipe-name {
            font-size: 14px;
            line-height: 1.35;
            overflow-wrap: anywhere;
        }

        .type-badge,
        .status-badge {
            margin: 0;
            padding: 5px 10px;
            font-size: 11px;
        }

        .recipe-type-cell .type-badge + .type-badge {
            margin-left: 4px;
        }

        .recipe-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }

        .recipe-actions form {
            margin: 0;
        }

        /* Phones: each recipe becomes a compact card, so the page never scrolls sideways. */
        @media (max-width: 640px) {
            .recipe-table,
            .recipe-table tbody,
            .recipe-table tr,
            .recipe-table td {
                display: block;
                width: 100%;
                min-width: 0;
            }

            .recipe-table {
                border: 0;
                border-radius: 0;
                overflow: visible;
                background: transparent;
            }

            .recipe-table thead {
                position: absolute;
                width: 1px;
                height: 1px;
                overflow: hidden;
                clip: rect(0 0 0 0);
            }

            .recipe-table tbody tr {
                background: #ffffff;
                border: 1px solid #e8edf4;
                border-radius: 16px;
                padding: 12px 14px;
                margin-bottom: 10px;
                display: grid;
                grid-template-columns: 1fr auto;
                gap: 8px 10px;
                align-items: center;
            }

            .recipe-table tbody td {
                padding: 0;
                border: 0;
                width: auto;
                text-align: left;
            }

            .recipe-table td.recipe-name-cell { grid-column: 1 / -1; }
            .recipe-table td.recipe-actions-cell { grid-column: 1 / -1; }
            .recipe-table td.recipe-status-cell { text-align: right; }
            .recipe-actions { justify-content: flex-start; }
            .table-wrap { overflow-x: visible !important; }
        }

    </style>

    <div class="recipes-shell">
        <div class="recipes-topbar">
            <div class="recipes-title-block">

                <h1 class="recipes-title">Back Office - Recipes</h1>

            </div>

            <div class="recipes-actions">
                <a href="{{ route('backoffice.recipes.export.csv') }}" class="btn btn-secondary">Export CSV</a>
                <a href="{{ route('backoffice.recipes.import') }}" class="btn btn-secondary">Import CSV</a>
                <a href="{{ route('backoffice.recipes.create', ['return_to' => $listReturnTo]) }}" class="btn btn-primary" data-recipe-edit data-recipe-title="Tambah Recipe">Tambah Recipe</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-secondary">Dashboard</a>
            </div>
        </div>



        @if(session('import_errors') && count(session('import_errors')) > 0)
            <div class="alert alert-error">
                Detail baris yang dilewati:
                <ul class="error-list">
                    @foreach(session('import_errors') as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-head">

            </div>

            <div class="summary-grid">
                <div class="summary-card orange">
                    <div class="summary-label">Total Recipes</div>
                    <div class="summary-value">{{ $recipes->count() }}</div>
                    <div class="summary-desc">Jumlah seluruh recipe produk jual yang tersimpan di sistem.</div>
                </div>

                <div class="summary-card green">
                    <div class="summary-label">Active Recipes</div>
                    <div class="summary-value">{{ $recipes->where('is_active', true)->count() }}</div>
                    <div class="summary-desc">Recipe aktif yang siap dipakai untuk deduction dan operasional.</div>
                </div>

                <div class="summary-card blue">
                    <div class="summary-label">Total Recipe Items</div>
                    <div class="summary-value">{{ $recipes->sum(fn($recipe) => $recipe->items->count()) }}</div>
                    <div class="summary-desc">Jumlah seluruh item ingredient yang dipakai di semua recipe.</div>
                </div>

                <div class="summary-card violet">
                    <div class="summary-label">Semi Finished Used</div>
                    <div class="summary-value">{{ $recipes->flatMap->items->filter(fn($item) => $item->ingredient?->ingredient_type === \App\Models\Ingredient::TYPE_SEMI_FINISHED)->count() }}</div>
                    <div class="summary-desc">Jumlah item recipe yang sudah memakai bahan setengah jadi.</div>
                </div>
            </div>

            <div class="recipe-filter-card">
                <form method="GET" action="{{ route('backoffice.recipes.index') }}" class="recipe-filter-form">
                    <div class="filter-field">
                        <label for="search">Search</label>
                        <input
                            type="text"
                            name="search"
                            id="search"
                            value="{{ $filters['search'] ?? '' }}"
                            placeholder="Cari recipe / product / variant / ingredient"
                        >
                    </div>

                    <div class="filter-field">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">Semua Status</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="ingredient_type">Ingredient Type</label>
                        <select name="ingredient_type" id="ingredient_type">
                            <option value="">Semua Type</option>
                            <option value="{{ \App\Models\Ingredient::TYPE_RAW }}" @selected(($filters['ingredient_type'] ?? '') === \App\Models\Ingredient::TYPE_RAW)>Mentah</option>
                            <option value="{{ \App\Models\Ingredient::TYPE_SEMI_FINISHED }}" @selected(($filters['ingredient_type'] ?? '') === \App\Models\Ingredient::TYPE_SEMI_FINISHED)>Setengah Jadi</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('backoffice.recipes.index') }}" class="btn btn-secondary">Reset</a>
                </form>
            </div>

            @if($recipes->count())
                <div class="table-wrap">
                    <table class="recipe-table table-center">
                        <thead>
                            <tr>
                                <th>Recipe Name</th>
                                <th>Ingredient Type</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recipes as $recipe)
                                <tr id="recipe-{{ $recipe->id }}">
                                    <td class="recipe-name-cell">
                                        <div class="recipe-name">{{ preg_replace('/^Recipe\s*-\s*/i', '', $recipe->name) }}</div>
                                    </td>
                                    <td class="recipe-type-cell" data-label="Ingredient Type">
                                        @php
                                            $ingredientTypes = $recipe->items
                                                ->map(fn($item) => $item->ingredient?->ingredient_type ?? \App\Models\Ingredient::TYPE_RAW)
                                                ->unique()
                                                ->values();
                                        @endphp

                                        @forelse($ingredientTypes as $ingredientType)
                                            @php
                                                $ingredientTypeLabel = $ingredientType === \App\Models\Ingredient::TYPE_SEMI_FINISHED
                                                    ? 'Setengah Jadi'
                                                    : 'Mentah';
                                            @endphp

                                            @if($ingredientType === \App\Models\Ingredient::TYPE_SEMI_FINISHED)
                                                <span class="type-badge badge-semi">{{ $ingredientTypeLabel }}</span>
                                            @else
                                                <span class="type-badge badge-raw">{{ $ingredientTypeLabel }}</span>
                                            @endif
                                        @empty
                                            -
                                        @endforelse
                                    </td>
                                    <td class="recipe-status-cell" data-label="Status">
                                        @if($recipe->is_active)
                                            <span class="status-badge status-active">Active</span>
                                        @else
                                            <span class="status-badge status-inactive">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="recipe-actions-cell" data-label="Action">
                                        <div class="recipe-actions">
                                            @if(in_array($recipe->id, $mutableRecipeIds, true))
                                                <a href="{{ route('backoffice.recipes.edit', [$recipe->id, 'return_to' => $listReturnTo]) }}" class="btn btn-secondary btn-sm" data-recipe-edit data-recipe-name="{{ $recipe->name }}">Edit</a>
                                            @else
                                                <a href="{{ route('backoffice.recipes.edit', [$recipe->id, 'return_to' => $listReturnTo]) }}" class="btn btn-secondary btn-sm" data-recipe-edit data-recipe-name="{{ $recipe->name }}" title="Recipe dipakai di beberapa outlet atau di luar akses Anda. Hanya bisa dilihat.">Lihat (read-only)</a>
                                            @endif

                                            @if($recipe->is_active && in_array($recipe->id, $mutableRecipeIds, true))
                                                <form method="POST" action="{{ route('backoffice.recipes.destroy', $recipe->id) }}" onsubmit="return confirm('Yakin ingin menonaktifkan recipe ini?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    @include('backoffice.partials.return-to-field', ['returnTo' => $listReturnTo])
                                                    <button type="submit" class="btn btn-warning btn-sm">
                                                        Nonaktifkan
                                                    </button>
                                                </form>
                                            @endif

                                            @include('backoffice.partials.cleanup-button', ['type' => 'recipe', 'id' => $recipe->id, 'name' => $recipe->name, 'return' => $listReturnTo, 'label' => 'Hapus Permanen', 'class' => 'btn btn-danger btn-sm', 'testid' => 'cleanup-recipe-'.$recipe->id])
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">
                    Belum ada recipe yang cocok dengan filter aktif.
                </div>
            @endif

        </div>
    </div>

    {{-- Edit / Tambah Recipe in place: the standalone editor and create form are shown in a side panel on this page (no page change). --}}
    <style>
        .rcp-drawer-backdrop { position: fixed; inset: 0; z-index: 9000; background: rgba(15, 23, 42, 0.45); display: none; }
        .rcp-drawer { position: fixed; z-index: 9001; top: 0; right: 0; bottom: 0; width: min(1180px, 96vw); background: #fff; box-shadow: -20px 0 60px rgba(15, 23, 42, 0.25); display: none; flex-direction: column; }
        .rcp-drawer.is-open, .rcp-drawer-backdrop.is-open { display: flex; }
        .rcp-drawer-backdrop.is-open { display: block; }
        .rcp-drawer-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #e5e7eb; }
        .rcp-drawer-title { margin: 0; font-size: 18px; font-weight: 800; color: #111827; overflow-wrap: anywhere; }
        .rcp-drawer-close { min-height: 40px; padding: 0 16px; border: 1px solid #d1d5db; border-radius: 12px; background: #fff; font-weight: 800; cursor: pointer; }
        .rcp-drawer-body { position: relative; flex: 1; min-height: 0; }
        .rcp-drawer-frame { width: 100%; height: 100%; border: 0; display: block; background: #fff; }
        .rcp-drawer-loading { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: #fff; color: #475569; font-weight: 700; }
        .rcp-drawer-loading[hidden] { display: none; }
        @media (max-width: 720px) { .rcp-drawer { width: 100vw; } }
        @media (prefers-reduced-motion: no-preference) { .rcp-drawer.is-open { animation: rcp-in .18s ease-out; } @keyframes rcp-in { from { transform: translateX(24px); opacity: .6; } to { transform: none; opacity: 1; } } }
    </style>

    <div class="rcp-drawer-backdrop" id="rcp-drawer-backdrop"></div>
    <aside class="rcp-drawer" id="rcp-drawer" role="dialog" aria-modal="true" aria-labelledby="rcp-drawer-title">
        <div class="rcp-drawer-head">
            <h2 class="rcp-drawer-title" id="rcp-drawer-title">Edit Recipe</h2>
            <button type="button" class="rcp-drawer-close" id="rcp-drawer-close">Tutup</button>
        </div>
        <div class="rcp-drawer-body">
            <iframe class="rcp-drawer-frame" id="rcp-drawer-frame" title="Edit Recipe"></iframe>
            <div class="rcp-drawer-loading" id="rcp-drawer-loading" hidden>Memuat editor Recipe…</div>
        </div>
    </aside>

    <script>
        (function () {
            'use strict';

            var drawer = document.getElementById('rcp-drawer');
            var backdrop = document.getElementById('rcp-drawer-backdrop');
            var frame = document.getElementById('rcp-drawer-frame');
            var loading = document.getElementById('rcp-drawer-loading');
            var title = document.getElementById('rcp-drawer-title');
            var closeBtn = document.getElementById('rcp-drawer-close');
            var TOAST_KEY = 'atg.recipes.drawerToasts';
            var listPath = <?php echo json_encode(parse_url(route('backoffice.recipes.index', [], false), PHP_URL_PATH)); ?>;
            var active = false, loads = 0, opener = null;

            // The layout's .shell has a backdrop-filter, which makes it (not the screen) the reference for position:fixed
            // and clips its children. Inside it the panel was as tall as the whole Recipes list, so its bottom ended up
            // far below the screen. Hang the panel on <body> instead: then "fixed" means the real viewport.
            document.body.appendChild(backdrop);
            document.body.appendChild(drawer);

            // A toast that was queued right before the list reloaded (after a save in the drawer).
            // (BackofficeToast is defined by the layout's feedback script, which comes after this one: wait for the page.)
            document.addEventListener('DOMContentLoaded', function () {
                try {
                    var queued = JSON.parse(sessionStorage.getItem(TOAST_KEY) || 'null');
                    sessionStorage.removeItem(TOAST_KEY);
                    if (queued && window.BackofficeToast) { queued.forEach(function (t) { window.BackofficeToast.show(t.type, t.message); }); }
                } catch (e) { /* storage unavailable: the save itself already happened */ }
            });

            function open(link) {
                opener = link;
                active = true;
                loads = 0;
                title.textContent = link.getAttribute('data-recipe-title')
                    || 'Edit Recipe' + (link.getAttribute('data-recipe-name') ? ' · ' + link.getAttribute('data-recipe-name') : '');
                frame.title = title.textContent;
                loading.hidden = false;
                frame.src = link.href;
                drawer.classList.add('is-open');
                backdrop.classList.add('is-open');
                document.body.style.overflow = 'hidden';
                closeBtn.focus();
            }

            function close(reloadList) {
                active = false;
                drawer.classList.remove('is-open');
                backdrop.classList.remove('is-open');
                document.body.style.overflow = '';
                frame.removeAttribute('src');

                // Anything done inside the editor (item added / qty changed / header saved) may change this list.
                if (reloadList) { location.reload(); return; }
                if (opener && opener.focus) { try { opener.focus(); } catch (e) { /* ignore */ } }
            }

            frame.addEventListener('load', function () {
                if (!active) { return; }
                loads += 1;
                loading.hidden = true;

                var doc = null, path = '';
                try { doc = frame.contentDocument; path = frame.contentWindow.location.pathname; } catch (e) { return; }

                // The editor redirected to the Recipes list (header saved, or "Kembali"): the work is done here.
                if (loads > 1 && path === listPath) {
                    var toasts = [];
                    doc.querySelectorAll('[data-bo-toast]').forEach(function (el) {
                        var message = el.querySelector('.bo-toast-message');
                        if (message) { toasts.push({ type: el.getAttribute('data-bo-toast-type') || 'info', message: message.textContent.trim() }); }
                    });
                    try { sessionStorage.setItem(TOAST_KEY, JSON.stringify(toasts)); } catch (e) { /* ignore */ }
                    close(true);
                }
            });

            document.addEventListener('click', function (event) {
                var link = event.target.closest ? event.target.closest('a[data-recipe-edit]') : null;
                if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }   // new tab etc. keep working
                event.preventDefault();
                open(link);
            });

            closeBtn.addEventListener('click', function () { close(loads > 1); });
            backdrop.addEventListener('click', function () { close(loads > 1); });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && active) { event.preventDefault(); close(loads > 1); }
            });
        })();
    </script>
@endsection
