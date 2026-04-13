<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Ingredients CSV - Back Office ATG POS</title>
    <style>
        :root {
            --bg: #f3f6fb;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --brand: #e86a3a;
            --brand-dark: #c9552a;
            --brand-soft: #fff3eb;
            --green: #166534;
            --green-soft: #e8fff1;
            --blue: #3730a3;
            --blue-soft: #eef2ff;
            --red: #9b1c1c;
            --red-soft: #ffe8e8;
            --shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
            color: var(--text);
        }

        .page {
            min-height: 100vh;
            padding: 24px;
        }

        .shell {
            max-width: 1100px;
            margin: 0 auto;
            background: rgba(255,255,255,0.62);
            border: 1px solid rgba(255,255,255,0.9);
            border-radius: 30px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            padding: 28px 28px 0;
        }

        .title-wrap {
            max-width: 680px;
        }

        .title {
            margin: 0;
            font-size: 32px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.03em;
        }

        .subtitle {
            margin-top: 10px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            border: 0;
            cursor: pointer;
            color: white;
            padding: 11px 16px;
            border-radius: 14px;
            font-weight: 800;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            box-shadow: 0 10px 22px rgba(15,23,42,0.10);
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.97;
        }

        .btn-success {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
        }

        .btn-dark {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .btn-primary {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        }

        .content {
            padding: 22px 28px 30px;
        }

        .alert {
            margin-bottom: 18px;
            border-radius: 16px;
            padding: 15px 18px;
            font-size: 14px;
            line-height: 1.7;
            border: 1px solid transparent;
        }

        .alert-success {
            background: var(--green-soft);
            color: #17663a;
            border-color: #ccefd8;
            font-weight: 700;
        }

        .alert-error {
            background: var(--red-soft);
            color: var(--red);
            border-color: #fecaca;
            font-weight: 700;
        }

        .hero-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff9f5 70%, #fff1ea 100%);
            border: 1px solid #f0e1d8;
            border-radius: 28px;
            padding: 24px;
            margin-bottom: 22px;
            position: relative;
            overflow: hidden;
        }

        .hero-card::after {
            content: "";
            position: absolute;
            right: -50px;
            top: -50px;
            width: 180px;
            height: 180px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(232,106,58,0.14) 0%, rgba(232,106,58,0.03) 65%, rgba(232,106,58,0) 80%);
            pointer-events: none;
        }

        .hero-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #f2dfd4;
            color: var(--brand-dark);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 14px;
            position: relative;
            z-index: 1;
        }

        .hero-heading {
            margin: 0 0 10px;
            font-size: 32px;
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -0.03em;
            position: relative;
            z-index: 1;
        }

        .hero-text {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
            max-width: 760px;
            position: relative;
            z-index: 1;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 0.95fr;
            gap: 22px;
        }

        .card {
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-head {
            padding: 22px 22px 0;
        }

        .card-title {
            margin: 0 0 8px;
            font-size: 22px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .card-subtitle {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .card-body {
            padding: 0 22px 22px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #374151;
        }

        .field input[type="file"] {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            padding: 12px 13px;
            font-size: 14px;
            background: white;
            color: #111827;
            min-height: 50px;
        }

        .actions-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .template-box {
            background: var(--blue-soft);
            border: 1px solid #dbe3ff;
            color: var(--blue);
            border-radius: 18px;
            padding: 18px;
            font-weight: 700;
        }

        .template-title {
            font-size: 15px;
            margin-bottom: 10px;
        }

        .template-subtitle {
            margin-top: 16px;
            margin-bottom: 10px;
            font-size: 15px;
        }

        code {
            display: block;
            background: white;
            color: #111827;
            padding: 12px;
            border-radius: 12px;
            border: 1px solid #d1d5db;
            white-space: pre-wrap;
            line-height: 1.7;
            font-size: 13px;
            font-weight: 700;
        }

        .helper {
            margin-top: 14px;
            color: #4b5563;
            font-size: 13px;
            line-height: 1.8;
            font-weight: 500;
        }

        .error-list {
            margin: 0;
            padding-left: 18px;
        }

        .error-list li {
            margin-bottom: 6px;
        }

        .note {
            margin-top: 18px;
            background: #fff7ed;
            color: #9a3412;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #fed7aa;
            line-height: 1.7;
        }

        @media (max-width: 900px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .actions {
                width: 100%;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .page {
                padding: 14px;
            }

            .topbar,
            .content {
                padding-left: 18px;
                padding-right: 18px;
            }

            .hero-heading {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="shell">
            <div class="topbar">
                <div class="title-wrap">
                    <h1 class="title">Import Ingredients CSV</h1>
                    <div class="subtitle">
                        Upload bahan baku dari sistem lama ke master ingredients yang baru. Halaman ini sudah disiapkan supaya proses migrasi lebih cepat dan lebih rapi.
                    </div>
                </div>

                <div class="actions">
                    <a href="{{ route('backoffice.ingredients.import.template') }}" class="btn btn-success">Download Template CSV</a>
                    <a href="{{ route('backoffice.ingredients.index') }}" class="btn btn-dark">Kembali ke Ingredients</a>
                </div>
            </div>

            <div class="content">
                @if(session('error'))
                    <div class="alert alert-error">
                        {{ session('error') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-error">
                        <ul class="error-list">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="hero-card">
                    <div class="hero-kicker">Ingredients Import</div>
                    <h2 class="hero-heading">Pastikan master bahan rapi dulu sebelum lanjut ke opening stock.</h2>
                    <p class="hero-text">
                        Urutan yang paling aman adalah import ingredients lebih dulu, baru setelah itu import opening stock. Dengan begitu nama bahan, kategori, unit, dan stock awal akan tetap sinkron.
                    </p>
                </div>

                <div class="grid">
                    <div class="card">
                        <div class="card-head">
                            <h2 class="card-title">Upload CSV</h2>
                            <p class="card-subtitle">
                                Pilih file CSV sesuai template. Sistem akan skip baris yang ingredient-nya sudah ada.
                            </p>
                        </div>

                        <div class="card-body">
                            <form method="POST" action="{{ route('backoffice.ingredients.import.store') }}" enctype="multipart/form-data">
                                @csrf

                                <div class="field">
                                    <label>Upload File CSV</label>
                                    <input type="file" name="file" accept=".csv,text/csv" required>
                                </div>

                                <div class="actions-row">
                                    <button type="submit" class="btn btn-primary">Import Ingredients</button>
                                    <a href="{{ route('backoffice.ingredients.index') }}" class="btn btn-dark">Batal</a>
                                </div>
                            </form>

                            <div class="note">
                                Tips: kalau import dari sistem lama, pastikan header CSV persis sama dengan template dan nama kolom tidak diubah.
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-head">
                            <h2 class="card-title">Template & Format</h2>
                            <p class="card-subtitle">
                                Pakai format ini supaya file selalu sesuai dengan sistem import ingredients yang sekarang.
                            </p>
                        </div>

                        <div class="card-body">
                            <div class="template-box">
                                <div class="template-title">Header CSV wajib:</div>
                                <code>name,category_name,unit,minimum_stock,cost_per_unit,is_active</code>

                                <div class="template-subtitle">Contoh isi:</div>
                                <code>Fresh Milk,Milk,ml,2000,18000,1
Liquid Sugar,Sweetener,ml,1000,12000,1
Black Tea,Tea Base,gram,500,800,1</code>

                                <div class="helper">
                                    `is_active` gunakan nilai <strong>1</strong> untuk aktif dan <strong>0</strong> untuk nonaktif.  
                                    Kalau category belum ada, sistem akan membuat category baru otomatis.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(session('import_errors') && count(session('import_errors')))
                    <div class="card" style="margin-top:22px;">
                        <div class="card-head">
                            <h2 class="card-title" style="color:#9b1c1c;">Detail Baris yang Dilewati</h2>
                            <p class="card-subtitle">
                                Beberapa baris tidak masuk karena sudah ada sebelumnya atau ada data yang tidak valid.
                            </p>
                        </div>

                        <div class="card-body">
                            <div class="alert alert-error" style="margin-bottom:0;">
                                <ul class="error-list">
                                    @foreach(session('import_errors') as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</body>
</html>