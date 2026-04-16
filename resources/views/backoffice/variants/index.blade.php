<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Variants - Back Office ATG POS</title>
    <style>
        :root {
            --bg: #f4f6fb;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --navy: #111827;
            --green: #166534;
            --green-soft: #eefaf1;
            --orange: #e86a3a;
            --orange-soft: #fff5ef;
            --blue: #2563eb;
            --blue-soft: #eef4ff;
            --red: #dc2626;
            --red-soft: #fff1f1;
            --surface: #ffffff;
            --shadow: 0 12px 30px rgba(0,0,0,0.06);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .wrap {
            max-width: 1500px;
            margin: 36px auto;
            padding: 0 20px 40px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .title-block {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .title {
            font-size: 34px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.02em;
        }

        .subtitle {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.7;
            max-width: 760px;
        }

        .top-actions {
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
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.96;
        }

        .btn-dark {
            background: var(--navy);
        }

        .btn-green {
            background: var(--green);
        }

        .btn-blue {
            background: var(--blue);
        }

        .btn-red {
            background: var(--red);
        }

        .btn-orange {
            background: var(--orange);
        }

        .card {
            background: var(--surface);
            border-radius: 22px;
            box-shadow: var(--shadow);
            padding: 24px;
        }

        .alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.6;
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

        .error-list {
            margin-top: 10px;
            padding-left: 18px;
            font-weight: normal;
        }

        .error-list li {
            margin-bottom: 6px;
            line-height: 1.5;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .summary-card {
            border-radius: 18px;
            padding: 18px;
            border: 1px solid transparent;
        }

        .summary-orange {
            background: var(--orange-soft);
            border-color: #f8dbc9;
        }

        .summary-green {
            background: #f3fff7;
            border-color: #d5efdf;
        }

        .summary-blue {
            background: var(--blue-soft);
            border-color: #dbe5ff;
        }

        .summary-dark {
            background: #f8fafc;
            border-color: #e5e7eb;
        }

        .summary-label {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
            margin-bottom: 10px;
        }

        .summary-value {
            font-size: 30px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 8px;
        }

        .summary-desc {
            font-size: 13px;
            line-height: 1.6;
            color: var(--muted);
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 18px;
            margin-top: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1400px;
            background: white;
        }

        th, td {
            text-align: left;
            padding: 15px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background: #f9fafb;
            font-size: 12px;
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .variant-name {
            font-weight: 800;
            color: var(--text);
        }

        .code-pill {
            display: inline-flex;
            align-items: center;
            padding: 7px 10px;
            border-radius: 999px;
            background: #f3f4f6;
            color: #374151;
            font-size: 12px;
            font-weight: 800;
        }

        .money {
            font-weight: 800;
            color: var(--blue);
            white-space: nowrap;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
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
            gap: 8px;
            flex-wrap: wrap;
            min-width: 160px;
        }

        .btn-small {
            min-height: 34px;
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 10px;
        }

        .inline-form {
            display: inline-block;
            margin: 0;
        }

        .empty {
            padding: 16px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 12px;
            margin-top: 16px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }

        .note {
            margin-top: 20px;
            background: #eef2ff;
            color: #3730a3;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: 700;
            border: 1px solid #dbe3ff;
            line-height: 1.7;
        }

        @media (max-width: 1100px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 760px) {
            .topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .top-actions {
                flex-wrap: wrap;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .title {
                font-size: 28px;
            }

            .wrap {
                padding: 0 14px 28px;
            }

            .card {
                padding: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title-block">
                <div class="title">Back Office - Variants</div>
                <div class="subtitle">
                    Kelola variant produk dan siapkan harga <strong>dine in</strong> serta <strong>delivery</strong> dalam satu halaman tanpa kolom harga lama yang dobel.
                </div>
            </div>

            <div class="top-actions">
                <a href="{{ route('backoffice.variants.export.csv') }}" class="btn btn-blue">Export CSV</a>
                <a href="{{ route('backoffice.variants.import') }}" class="btn btn-orange">Import CSV</a>
                <a href="{{ route('backoffice.variants.create') }}" class="btn btn-green">Tambah Variant</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Kembali</a>
            </div>
        </div>

        <div class="card">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

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

            <div class="summary-grid">
                <div class="summary-card summary-orange">
                    <div class="summary-label">Total Variants</div>
                    <div class="summary-value">{{ $variants->count() }}</div>
                    <div class="summary-desc">Jumlah seluruh variant yang terdaftar di sistem.</div>
                </div>

                <div class="summary-card summary-green">
                    <div class="summary-label">Active Variants</div>
                    <div class="summary-value">{{ $variants->where('is_active', true)->count() }}</div>
                    <div class="summary-desc">Variant aktif yang siap dijual di cashier.</div>
                </div>

                <div class="summary-card summary-blue">
                    <div class="summary-label">Delivery Ready</div>
                    <div class="summary-value">{{ $variants->filter(fn($v) => !is_null($v->price_delivery))->count() }}</div>
                    <div class="summary-desc">Variant yang sudah punya harga delivery.</div>
                </div>

                <div class="summary-card summary-dark">
                    <div class="summary-label">Dine In Ready</div>
                    <div class="summary-value">{{ $variants->filter(fn($v) => !is_null($v->price_dine_in))->count() }}</div>
                    <div class="summary-desc">Variant yang sudah punya harga dine in.</div>
                </div>
            </div>

            @if($variants->count())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Brand</th>
                                <th>Category</th>
                                <th>Product</th>
                                <th>Variant</th>
                                <th>Code</th>
                                <th>Price Dine In</th>
                                <th>Price Delivery</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($variants as $variant)
                                <tr>
                                    <td>{{ $variant->product->brand->name ?? '-' }}</td>
                                    <td>{{ $variant->product->category->name ?? '-' }}</td>
                                    <td>{{ $variant->product->name ?? '-' }}</td>
                                    <td class="variant-name">{{ $variant->name ?? '-' }}</td>
                                    <td>
                                        <span class="code-pill">{{ $variant->code ?? '-' }}</span>
                                    </td>
                                    <td class="money">Rp{{ number_format((float) ($variant->price_dine_in ?? $variant->price ?? 0), 0, ',', '.') }}</td>
                                    <td class="money">Rp{{ number_format((float) ($variant->price_delivery ?? $variant->price ?? 0), 0, ',', '.') }}</td>
                                    <td>
                                        @if($variant->is_active)
                                            <span class="status-pill status-active">Active</span>
                                        @else
                                            <span class="status-pill status-inactive">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="action-stack">
                                            <a href="{{ route('backoffice.variants.edit', $variant->id) }}" class="btn btn-blue btn-small">Edit</a>

                                            <form method="POST" action="{{ route('backoffice.variants.destroy', $variant->id) }}" class="inline-form" onsubmit="return confirm('Yakin hapus variant ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-red btn-small">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">
                    Belum ada variant tersimpan.
                </div>
            @endif

            <div class="note">
                Variants sekarang sudah punya Export & Import CSV. Tampilan tetap fokus ke <strong>harga dine in</strong> dan <strong>harga delivery</strong>.
            </div>
        </div>
    </div>
</body>
</html>