<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#111827">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="ATG POS">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print All Receipts - Shift {{ $shift->id }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }

        .page {
            max-width: 960px;
            margin: 0 auto;
            padding: 24px;
        }

        .print-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .btn {
            min-height: 42px;
            padding: 0 16px;
            border-radius: 12px;
            border: 0;
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-green {
            background: #166534;
        }

        .btn-dark {
            background: #111827;
        }

        .print-info {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 18px;
        }

        .print-title {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 800;
        }

        .print-subtitle {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
        }

        .receipt-list {
            display: grid;
            gap: 18px;
        }

        .receipt {
            width: 80mm;
            max-width: 100%;
            margin: 0 auto;
            background: white;
            color: #111827;
            padding: 14px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            font-size: 12px;
            line-height: 1.45;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #9ca3af;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .brand {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 0.03em;
            margin-bottom: 4px;
        }

        .outlet {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .muted {
            color: #6b7280;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 4px;
        }

        .receipt-row strong {
            font-weight: 800;
        }

        .divider {
            border-top: 1px dashed #9ca3af;
            margin: 10px 0;
        }

        .item {
            margin-bottom: 8px;
        }

        .item-name {
            font-weight: 700;
            margin-bottom: 3px;
        }

        .item-meta {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            color: #374151;
        }

        .modifiers {
            margin-top: 2px;
            font-size: 11px;
            color: #6b7280;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            font-size: 13px;
            font-weight: 800;
            margin-top: 4px;
        }

        .grand-total {
            font-size: 15px;
        }

        .void-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 10px;
            border-radius: 999px;
            background: #fee2e2;
            color: #b91c1c;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            margin-top: 8px;
        }

        .receipt-footer {
            text-align: center;
            border-top: 1px dashed #9ca3af;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 11px;
            color: #6b7280;
        }

        .empty-state {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 24px;
            text-align: center;
            font-weight: 700;
            color: #6b7280;
        }

        @media print {
            body {
                background: white;
            }

            .page {
                max-width: none;
                padding: 0;
                margin: 0;
            }

            .print-actions,
            .print-info {
                display: none !important;
            }

            .receipt-list {
                display: block;
            }

            .receipt {
                width: 80mm;
                margin: 0 auto;
                border: 0;
                border-radius: 0;
                page-break-after: always;
                break-after: page;
            }

            .receipt:last-child {
                page-break-after: auto;
                break-after: auto;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="print-actions">
            <button type="button" class="btn btn-green" onclick="window.print()">Print Semua Struk</button>
            <a href="{{ route('cashier.index') }}" class="btn btn-dark">Kembali ke Cashier</a>
        </div>

        <div class="print-info">
            <h1 class="print-title">Print Semua Struk Shift</h1>

        </div>

        @if($transactions->isEmpty())
            <div class="empty-state">
                Belum ada transaksi dalam shift ini.
            </div>
        @else
            <div class="receipt-list">
                @foreach($transactions as $transaction)
                    @php
                        $status = strtolower((string) ($transaction->status ?? '-'));
                        $paymentMethod = strtoupper(trim((string) ($transaction->payment_method ?? '-')));
                        if ($paymentMethod === '') {
                            $paymentMethod = '-';
                        }
                    @endphp

                    <div class="receipt">
                        <div class="receipt-header">
                            <div class="brand">ATG POS</div>
                            <div class="outlet">{{ $transaction->outlet->name ?? $shift->outlet->name ?? '-' }}</div>
                            <div class="muted">Receipt</div>

                            @if($status === 'void')
                                <div class="void-badge">Void</div>
                            @endif
                        </div>

                        <div class="receipt-row">
                            <span>No</span>
                            @php
                                $rawTransactionNumber = $transaction->transaction_number ?? null;
                                $displayTransactionNumber = '-';

                                if (! empty($rawTransactionNumber)) {
                                    $parts = explode('-', $rawTransactionNumber);
                                    $lastPart = end($parts);

                                    if (is_numeric($lastPart)) {
                                        $displayTransactionNumber = 'ATG ' . str_pad((string) ((int) $lastPart), 3, '0', STR_PAD_LEFT);
                                    } else {
                                        $displayTransactionNumber = $rawTransactionNumber;
                                    }
                                }
                            @endphp
                            <strong>{{ $displayTransactionNumber }}</strong>
                        </div>

                        <div class="receipt-row">
                            <span>Date</span>
                            <strong>{{ $transaction->created_at?->format('d M Y H:i') ?? '-' }}</strong>
                        </div>

                        <div class="receipt-row">
                            <span>Cashier</span>
                            <strong>{{ $transaction->user->name ?? $shift->user->name ?? '-' }}</strong>
                        </div>

                        @if($transaction->member)
                            <div class="receipt-row">
                                <span>Member</span>
                                <strong>{{ $transaction->member->name }}</strong>
                            </div>
                        @endif

                        <div class="divider"></div>

                        @forelse($transaction->items as $item)
                            <div class="item">
                                <div class="item-name">
                                    {{ $item->product_name ?? '-' }}
                                    @if($item->variant_name)
                                        - {{ $item->variant_name }}
                                    @endif
                                </div>

                                @if($item->less_sugar || $item->less_ice)
                                    <div class="modifiers">
                                        @if($item->less_sugar)
                                            Less Sugar
                                        @endif

                                        @if($item->less_sugar && $item->less_ice)
                                            ,
                                        @endif

                                        @if($item->less_ice)
                                            Less Ice
                                        @endif
                                    </div>
                                @endif

                                <div class="item-meta">
                                    <span>
                                        {{ number_format((float) $item->qty, 0, ',', '.') }}
                                        x Rp {{ number_format((float) $item->price, 0, ',', '.') }}
                                    </span>
                                    <strong>
                                        Rp {{ number_format((float) $item->line_total, 0, ',', '.') }}
                                    </strong>
                                </div>
                            </div>
                        @empty
                            <div class="item">
                                <div class="item-name">-</div>
                            </div>
                        @endforelse

                        <div class="divider"></div>

                        <div class="receipt-row">
                            <span>Subtotal</span>
                            <strong>Rp {{ number_format((float) ($transaction->subtotal ?? 0), 0, ',', '.') }}</strong>
                        </div>

                        @if((float) ($transaction->discount_amount ?? 0) > 0)
                            <div class="receipt-row">
                                <span>Discount</span>
                                <strong>- Rp {{ number_format((float) $transaction->discount_amount, 0, ',', '.') }}</strong>
                            </div>
                        @endif

                        @if((float) ($transaction->tax_amount ?? 0) > 0)
                            <div class="receipt-row">
                                <span>Tax</span>
                                <strong>Rp {{ number_format((float) $transaction->tax_amount, 0, ',', '.') }}</strong>
                            </div>
                        @endif

                        <div class="total-row grand-total">
                            <span>Grand Total</span>
                            <span>Rp {{ number_format((float) ($transaction->grand_total ?? 0), 0, ',', '.') }}</span>
                        </div>

                        <div class="divider"></div>

                        <div class="receipt-row">
                            <span>Payment</span>
                            <strong>{{ $paymentMethod }}</strong>
                        </div>

                        <div class="receipt-row">
                            <span>Paid</span>
                            <strong>Rp {{ number_format((float) ($transaction->amount_paid ?? 0), 0, ',', '.') }}</strong>
                        </div>

                        <div class="receipt-row">
                            <span>Change</span>
                            <strong>Rp {{ number_format((float) ($transaction->change_amount ?? 0), 0, ',', '.') }}</strong>
                        </div>

                        @if($status === 'void')
                            <div class="divider"></div>

                            <div class="receipt-row">
                                <span>Void At</span>
                                <strong>{{ $transaction->void_at?->format('d M Y H:i') ?? '-' }}</strong>
                            </div>

                            <div class="receipt-row">
                                <span>Void By</span>
                                <strong>{{ $transaction->voidBy->name ?? '-' }}</strong>
                            </div>

                            <div style="margin-top:6px;">
                                <strong>Void Reason:</strong><br>
                                {{ $transaction->void_reason ?? '-' }}
                            </div>
                        @endif

                        <div class="receipt-footer">
                            Terima kasih.<br>
                            Printed from shift #{{ $shift->id }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <script>
        window.addEventListener('load', function () {
            const params = new URLSearchParams(window.location.search);

            if (params.get('autoprint') === '1') {
                window.print();
            }
        });
    </script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/service-worker.js').catch(function () {
                    // Silent fail supaya tidak ganggu POS flow.
                });
            });
        }
    </script>

</body>
</html>
