@extends('backoffice.layouts.app')

@section('content')
    <style>
        .import-shell {
            display: grid;
            gap: 22px;
        }

        .import-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            flex-wrap: wrap;
        }

        .import-title {
            margin: 0;
            font-size: 34px;
            font-weight: 900;
            color: #111827;
            letter-spacing: -0.04em;
        }

        .import-subtitle {
            margin-top: 8px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
            max-width: 760px;
            font-weight: 700;
        }

        .import-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .import-card {
            background: #ffffff;
            border: 1px solid #e8edf4;
            border-radius: 26px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .import-card-head {
            padding: 22px 22px 0;
        }

        .import-card-title {
            margin: 0 0 8px;
            font-size: 22px;
            font-weight: 900;
            color: #111827;
        }

        .import-card-subtitle {
            margin: 0 0 18px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
            font-weight: 700;
        }

        .import-card-body {
            padding: 22px;
        }

        .import-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(320px, 0.65fr);
            gap: 18px;
            align-items: start;
        }

        .import-field {
            display: grid;
            gap: 8px;
            margin-bottom: 16px;
        }

        .import-field label {
            font-size: 12px;
            font-weight: 900;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .import-field input[type="file"] {
            width: 100%;
            min-height: 54px;
            border: 1px solid #d9e1ec;
            border-radius: 16px;
            padding: 14px 16px;
            background: #ffffff;
            color: #111827;
            font-size: 14px;
            font-weight: 800;
        }

        .import-info {
            padding: 16px;
            border-radius: 18px;
            background: #f8fafc;
            border: 1px solid #e8edf4;
            color: #374151;
            line-height: 1.7;
            font-size: 14px;
            font-weight: 700;
        }

        .template-box {
            padding: 16px;
            border-radius: 18px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 13px;
            line-height: 1.7;
            font-weight: 700;
        }

        .template-title {
            color: #111827;
            font-size: 13px;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .template-box code {
            display: block;
            white-space: pre-wrap;
            background: rgba(255,255,255,0.8);
            border: 1px solid #fed7aa;
            border-radius: 12px;
            padding: 10px;
            color: #111827;
            font-size: 12px;
            margin: 8px 0 12px;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .import-alert {
            border-radius: 18px;
            padding: 15px 18px;
            line-height: 1.7;
            font-size: 14px;
            font-weight: 800;
            border: 1px solid transparent;
        }

        .import-alert.error {
            background: #ffe8e8;
            color: #9b1c1c;
            border-color: #fecaca;
        }

        @media (max-width: 980px) {
            .import-grid {
                grid-template-columns: 1fr;
            }

            .import-title {
                font-size: 30px;
            }
        }
    </style>

    <div class="import-shell">
        <div class="import-topbar">
            <div>
                <h1 class="import-title">Import Ingredients</h1>
                <div class="import-subtitle">
                    Upload data ingredients dari CSV template. Gunakan import ini untuk menambah atau memperbarui master bahan baku.
                </div>
            </div>

            <div class="import-actions">
                <a href="{{ route('backoffice.ingredients.import.template') }}" class="btn btn-green">Download Template CSV</a>
                <a href="{{ route('backoffice.ingredients.index') }}" class="btn btn-dark">Kembali ke Ingredients</a>
            </div>
        </div>

        @if(session('error'))
            <div class="import-alert error">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="import-alert error">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="import-grid">
            <div class="import-card">
                <div class="import-card-head">
                    <h2 class="import-card-title">Upload File</h2>
                    <p class="import-card-subtitle">
                        Pilih file CSV sesuai template. Sistem akan validasi header dan menampilkan detail baris yang gagal jika ada.
                    </p>
                </div>

                <div class="import-card-body">
                    <form method="POST" action="{{ route('backoffice.ingredients.import.store') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="import-field">
                            <label>Upload File CSV</label>
                            <input type="file" name="file" accept=".csv,text/csv" required>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-orange">Import Ingredients</button>
                            <a href="{{ route('backoffice.ingredients.index') }}" class="btn btn-dark">Batal</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="import-card">
                <div class="import-card-head">
                    <h2 class="import-card-title">Format Template</h2>
                    <p class="import-card-subtitle">
                        Header CSV harus sama persis dengan template supaya data bisa dibaca sistem.
                    </p>
                </div>

                <div class="import-card-body">
                    <div class="import-info" style="margin-bottom:14px;">
                        <strong>User:</strong> {{ $user->name }}<br>
                        <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                        <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}
                    </div>

                    <div class="template-box">
                        <div class="template-title">Template header CSV wajib:</div>
                        <code>name,category_name,unit,ingredient_type,minimum_stock,cost_per_unit,is_active</code>

                        <div class="template-title">Contoh isi:</div>
                        <code>Black Tea,Tea,gram,raw,1000,250,1
Liquid Sugar,Syrup,ml,raw,1000,120,1
Brown Sugar Syrup,Syrup,ml,semi_finished,500,180,1</code>

                        <div>
                            Pakai tombol <strong>Download Template CSV</strong> supaya format file selalu sesuai sistem.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
