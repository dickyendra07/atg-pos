<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Variant - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 940px;
            margin: 40px auto;
            padding: 0 20px 40px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 12px;
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

        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
        }

        .error-box {
            margin-bottom: 18px;
            background: #ffe8e8;
            color: #9b1c1c;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
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

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .note {
            margin-top: 20px;
            background: #eef2ff;
            color: #3730a3;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
            line-height: 1.7;
        }

        @media (max-width: 720px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title">Tambah Variant</div>
            <a href="{{ route('backoffice.variants.index') }}" class="btn">Kembali</a>
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

        <div class="card">
            <form method="POST" action="{{ route('backoffice.variants.store') }}">
                @csrf

                <div class="field">
                    <label>Product</label>
                    <select name="product_id" required>
                        <option value="">Pilih product</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                                {{ $product->name }} - {{ $product->brand->name ?? '-' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label>Variant Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required>
                </div>

                <div class="field">
                    <label>Code</label>
                    <input type="text" name="code" value="{{ old('code') }}" required>
                </div>

                <div class="grid">
                    <div class="field">
                        <label>Price Dine In</label>
                        <input type="number" name="price_dine_in" min="0" step="0.01" value="{{ old('price_dine_in', 0) }}" required>
                    </div>

                    <div class="field">
                        <label>Price Delivery</label>
                        <input type="number" name="price_delivery" min="0" step="0.01" value="{{ old('price_delivery', old('price_dine_in', 0)) }}" required>
                    </div>
                </div>

                <div class="field">
                    <label>Status</label>
                    <select name="is_active" required>
                        <option value="1" @selected(old('is_active', '1') == '1')>Active</option>
                        <option value="0" @selected(old('is_active') == '0')>Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-success">Simpan Variant</button>
                    <a href="{{ route('backoffice.variants.index') }}" class="btn">Batal</a>
                </div>
            </form>

            <div class="note">
                Variant sekarang sudah siap untuk 2 harga: <strong>dine in</strong> dan <strong>delivery</strong>. Kalau belum ada perbedaan harga, isi saja sama dulu supaya operasional tetap aman.
            </div>
        </div>
    </div>
</body>
</html>