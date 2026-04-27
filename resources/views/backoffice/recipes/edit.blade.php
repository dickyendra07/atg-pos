<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Recipe - Back Office ATG POS</title>
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
            min-width: 760px;
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

        .grid-2 {
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            gap: 20px;
            align-items: start;
        }

        .right-stack {
            display: flex;
            flex-direction: column;
            gap: 20px;
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

        @media (max-width: 980px) {
            .grid-2 {
                grid-template-columns: 1fr;
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
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title">Edit Recipe</div>
            <a href="{{ route('backoffice.recipes.index') }}" class="btn">Kembali</a>
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

        @if(session('success'))
            <div class="success-box">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="error-box">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid-2">
            <div class="card">
                <div class="section-title">Header Recipe</div>

                <div class="info">
                    <strong>User:</strong> {{ $user->name }}<br>
                    <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                    <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}
                </div>

                <form method="POST" action="{{ route('backoffice.recipes.update', $recipe->id) }}">
                    @csrf
                    @method('PUT')

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
                        <input type="text" name="name" value="{{ old('name', $recipe->name) }}" required>
                    </div>

                    <div class="field">
                        <label>Status</label>
                        <select name="is_active" required>
                            <option value="1" @selected(old('is_active', (string) $recipe->is_active) == '1')>Active</option>
                            <option value="0" @selected(old('is_active', (string) $recipe->is_active) == '0')>Inactive</option>
                        </select>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn btn-success">Update Header</button>
                    </div>
                </form>
            </div>

            <div class="right-stack">
                <div class="card">
                    <div class="section-title">Daftar Recipe Items</div>

                    @if($recipe->items->count())
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Ingredient</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Unit</th>
                                        <th>Qty</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recipe->items as $item)
                                        @php
                                            $ingredientType = $item->ingredient?->ingredient_type;
                                            $ingredientTypeLabel = $item->ingredient?->ingredientTypeLabel() ?? 'Mentah';
                                        @endphp
                                        <tr>
                                            <td>{{ $item->ingredient->name ?? '-' }}</td>
                                            <td>{{ $item->ingredient->category->name ?? '-' }}</td>
                                            <td>
                                                @if($ingredientType === \App\Models\Ingredient::TYPE_SEMI_FINISHED)
                                                    <span class="badge badge-semi">{{ $ingredientTypeLabel }}</span>
                                                @else
                                                    <span class="badge badge-raw">{{ $ingredientTypeLabel }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $item->unit ?? $item->ingredient->unit ?? '-' }}</td>
                                            <td>{{ number_format((float) $item->qty, 2, ',', '.') }}</td>
                                            <td>
                                                <form method="POST" action="{{ route('backoffice.recipes.items.destroy', [$recipe->id, $item->id]) }}" class="inline-form" onsubmit="return confirm('Yakin mau hapus recipe item ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-danger">Hapus</button>
                                                </form>
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

                <div class="card">
                    <div class="section-title">Tambah Bahan Recipe</div>

                    <form method="POST" action="{{ route('backoffice.recipes.items.store', $recipe->id) }}">
                        @csrf

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
                        </div>

                        <div class="field">
                            <label>Qty</label>
                            <input type="number" name="qty" min="0.01" step="0.01" required>
                        </div>

                        <div class="actions">
                            <button type="submit" class="btn btn-success">Tambah Recipe Item</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>