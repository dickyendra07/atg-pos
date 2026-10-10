<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Recipe - Back Office ATG POS</title>
    {{-- Shown inside the Recipes list drawer (an iframe of this same page): drop the page header there. Decided in the
         browser before anything is painted, so it also holds after a save or an item action inside the frame. --}}
    <script>if (window.self !== window.top) { document.documentElement.classList.add('is-embedded'); }</script>
    <style>
        .is-embedded .topbar { display: none; }
        .is-embedded .wrap { margin: 16px auto; }
        .is-embedded body { background: #fff; }

        /* Standalone and drawer share one layout (see .grid-2 below). Only the page header and the "who / which
           outlet" box are dropped in the drawer: both are already known from the page behind the panel. */
        .is-embedded .recipe-header-card > .info { display: none; }
    </style>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 1320px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 16px;
        }

        .title {
            font-size: 28px;
            font-weight: bold;
        }

        .btn {
            text-decoration: none;
            background: #111827;
            color: white;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: bold;
            display: inline-block;
            border: 0;
            cursor: pointer;
        }

        .btn-success {
            background: #166534;
        }

        .btn-danger {
            background: #b91c1c;
            color: white;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 12px;
        }

        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
            margin-bottom: 20px;
        }

        .info {
            margin-bottom: 18px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .field input,
        .field select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px;
            font-size: 14px;
        }

        .error-box {
            margin-bottom: 18px;
            background: #ffe8e8;
            color: #9b1c1c;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
        }

        .readonly-box {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            color: #92400e;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-weight: 600;
        }

        .success-box {
            margin-bottom: 18px;
            background: #e8fff1;
            color: #17663a;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .section-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 16px;
        }

        .table-wrap {
            overflow-x: auto;
            margin-top: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        th {
            background: #f9fafb;
            font-size: 13px;
            color: #555;
        }

        .note {
            margin-top: 20px;
            background: #eef2ff;
            color: #3730a3;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
        }

        /* Header and add-form stack in a narrow left column; the item list gets the rest of the width, so its
           Qty and Action controls are on screen without horizontal scrolling. The long list never pushes
           "Tambah Bahan Recipe" out of sight. Narrower than 1140px: header, add-form, then the list, full width. */
        .grid-2 {
            display: grid;
            grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
            grid-template-areas: "head items" "add items";
            grid-template-rows: auto 1fr;
            gap: 20px;
            align-items: start;
        }

        .right-stack {
            display: contents;
        }

        .recipe-header-card { grid-area: head; }
        #recipe-add-item { grid-area: add; }
        #recipe-items { grid-area: items; }

        /* Grid children may shrink below their content, so the page itself never grows sideways. */
        .grid-2 > *,
        .grid-2 .card {
            min-width: 0;
        }

        .inline-form {
            display: inline-block;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-raw {
            background: #fff7ed;
            color: #b45309;
        }

        .badge-semi {
            background: #eef2ff;
            color: #3730a3;
        }

        .empty-state {
            padding: 16px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 12px;
            font-weight: bold;
        }

        @media (max-width: 1139px) {
            .grid-2 {
                grid-template-columns: minmax(0, 1fr);
                grid-template-areas: "head" "add" "items";
                grid-template-rows: auto;
            }
        }

        @media (max-width: 768px) {
            .wrap {
                margin: 24px auto;
                padding: 0 14px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .title {
                font-size: 24px;
            }
        }
    
        .qty-display {
            font-weight:800;
            color:#111827;
        }

        .qty-edit-form {
            display:none;
            align-items:center;
            gap:8px;
            margin-top:8px;
        }

        tr.editing .qty-display {
            display:none;
        }

        tr.editing .qty-edit-form {
            display:flex;
        }

        .qty-input {
            width:90px;
            height:38px;
            border:1px solid #d1d5db;
            border-radius:10px;
            padding:0 10px;
            text-align:center;
            font-weight:700;
        }

        .btn-save {
            background:#166534;
            color:white;
            border:none;
            padding:9px 14px;
            border-radius:10px;
            font-size:12px;
            font-weight:800;
            cursor:pointer;
        }

        .btn-edit {
            background:#2563eb;
            color:white;
            border:none;
            padding:10px 16px;
            border-radius:12px;
            font-size:12px;
            font-weight:800;
            cursor:pointer;
        }

        .btn-danger {
            background:#dc2626;
            color:white;
            border:none;
            padding:10px 16px;
            border-radius:12px;
            font-size:12px;
            font-weight:800;
            cursor:pointer;
        }

        .action-group {
            display:flex;
            gap:8px;
            align-items:center;
        }

    

.qty-wrapper {
    display:flex;
    align-items:center;
}

.qty-display {
    font-weight:800;
    font-size:14px;
    color:#111827;
}

.qty-form {
    display:none;
    align-items:center;
    gap:8px;
}

.qty-wrapper.editing .qty-display {
    display:none;
}

.qty-wrapper.editing .qty-form {
    display:flex;
}

.qty-input {
    width:90px;
    height:40px;
    border:1px solid #d1d5db;
    border-radius:12px;
    padding:0 12px;
    font-weight:700;
    text-align:center;
}


.btn-save {
    background:#15803d;
    color:white;
    border:none;
    padding:10px 14px;
    border-radius:12px;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
}


.btn-cancel {
    background:#64748b;
    color:white;
    border:none;
    padding:10px 14px;
    border-radius:12px;
    font-size:12px;
    font-weight:800;
    cursor:pointer;
}


.btn-edit {
    background:#2563eb;
    color:white;
    border:none;
    padding:10px 16px;
    border-radius:12px;
    font-weight:800;
    cursor:pointer;
}


.btn-danger {
    background:#dc2626;
    color:white;
    border:none;
    padding:10px 16px;
    border-radius:12px;
    font-weight:800;
    cursor:pointer;
}


.action-group {
    display:flex;
    gap:8px;
    align-items:center;
}

</style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title">Edit Recipe</div>
            <a href="{{ \App\Support\BackofficeReturnUrl::resolve(request(), 'backoffice.recipes.index', [], 'recipe-'.$recipe->id) }}" class="btn btn-secondary">Kembali</a>
        </div>

        @if($errors->any())
            <div class="error-box">
                <div>Form belum valid:</div>
                <ul style="margin:10px 0 0 18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif



        @unless($canMutate)
            <div class="readonly-box" id="recipe-readonly-notice">
                <strong>Read-only.</strong> {{ $mutationNotice }}
            </div>
        @endunless

        <div class="grid-2">
            <div class="card recipe-header-card">
                <div class="section-title">Header Recipe</div>

                <div class="info">
                    <strong>User:</strong> {{ $user->name }}<br>
                    <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                    <strong>Outlet:</strong> {{ $activeOutletLabel ?? '-' }}
                </div>

                <form method="POST" action="{{ route('backoffice.recipes.update', $recipe->id) }}">
                    @csrf
                    @method('PUT')
                    @include('backoffice.partials.return-to-field')

                    <fieldset @disabled(! $canMutate) style="border:0;padding:0;margin:0;min-width:0;">
                    <div class="recipe-fields">
                    <div class="field">
                        <label>Product Variant</label>
                        <select name="product_variant_id" required>
                            <option value="">Pilih variant</option>
                            @foreach($variants as $variant)
                                <option value="{{ $variant->id }}" @selected(old('product_variant_id', $recipe->product_variant_id) == $variant->id)>
                                    {{ $variant->product->name ?? '-' }} - {{ $variant->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>Recipe Name</label>
                        <input type="text" name="name" value="{{ old('name', preg_replace('/^Recipe\s*-\s*/i', '', $recipe->name)) }}" required>
                    </div>

                    <div class="field">
                        <label>Status</label>
                        <select name="is_active" required>
                            <option value="1" @selected(old('is_active', (string) (int) $recipe->is_active) == '1')>Active</option>
                            <option value="0" @selected(old('is_active', (string) (int) $recipe->is_active) == '0')>Inactive</option>
                        </select>
                    </div>

                    @if($canMutate)
                        <div class="actions">
                            <button type="submit" class="btn btn-primary">Update Header</button>
                        </div>
                    @endif
                    </div>
                    </fieldset>
                </form>
            </div>

            <div class="right-stack">
                <div class="card" id="recipe-items">
                    <div class="section-title">Daftar Recipe Items</div>

                    @if($recipe->items->count())
                        <div class="table-wrap">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th class="c-ing">Ingredient</th>
                                        <th class="c-unit">Unit</th>
                                        <th class="c-qty">Qty</th>
                                        <th class="c-act">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recipe->items as $item)
                                        @php
                                            $ingredientType = $item->ingredient?->ingredient_type;
                                            $ingredientTypeLabel = $item->ingredient?->ingredientTypeLabel() ?? 'Mentah';
                                        @endphp

                                        <tr id="recipe-item-{{ $item->id }}">

                                            <td class="c-ing">
                                                <div class="ing-name">{{ $item->ingredient->name ?? '-' }}</div>
                                                <div class="ing-meta">
                                                    <span class="ing-cat">{{ $item->ingredient->category->name ?? '-' }}</span>
                                                    @if($ingredientType === \App\Models\Ingredient::TYPE_SEMI_FINISHED)
                                                        <span class="badge badge-semi">
                                                            {{ $ingredientTypeLabel }}
                                                        </span>
                                                    @else
                                                        <span class="badge badge-raw">
                                                            {{ $ingredientTypeLabel }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>

                                            <td class="c-unit" data-label="Unit">
                                                {{ $item->unit ?? $item->ingredient->unit ?? '-' }}
                                            </td>


                                            <td class="c-qty" data-label="Qty">

                                                <div class="qty-container">

                                                    <span class="qty-text">
                                                        {{ number_format((float)$item->qty,2,',','.') }}
                                                    </span>

                                                    @if($canMutate)

                                                    <form method="POST"
                                                          action="{{ route('backoffice.recipes.items.update', [$recipe->id,$item->id]) }}"
                                                          class="qty-form">

                                                        @csrf
                                                        @method('PUT')
                                                        @include('backoffice.partials.return-to-field')

                                                        <input
                                                            type="number"
                                                            step="0.01"
                                                            name="qty"
                                                            value="{{ $item->qty }}"
                                                            class="qty-input"
                                                        >

                                                        <button class="btn btn-primary btn-sm">
                                                            ✓ Simpan
                                                        </button>


                                                        <button type="button"
                                                                class="btn btn-secondary btn-sm"
                                                                onclick="closeQty(this)">
                                                            Batal
                                                        </button>

                                                    </form>
                                                    @endif

                                                </div>

                                            </td>


                                            <td class="c-act" data-label="Action">

                                                <div class="action-buttons">
                                                    @if($canMutate)

                                                    <button type="button"
                                                            class="btn btn-secondary btn-sm btn-edit-trigger"
                                                            onclick="openQty(this)">
                                                        ✏ Edit
                                                    </button>


                                                    <form method="POST"
                                                          action="{{ route('backoffice.recipes.items.destroy', [$recipe->id,$item->id]) }}"
                                                          onsubmit="return confirm('Yakin mau hapus recipe item ini?')">

                                                        @csrf
                                                        @method('DELETE')
                                                        @include('backoffice.partials.return-to-field')

                                                        <button class="btn btn-danger btn-sm">
                                                            🗑 Hapus
                                                        </button>

                                                    </form>
                                                    @else
                                                        <span class="qty-text">Read-only</span>
                                                    @endif

                                                </div>

                                            </td>


                                        </tr>

                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty-state">
                            Recipe ini belum punya bahan sama sekali.
                        </div>
                    @endif
                </div>

                <div class="card" id="recipe-add-item">
                    <div class="section-title">Tambah Bahan Recipe</div>

                    @unless($canMutate)
                        <div class="info">Recipe read-only, bahan tidak bisa ditambah dari konteks ini.</div>
                    @else
                    <form method="POST" action="{{ route('backoffice.recipes.items.store', $recipe->id) }}">
                        @csrf
                        @include('backoffice.partials.return-to-field')

                        <div class="field">
                            <label>Ingredient</label>
                            <select name="ingredient_id" required>
                                <option value="">Pilih ingredient</option>
                                @foreach($ingredients as $ingredient)
                                    <option value="{{ $ingredient->id }}">
                                        {{ $ingredient->name }}
                                        - {{ $ingredient->unit }}
                                        - {{ $ingredient->category->name ?? '-' }}
                                        - [{{ strtoupper($ingredient->ingredientTypeLabel()) }}]
                                    </option>
                                @endforeach
                            </select>
                            <div class="info" style="margin-top:8px;">
                                @if($variantOutlets->isNotEmpty())
                                    Variant ini tersedia di: {{ $variantOutlets->implode(', ') }}. Hanya ingredient aktif yang tersedia di semua outlet tersebut yang ditampilkan.
                                @else
                                    Hanya ingredient aktif yang belum ada di recipe ini yang ditampilkan.
                                @endif
                            </div>
                        </div>

                        <div class="add-row">
                            <div class="field">
                                <label>Qty</label>
                                <input type="number" name="qty" min="0.01" step="0.01" required>
                            </div>

                            <div class="actions">
                                <button type="submit" class="btn btn-primary">Tambah Recipe Item</button>
                            </div>
                        </div>
                    </form>
                    @endunless
                </div>
            </div>
        </div>
    </div>


<script>

function editQty(button){

    const wrapper = button
        .closest('tr')
        .querySelector('.qty-wrapper');

    wrapper.classList.add('editing');

}


function cancelQty(button){

    const wrapper = button.closest('.qty-wrapper');

    wrapper.classList.remove('editing');

}

</script>



<style>

.qty-form {
    display:none;
    align-items:center;
    gap:8px;
}

.qty-container.editing .qty-text {
    display:none;
}

.qty-container.editing .qty-form {
    display:flex;
}


.qty-input {
    width:90px;
    height:40px;
    border-radius:12px;
    border:1px solid #d1d5db;
    text-align:center;
    font-weight:700;
}


.btn-edit,
.btn-save,
.btn-cancel,
.btn-danger {

    border:none;
    cursor:pointer;
    border-radius:12px;
    padding:10px 14px;
    font-size:12px;
    font-weight:800;

}


.btn-edit {
    background:#2563eb;
    color:white;
}


.btn-save {
    background:#15803d;
    color:white;
}


.btn-cancel {
    background:#64748b;
    color:white;
}


.btn-danger {
    background:#dc2626;
    color:white;
}


.action-buttons {

    display:flex;
    gap:8px;
    align-items:center;

}

/* RECIPE_EDITOR_COMPACT: item list (.items-table) and the header / add forms. Presentation only. */
.recipe-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    column-gap: 16px;
}

.recipe-fields > .actions {
    grid-column: 1 / -1;
}

.add-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 0 12px;
}

.add-row .field {
    flex: 1 1 110px;
}

.add-row .actions {
    margin: 0 0 16px;
}

#recipe-items {
    padding: 18px;
}

.items-table {
    width: 100%;
    min-width: 0;
    table-layout: fixed;
}

.items-table th,
.items-table td {
    padding: 12px 10px;
    vertical-align: middle;
}

.items-table th {
    box-sizing: border-box;
}

.items-table th.c-unit { width: 64px; }
.items-table th.c-qty { width: 292px; }
.items-table th.c-act { width: 166px; }

.items-table .ing-name {
    font-weight: 700;
    color: #111827;
    overflow-wrap: anywhere;
}

.items-table .ing-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 8px;
    margin-top: 4px;
    font-size: 12px;
    color: #64748b;
}

.items-table .ing-meta .badge {
    padding: 2px 8px;
    font-size: 11px;
}

.items-table .qty-container {
    display: flex;
    align-items: center;
    min-height: 40px;
}

.items-table .qty-text {
    font-weight: 800;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.items-table .qty-form {
    flex-wrap: wrap;
    row-gap: 6px;
}

.items-table .qty-input {
    box-sizing: border-box;
    width: 92px;
    min-width: 0;
}

.items-table .action-buttons .btn,
.items-table .action-buttons form {
    margin: 0;
}

/* While a quantity is being edited the Edit button has nothing left to do; Hapus stays visible. */
.items-table tr.is-editing .btn-edit-trigger {
    display: none;
}

/* Phone: every item becomes a card (name, unit + qty, actions), no sideways scrolling. */
@media (max-width: 720px) {
    #recipe-items {
        padding: 14px;
    }

    .items-table,
    .items-table tbody {
        display: block;
    }

    .items-table thead {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip: rect(0 0 0 0);
        white-space: nowrap;
    }

    .items-table tr {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        grid-template-areas: "ing ing" "unit qty" "act act";
        gap: 8px 12px;
        padding: 14px 0;
        border-bottom: 1px solid #e5e7eb;
    }

    .items-table tr.is-editing {
        grid-template-areas: "ing ing" "unit unit" "qty qty" "act act";
    }

    .items-table td {
        display: block;
        padding: 0;
        border: 0;
        min-width: 0;
    }

    .items-table .c-ing { grid-area: ing; }
    .items-table .c-unit { grid-area: unit; }
    .items-table .c-qty { grid-area: qty; }
    .items-table .c-act { grid-area: act; }

    .items-table td[data-label]::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 2px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #64748b;
    }

    .items-table td.c-ing::before,
    .items-table td.c-act::before {
        display: none;
    }

    .items-table td[data-label]::before {
        line-height: 16px;
    }

    .items-table .c-unit,
    .items-table .qty-text {
        font-size: 15px;
        line-height: 24px;
    }

    .items-table .c-unit {
        font-weight: 600;
    }

    .items-table .qty-container {
        min-height: 24px;
    }

    .items-table tr.is-editing .qty-container {
        min-height: 0;
    }

    .items-table .action-buttons {
        display: flex;
        gap: 8px;
    }

    .items-table .action-buttons > .btn,
    .items-table .action-buttons > form {
        flex: 1 1 0;
    }

    .items-table .action-buttons .btn {
        width: 100%;
    }

    .items-table .qty-form {
        width: 100%;
    }

    .items-table .qty-input {
        flex: 1 1 100%;
    }

    .items-table .qty-form .btn {
        flex: 1 1 0;
    }
}

</style>


<script>

function openQty(button){

    const row = button.closest('tr');
    const container = row.querySelector('.qty-container');

    container.classList.add('editing');
    row.classList.add('is-editing');

    const input = container.querySelector('.qty-input');
    if (input) {
        input.focus();
        input.select();
    }

}


function closeQty(button){

    const container = button.closest('.qty-container');

    container.classList.remove('editing');

    const row = container.closest('tr');
    if (row) {
        row.classList.remove('is-editing');
    }

}

</script>

    @include('backoffice.partials.feedback')
@include('backoffice.partials.button-system')
</body>
</html>
