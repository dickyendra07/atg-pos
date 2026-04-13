<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Recipe - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 980px;
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

        .title-block {
            max-width: 680px;
        }

        .title {
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #111827;
        }

        .subtitle {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.7;
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
            margin-bottom: 20px;
        }

        .hero-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff9f5 70%, #fff1ea 100%);
            border: 1px solid #f0e1d8;
        }

        .hero-kicker {
            display: inline-block;
            background: rgba(255,255,255,0.84);
            border: 1px solid #f2dfd4;
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 8px 12px;
            border-radius: 999px;
            margin-bottom: 14px;
        }

        .hero-title {
            font-size: 28px;
            font-weight: 800;
            line-height: 1.15;
            margin: 0 0 10px;
            color: #111827;
        }

        .hero-text {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
            line-height: 1.8;
            max-width: 760px;
        }

        .info {
            margin-bottom: 18px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            line-height: 1.8;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 6px;
            color: #374151;
        }

        .field input,
        .field select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px;
            font-size: 14px;
            background: white;
            color: #111827;
        }

        .field input:focus,
        .field select:focus {
            outline: none;
            border-color: #e86a3a;
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .helper {
            margin-top: 6px;
            font-size: 12px;
            line-height: 1.6;
            color: #6b7280;
        }

        .error-box {
            margin-bottom: 18px;
            background: #ffe8e8;
            color: #9b1c1c;
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

        .note {
            margin-top: 20px;
            background: #eef2ff;
            color: #3730a3;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
            line-height: 1.7;
        }

        .tip-list {
            margin: 12px 0 0 18px;
            padding: 0;
            font-weight: normal;
        }

        .tip-list li {
            margin-bottom: 8px;
            line-height: 1.6;
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
                font-size: 26px;
            }

            .hero-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title-block">
                <div class="title">Tambah Recipe</div>
                <div class="subtitle">
                    Buat header recipe jual dulu, lalu lanjut isi komponen bahan di halaman edit recipe.
                </div>
            </div>

            <a href="{{ route('backoffice.recipes.index') }}" class="btn">Kembali</a>
        </div>

        @if($errors->any())
            <div class="error-box">
                <div>Form belum valid:</div>
                <ul style="margin:10px 0 0 18px;">
                    @foreach($errors->all() as $error)
                        <li style="margin-bottom:6px;">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card hero-card">
            <div class="hero-kicker">Batch 4 Recipe Hybrid</div>
            <h2 class="hero-title">Recipe jual sekarang bisa disiapkan untuk campuran raw dan semi-finished.</h2>
            <p class="hero-text">
                Di langkah ini kamu buat dulu header recipe untuk variant yang dijual. Setelah recipe tersimpan,
                kamu akan lanjut ke halaman edit untuk menambahkan item bahan yang bisa berupa bahan mentah,
                bahan setengah jadi, atau campuran keduanya.
            </p>
        </div>

        <div class="card">
            <div class="info">
                <strong>User:</strong> {{ $user->name }}<br>
                <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}
            </div>

            <form method="POST" action="{{ route('backoffice.recipes.store') }}">
                @csrf

                <div class="field">
                    <label>Product Variant</label>
                    <select name="product_variant_id" required>
                        <option value="">Pilih variant</option>
                        @foreach($variants as $variant)
                            <option value="{{ $variant->id }}" @selected(old('product_variant_id') == $variant->id)>
                                {{ $variant->product->name ?? '-' }} - {{ $variant->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="helper">
                        Satu variant hanya boleh punya satu recipe aktif utama.
                    </div>
                </div>

                <div class="field">
                    <label>Recipe Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="Contoh: Waffle Coklat / Es Teh Cream Base" required>
                    <div class="helper">
                        Pakai nama recipe yang jelas supaya gampang dibedakan saat nanti dikelola dan dicek deduction-nya.
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
                    <button type="submit" class="btn btn-success">Simpan Recipe</button>
                    <a href="{{ route('backoffice.recipes.index') }}" class="btn">Batal</a>
                </div>
            </form>

            <div class="note">
                Setelah header recipe berhasil dibuat, langkah berikutnya adalah masuk ke halaman <strong>Edit Recipe</strong> untuk menambahkan item bahan.
                <ul class="tip-list">
                    <li>Recipe boleh isi <strong>raw</strong> saja.</li>
                    <li>Recipe boleh isi <strong>semi-finished</strong> saja.</li>
                    <li>Recipe juga boleh <strong>campuran raw + semi-finished</strong> dalam satu recipe.</li>
                    <li>Saat checkout, sistem akan deduction berdasarkan item recipe yang kamu susun di halaman edit.</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>