<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Detail - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 1240px;
            margin: 40px auto;
            padding: 0 20px 40px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
        }

        .title-block {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .title {
            font-size: 30px;
            font-weight: 800;
            color: #111827;
        }

        .subtitle {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
        }

        .top-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            border: 0;
            cursor: pointer;
            background: #111827;
            color: white;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            font-size: 14px;
        }

        .btn-light {
            background: #e5e7eb;
            color: #111827;
        }

        .btn-danger {
            background: #b91c1c;
        }

        .btn-secondary {
            background: #6b7280;
        }

        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 16px;
            color: #111827;
        }

        .success-box,
        .error-box,
        .warning-box,
        .void-box {
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 18px;
            font-weight: 700;
            line-height: 1.7;
        }

        .success-box {
            background: #e8fff1;
            color: #17663a;
            border: 1px solid #ccefd8;
        }

        .error-box {
            background: #ffe8e8;
            color: #9b1c1c;
            border: 1px solid #fecaca;
        }

        .warning-box {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fed7aa;
        }

        .void-box {
            background: #fff1f2;
            color: #9f1239;
            border: 1px solid #fecdd3;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .info-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 14px;
            border: 1px solid #e5e7eb;
        }

        .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 6px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .value {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            line-height: 1.5;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
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
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .money {
            font-weight: 800;
            color: #1d4ed8;
        }

        .summary-box {
            margin-top: 18px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .summary-total {
            font-size: 22px;
            font-weight: 800;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .badge-normal {
            background: #e8fff1;
            color: #17663a;
        }

        .badge-blocked {
            background: #ffe8e8;
            color: #9b1c1c;
        }

        .badge-void {
            background: #fff1f2;
            color: #be123c;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #374151;
        }

        .field textarea {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 12px 13px;
            font-size: 14px;
            min-height: 120px;
            resize: vertical;
            font-family: Arial, sans-serif;
        }

        .field textarea:focus {
            outline: none;
            border-color: rgba(185,28,28,0.75);
            box-shadow: 0 0 0 4px rgba(185,28,28,0.08);
        }

        .helper {
            margin-top: 8px;
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
        }

        .note {
            background: #eef2ff;
            color: #3730a3;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: 700;
            margin-top: 18px;
            border: 1px solid #dbe3ff;
            line-height: 1.7;
        }

        @media (max-width: 900px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .top-actions {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title-block">
                <div class="title">Transaction Detail</div>
                <div class="subtitle">
                    Detail transaksi kasir lengkap dengan item, pembayaran, dan aksi void untuk koreksi transaksi yang sudah terlanjur tersimpan.
                </div>
            </div>

            <div class="top-actions">
                <a href="{{ route('backoffice.transactions.receipt', $transaction->id) }}" class="btn btn-light" target="_blank">Print Receipt</a>
                <a href="{{ route('backoffice.transactions.index') }}" class="btn">Kembali</a>
            </div>
        </div>

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

        @if($errors->any())
            <div class="error-box">
                <ul style="margin:0; padding-left:18px;">
                    @foreach($errors->all() as $error)
                        <li style="margin-bottom:6px;">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(strtolower((string) $transaction->status) === 'void')
            <div class="void-box">
                Transaksi ini sudah di-void.
                <br>
                Waktu void:
                <strong>{{ $transaction->void_at?->format('Y-m-d H:i:s') ?? '-' }}</strong>
                <br>
                Void oleh:
                <strong>{{ $transaction->voidBy->name ?? '-' }}</strong>
                <br>
                Alasan:
                <strong>{{ $transaction->void_reason ?? '-' }}</strong>
            </div>
        @endif

        <div class="card">
            <div class="section-title">Informasi Transaksi</div>

            <div class="info-grid">
                <div class="info-box">
                    <div class="label">Transaction Number</div>
                    <div class="value">{{ $transaction->transaction_number }}</div>
                </div>

                <div class="info-box">
                    <div class="label">Tanggal</div>
                    <div class="value">{{ $transaction->created_at?->format('Y-m-d H:i:s') }}</div>
                </div>

                <div class="info-box">
                    <div class="label">Kasir</div>
                    <div class="value">{{ $transaction->user->name ?? '-' }}</div>
                </div>

                <div class="info-box">
                    <div class="label">Outlet</div>
                    <div class="value">{{ $transaction->outlet->name ?? '-' }}</div>
                </div>

                <div class="info-box">
                    <div class="label">Member</div>
                    <div class="value">
                        @if($transaction->member)
                            {{ $transaction->member->name }}<br>
                            <span style="font-size:14px;font-weight:normal;">{{ $transaction->member->phone }}</span>
                        @else
                            -
                        @endif
                    </div>
                </div>

                <div class="info-box">
                    <div class="label">Status</div>
                    <div class="value">
                        @php
                            $status = strtolower((string) $transaction->status);
                        @endphp

                        @if($status === 'stock_blocked')
                            <span class="badge badge-blocked">{{ ucfirst($transaction->status) }}</span>
                        @elseif($status === 'void')
                            <span class="badge badge-void">Void</span>
                        @else
                            <span class="badge badge-normal">{{ ucfirst($transaction->status) }}</span>
                        @endif
                    </div>
                </div>

                <div class="info-box">
                    <div class="label">Payment Method</div>
                    <div class="value">{{ strtoupper($transaction->payment_method ?? '-') }}</div>
                </div>

                <div class="info-box">
                    <div class="label">Amount Paid / Change</div>
                    <div class="value">
                        Rp{{ number_format((float) $transaction->amount_paid, 0, ',', '.') }}
                        / Rp{{ number_format((float) $transaction->change_amount, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="section-title">Item Transaksi</div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Variant</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transaction->items as $item)
                            <tr>
                                <td>{{ $item->product_name ?? '-' }}</td>
                                <td>{{ $item->variant_name ?? '-' }}</td>
                                <td>{{ number_format((float) $item->qty, 0, ',', '.') }}</td>
                                <td class="money">Rp{{ number_format((float) $item->price, 0, ',', '.') }}</td>
                                <td class="money">Rp{{ number_format((float) $item->line_total, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">Belum ada item transaksi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="summary-box">
                <div class="summary-row">
                    <span>Subtotal</span>
                    <span class="money">Rp{{ number_format((float) $transaction->subtotal, 0, ',', '.') }}</span>
                </div>
                <div class="summary-row">
                    <span>Discount</span>
                    <span class="money">Rp{{ number_format((float) $transaction->discount_amount, 0, ',', '.') }}</span>
                </div>
                <div class="summary-row">
                    <span>Tax</span>
                    <span class="money">Rp{{ number_format((float) $transaction->tax_amount, 0, ',', '.') }}</span>
                </div>
                <div class="summary-row summary-total">
                    <span>Grand Total</span>
                    <span class="money">Rp{{ number_format((float) $transaction->grand_total, 0, ',', '.') }}</span>
                </div>
            </div>

            <div class="note">
                Detail transaksi ini sekarang siap dipakai juga untuk flow void. Saat transaksi di-void, stok bahan akan dikembalikan ke outlet asal transaksi.
            </div>
        </div>

        @if(strtolower((string) $transaction->status) !== 'void')
            <div class="card">
                <div class="section-title">Void Transaction</div>

                <div class="warning-box">
                    Gunakan void hanya kalau transaksi memang harus dibatalkan. Aksi ini akan:
                    <br>
                    - mengubah status transaksi menjadi <strong>void</strong>
                    <br>
                    - mengembalikan stok bahan ke outlet
                    <br>
                    - menyimpan alasan void untuk audit
                </div>

                <form method="POST" action="{{ route('backoffice.transactions.void', $transaction) }}" onsubmit="return confirm('Yakin void transaksi ini? Stok akan dikembalikan ke outlet.')">
                    @csrf

                    <div class="form-grid">
                        <div class="field">
                            <label>Alasan Void</label>
                            <textarea name="void_reason" placeholder="Contoh: salah input pesanan / transaksi dobel / customer batal" required>{{ old('void_reason') }}</textarea>
                            <div class="helper">
                                Isi alasan void dengan jelas supaya mudah ditelusuri saat audit atau review transaksi.
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:16px;">
                        <button type="submit" class="btn btn-danger">Void Transaction</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</body>
</html>