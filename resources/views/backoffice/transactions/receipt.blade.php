<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - {{ $displayReceiptNumber ?? ($transaction->transaction_number ?? '-') }}</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }

        .page {
            min-height: 100vh;
            padding: 32px 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .top-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
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
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .receipt {
            width: 100%;
            max-width: 320px;
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.08);
            padding: 20px 18px;
        }

        .brand {
            text-align: center;
            padding-bottom: 14px;
            border-bottom: 1px dashed #d1d5db;
        }

        .brand-title {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .brand-subtitle {
            font-size: 13px;
            color: #4b5563;
            line-height: 1.4;
        }

        .meta {
            padding: 14px 0;
            border-bottom: 1px dashed #d1d5db;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
            font-size: 13px;
            line-height: 1.45;
        }

        .meta-row:last-child {
            margin-bottom: 0;
        }

        .meta-label {
            color: #6b7280;
        }

        .meta-value {
            text-align: right;
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-completed {
            background: #e8fff1;
            color: #17663a;
        }

        .status-void {
            background: #ffe8e8;
            color: #9b1c1c;
        }

        .items {
            padding: 14px 0;
            border-bottom: 1px dashed #d1d5db;
        }

        .item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 12px;
        }

        .item:last-child {
            margin-bottom: 0;
        }

        .item-left {
            min-width: 0;
        }

        .item-name {
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 3px;
            line-height: 1.35;
        }

        .item-meta {
            font-size: 12px;
            color: #4b5563;
            line-height: 1.45;
        }

        .item-price {
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            text-align: right;
        }

        .totals {
            padding: 14px 0 8px;
            border-bottom: 1px dashed #d1d5db;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .total-row:last-child {
            margin-bottom: 0;
        }

        .total-label {
            color: #4b5563;
        }

        .total-value {
            font-weight: 700;
            text-align: right;
        }

        .total-label.grand-total,
        .total-value.grand-total {
            font-size: 16px;
            font-weight: 800;
            color: #111827;
        }

        .footer {
            padding-top: 14px;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5;
        }

        @media print {
            body {
                background: white;
            }

            .page {
                padding: 0;
            }

            .top-actions {
                display: none;
            }

            .receipt {
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    @php
        $backUrl = request('source') === 'cashier'
            ? route('cashier.index')
            : route('backoffice.transactions.show', $transaction->id);

        $status = strtolower((string) ($transaction->status ?? 'completed'));
        $isVoid = $status === 'void';

        $displayReceiptNumber = 'ATG-0001';

        if (! empty($transaction->transaction_number)) {
            $parts = explode('-', $transaction->transaction_number);
            $lastPart = end($parts);

            if (is_numeric($lastPart)) {
                $displayReceiptNumber = 'ATG-' . str_pad((string) ((int) $lastPart), 4, '0', STR_PAD_LEFT);
            }
        }

        $displayAmountPaid = in_array(strtolower((string) $transaction->payment_method), ['qris', 'transfer'], true)
            ? (float) $transaction->grand_total
            : (float) $transaction->amount_paid;
    @endphp

    <div class="page">
        <div class="top-actions">
            <button onclick="window.print()" class="btn">Print</button>
            <a href="{{ $backUrl }}" class="btn">Kembali</a>
        </div>

        <div class="receipt">
            <div class="brand">
                <div class="brand-title">ATG POS</div>
                <div class="brand-subtitle">
                    {{ $transaction->outlet->name ?? 'Outlet' }}<br>
                    Receipt Transaksi
                </div>
            </div>

            <div class="meta">
                <div class="meta-row">
                    <div class="meta-label">No. Struk</div>
                    <div class="meta-value">{{ $displayReceiptNumber }}</div>
                </div>

                <div class="meta-row">
                    <div class="meta-label">Tanggal</div>
                    <div class="meta-value">{{ $transaction->created_at?->format('Y-m-d H:i:s') }}</div>
                </div>

                <div class="meta-row">
                    <div class="meta-label">Kasir</div>
                    <div class="meta-value">{{ $transaction->user->name ?? '-' }}</div>
                </div>

                <div class="meta-row">
                    <div class="meta-label">Member</div>
                    <div class="meta-value">{{ $transaction->member->name ?? '-' }}</div>
                </div>

                <div class="meta-row">
                    <div class="meta-label">Payment</div>
                    <div class="meta-value">{{ strtoupper((string) ($transaction->payment_method ?? '-')) }}</div>
                </div>

                <div class="meta-row">
                    <div class="meta-label">Status</div>
                    <div class="meta-value">
                        <span class="status-badge {{ $isVoid ? 'status-void' : 'status-completed' }}">
                            {{ ucfirst($transaction->status ?? 'completed') }}
                        </span>
                    </div>
                </div>

                @if($isVoid)
                    <div class="meta-row">
                        <div class="meta-label">Void At</div>
                        <div class="meta-value">{{ $transaction->void_at ? \Carbon\Carbon::parse($transaction->void_at)->format('Y-m-d H:i:s') : '-' }}</div>
                    </div>

                    <div class="meta-row">
                        <div class="meta-label">Void Reason</div>
                        <div class="meta-value">{{ $transaction->void_reason ?? '-' }}</div>
                    </div>
                @endif
            </div>

            <div class="items">
                @forelse($transaction->items as $item)
                    <div class="item">
                        <div class="item-left">
                            <div class="item-name">{{ $item->product_name ?? '-' }}</div>
                            <div class="item-meta">
                                {{ $item->variant_name ?? '-' }} x {{ number_format((float) $item->qty, 0, ',', '.') }}

                                @if($item->less_sugar || $item->less_ice)
                                    <br>
                                    @if($item->less_sugar)
                                        Less Sugar
                                    @endif
                                    @if($item->less_sugar && $item->less_ice)
                                        •
                                    @endif
                                    @if($item->less_ice)
                                        Less Ice
                                    @endif
                                @endif
                            </div>
                        </div>

                        <div class="item-price">
                            Rp{{ number_format((float) $item->line_total, 0, ',', '.') }}
                        </div>
                    </div>
                @empty
                    <div class="item">
                        <div class="item-left">
                            <div class="item-name">Tidak ada item</div>
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="totals">
                <div class="total-row">
                    <div class="total-label">Subtotal</div>
                    <div class="total-value">Rp{{ number_format((float) $transaction->subtotal, 0, ',', '.') }}</div>
                </div>

                <div class="total-row">
                    <div class="total-label">Discount</div>
                    <div class="total-value">Rp{{ number_format((float) $transaction->discount_amount, 0, ',', '.') }}</div>
                </div>

                <div class="total-row">
                    <div class="total-label">Amount Paid</div>
                    <div class="total-value">Rp{{ number_format((float) $displayAmountPaid, 0, ',', '.') }}</div>
                </div>

                <div class="total-row">
                    <div class="total-label">Change</div>
                    <div class="total-value">Rp{{ number_format((float) $transaction->change_amount, 0, ',', '.') }}</div>
                </div>

                <div class="total-row">
                    <div class="total-label grand-total">Grand Total</div>
                    <div class="total-value grand-total">Rp{{ number_format((float) $transaction->grand_total, 0, ',', '.') }}</div>
                </div>
            </div>

            <div class="footer">
                Terima kasih sudah berbelanja.
            </div>
        </div>
    </div>
</body>
</html>