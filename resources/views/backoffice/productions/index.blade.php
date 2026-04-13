<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productions - Back Office ATG POS</title>
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
            max-width: 1440px;
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
            max-width: 760px;
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
        }
        .btn-primary { background: linear-gradient(135deg, #166534 0%, #1f7a44 100%); }
        .btn-dark { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); }

        .content { padding: 22px 28px 30px; }

        .alert {
            margin-bottom: 18px;
            border-radius: 16px;
            padding: 15px 18px;
            font-size: 14px;
            line-height: 1.7;
            font-weight: 700;
        }
        .alert-success {
            background: #e8fff1;
            color: #17663a;
            border: 1px solid #ccefd8;
        }

        .hero-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff9f5 70%, #fff1ea 100%);
            border: 1px solid #f0e1d8;
            border-radius: 28px;
            padding: 24px;
            margin-bottom: 22px;
        }

        .hero-kicker {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.84);
            border: 1px solid #f2dfd4;
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .hero-heading {
            margin: 0 0 10px;
            font-size: 34px;
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -0.03em;
        }

        .hero-text {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .table-card {
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-head {
            padding: 22px 22px 0;
        }

        .table-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 800;
        }

        .table-subtitle {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.8;
        }

        .table-wrap {
            padding: 0 22px 22px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1180px;
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

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .badge-output {
            background: var(--orange-soft);
            color: var(--orange-text);
        }

        .badge-status {
            background: var(--green-soft);
            color: #17663a;
        }

        .btn-small {
            min-height: 38px;
            padding: 0 14px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 800;
            color: white;
            text-decoration: none;
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
        }

        .empty {
            margin: 0 22px 22px;
            padding: 18px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 16px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="shell">
        <div class="topbar">
            <div>
                <h1 class="title">Back Office · Productions</h1>
                <div class="subtitle">
                    Histori produksi stok bahan mentah menjadi bahan setengah jadi. Semua transaksi di sini langsung memengaruhi stock balance dan stock movement.
                </div>
            </div>

            <div class="actions">
                <a href="{{ route('backoffice.productions.create') }}" class="btn btn-primary">Buat Produksi</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Kembali</a>
            </div>
        </div>

        <div class="content">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="hero-card">
                <div class="hero-kicker">Batch 3 · Productions</div>
                <h2 class="hero-heading">Eksekusi produksi stok raw ke semi-finished dalam satu flow.</h2>
                <p class="hero-text">
                    Saat produksi disimpan, stok bahan mentah akan berkurang, stok output setengah jadi akan bertambah, dan movement produksi akan langsung tercatat.
                </p>
            </div>

            <div class="table-card">
                <div class="table-head">
                    <h2 class="table-title">Production History</h2>
                    <p class="table-subtitle">
                        Gunakan histori ini untuk audit kapan produksi dijalankan, siapa yang menjalankan, di lokasi mana, dan output apa yang dihasilkan.
                    </p>
                </div>

                @if($productions->count())
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Recipe</th>
                                <th>Output</th>
                                <th>Location</th>
                                <th>Batch Qty</th>
                                <th>Output Qty</th>
                                <th>Produced By</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($productions as $production)
                                <tr>
                                    <td>{{ $production->produced_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                    <td>{{ $production->recipe->name ?? '-' }}</td>
                                    <td>
                                        <span class="badge badge-output">
                                            {{ $production->outputIngredient->name ?? '-' }}
                                        </span>
                                    </td>
                                    <td>{{ ucfirst($production->location_type) }} · {{ $production->location_name }}</td>
                                    <td>{{ number_format((float) $production->batch_qty, 2, ',', '.') }}</td>
                                    <td>{{ number_format((float) $production->output_qty, 2, ',', '.') }} {{ $production->output_unit }}</td>
                                    <td>{{ $production->producedBy->name ?? '-' }}</td>
                                    <td><span class="badge badge-status">{{ ucfirst($production->status ?? 'completed') }}</span></td>
                                    <td>
                                        <a href="{{ route('backoffice.productions.show', $production->id) }}" class="btn-small">Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty">
                        Belum ada histori produksi.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
</body>
</html>