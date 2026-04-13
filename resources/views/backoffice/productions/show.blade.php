<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Detail - Back Office ATG POS</title>
    <style>
        :root {
            --text: #111827;
            --muted: #6b7280;
            --brand: #e86a3a;
            --green: #166534;
            --green-soft: #e8fff1;
            --blue: #2563eb;
            --blue-soft: #eff6ff;
            --orange-soft: #fff7ed;
            --orange-text: #c2410c;
            --shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
            color: var(--text);
        }

        .page { min-height: 100vh; padding: 24px; }
        .shell {
            max-width: 1360px;
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
        .title {
            margin: 0;
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -0.03em;
        }
        .subtitle {
            margin-top: 10px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
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
        }
        .btn-dark { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); }

        .content { padding: 22px 28px 30px; }

        .alert {
            margin-bottom: 18px;
            border-radius: 16px;
            padding: 15px 18px;
            font-size: 14px;
            line-height: 1.7;
            font-weight: 700;
            background: #e8fff1;
            color: #17663a;
            border: 1px solid #ccefd8;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
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

        .summary-line {
            font-size: 14px;
            line-height: 1.9;
            margin-bottom: 8px;
            color: #374151;
        }

        .label {
            color: #6b7280;
            font-weight: 700;
            margin-right: 6px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .badge-output {
            background: var(--orange-soft);
            color: var(--orange-text);
        }

        .badge-input {
            background: var(--blue-soft);
            color: #1d4ed8;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 960px;
            border-collapse: collapse;
            background: white;
            border: 1px solid #e8edf4;
            border-radius: 18px;
            overflow: hidden;
        }

        th, td {
            text-align: left;
            padding: 16px 14px;
            border-bottom: 1px solid #edf1f6;
            vertical-align: middle;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            color: #6b7280;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        @media (max-width: 980px) {
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="page">
    <div class="shell">
        <div class="topbar">
            <div>
                <h1 class="title">Detail Produksi</h1>
                <div class="subtitle">
                    Detail transaksi produksi yang sudah menambah stok output dan mengurangi stok bahan input.
                </div>
            </div>

            <a href="{{ route('backoffice.productions.index') }}" class="btn btn-dark">Kembali</a>
        </div>

        <div class="content">
            @if(session('success'))
                <div class="alert">{{ session('success') }}</div>
            @endif

            <div class="grid-2">
                <div class="card">
                    <div class="card-head">
                        <h2 class="card-title">Header Produksi</h2>
                        <p class="card-subtitle">Informasi utama transaksi produksi.</p>
                    </div>

                    <div class="card-body">
                        <div class="summary-line"><span class="label">Production ID:</span>#{{ $production->id }}</div>
                        <div class="summary-line"><span class="label">Tanggal:</span>{{ $production->produced_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                        <div class="summary-line"><span class="label">Recipe:</span>{{ $production->recipe->name ?? '-' }}</div>
                        <div class="summary-line"><span class="label">Location:</span>{{ ucfirst($production->location_type) }} · {{ $production->location_name }}</div>
                        <div class="summary-line"><span class="label">Batch Qty:</span>{{ number_format((float) $production->batch_qty, 2, ',', '.') }}</div>
                        <div class="summary-line"><span class="label">Produced By:</span>{{ $production->producedBy->name ?? '-' }}</div>
                        <div class="summary-line"><span class="label">Status:</span>{{ ucfirst($production->status ?? 'completed') }}</div>
                        <div class="summary-line"><span class="label">Note:</span>{{ $production->note ?? '-' }}</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head">
                        <h2 class="card-title">Output Produksi</h2>
                        <p class="card-subtitle">Ringkasan hasil produksi yang masuk ke stok.</p>
                    </div>

                    <div class="card-body">
                        <div class="summary-line">
                            <span class="label">Output Ingredient:</span>
                            <span class="badge badge-output">{{ $production->outputIngredient->name ?? '-' }}</span>
                        </div>
                        <div class="summary-line"><span class="label">Output Qty:</span>{{ number_format((float) $production->output_qty, 2, ',', '.') }} {{ $production->output_unit }}</div>
                        <div class="summary-line"><span class="label">Reference Type:</span>ingredient_production</div>
                        <div class="summary-line"><span class="label">Reference ID:</span>#{{ $production->id }}</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h2 class="card-title">Production Items</h2>
                    <p class="card-subtitle">Semua bahan input dan output yang tercatat dalam transaksi ini.</p>
                </div>

                <div class="card-body">
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Item Type</th>
                                <th>Ingredient</th>
                                <th>Category</th>
                                <th>Qty</th>
                                <th>Unit</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($production->items as $item)
                                <tr>
                                    <td>
                                        @if($item->item_type === 'output')
                                            <span class="badge badge-output">Output</span>
                                        @else
                                            <span class="badge badge-input">Input</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->ingredient->name ?? '-' }}</td>
                                    <td>{{ $item->ingredient->category->name ?? '-' }}</td>
                                    <td>{{ number_format((float) $item->qty, 2, ',', '.') }}</td>
                                    <td>{{ $item->unit }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>