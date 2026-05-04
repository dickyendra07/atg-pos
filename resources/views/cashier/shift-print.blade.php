<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Receipt - ATG POS</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f4f6;
            color: #111827;
            font-family: "Courier New", monospace;
            font-size: 12px;
        }

        .page {
            min-height: 100vh;
            padding: 18px;
        }

        .print-actions {
            max-width: 320px;
            margin: 0 auto 14px;
            display: flex;
            gap: 8px;
        }

        .btn {
            border: 0;
            border-radius: 10px;
            padding: 10px 12px;
            color: #fff;
            text-decoration: none;
            font-family: Arial, sans-serif;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 1;
        }

        .btn-green { background: #166534; }
        .btn-dark { background: #111827; }

        .receipt {
            width: 80mm;
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 14px 12px;
            box-shadow: 0 12px 30px rgba(15,23,42,0.14);
        }

        .center {
            text-align: center;
        }

        .brand {
            font-size: 15px;
            font-weight: 900;
            line-height: 1.2;
            margin-bottom: 3px;
        }

        .title {
            font-size: 13px;
            font-weight: 900;
            margin-bottom: 4px;
        }

        .muted {
            color: #374151;
            font-size: 11px;
            line-height: 1.35;
        }

        .divider {
            border-top: 1px dashed #111827;
            margin: 8px 0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            line-height: 1.45;
        }

        .row span:first-child {
            flex: 1;
        }

        .row span:last-child {
            text-align: right;
            font-weight: 700;
        }

        .trx {
            break-inside: avoid;
            page-break-inside: avoid;
            margin-bottom: 10px;
        }

        .trx-head {
            font-weight: 900;
            line-height: 1.45;
        }

        .status-void {
            color: #b91c1c;
            font-weight: 900;
        }

        .item {
            margin-top: 5px;
            line-height: 1.35;
        }

        .item-name {
            font-weight: 700;
        }

        .item-meta {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding-left: 8px;
        }

        .item-meta span:last-child {
            text-align: right;
            white-space: nowrap;
        }

        .trx-total {
            margin-top: 5px;
            font-weight: 900;
        }

        .grand {
            font-size: 14px;
            font-weight: 900;
        }

        .footer {
            margin-top: 10px;
            text-align: center;
            font-size: 11px;
            line-height: 1.45;
        }

        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }

            body {
                background: #fff;
                margin: 0;
                font-size: 11px;
            }

            .page {
                min-height: 0;
                padding: 0;
            }

            .print-actions {
                display: none;
            }

            .receipt {
                width: 80mm;
                margin: 0;
                padding: 10px 8px;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
@php
    $completedTransactions = $transactions->where('status', 'completed')->values();
    $voidTransactions = $transactions->where('status', 'void')->values();

    $grossSales = (float) $completedTransactions->sum('subtotal');
    $totalDiscount = (float) $completedTransactions->sum('discount_amount');
    $netSales = (float) $completedTransactions->sum('grand_total');

    $paymentRows = [
        'Cash' => (float) ($summary['cash_sales'] ?? 0),
        'QRIS' => (float) ($summary['qris_sales'] ?? 0),
        'Transfer' => (float) ($summary['transfer_sales'] ?? 0),
        'Debit' => (float) ($summary['debit_sales'] ?? 0),
        'Credit' => (float) ($summary['credit_sales'] ?? 0),
    ];

    $formatTransactionNumber = function ($transactionNumber) {
        if (empty($transactionNumber)) {
            return '-';
        }

        $parts = explode('-', $transactionNumber);
        $lastPart = end($parts);

        if (is_numeric($lastPart)) {
            return 'ATG ' . str_pad((string) ((int) $lastPart), 3, '0', STR_PAD_LEFT);
        }

        return $transactionNumber;
    };
@endphp

    <div class="page">
        <div class="print-actions">
            <button type="button" class="btn btn-green" onclick="window.print()">Print Shift</button>
            <a href="{{ route('cashier.index') }}" class="btn btn-dark">Kembali</a>
        </div>

        <div class="receipt">
            <div class="center">
                <div class="brand">ATG POS</div>
                <div class="title">SHIFT TRANSACTION RECEIPT</div>
                <div class="muted">{{ $shift->outlet->name ?? '-' }}</div>
                <div class="muted">{{ now()->format('Y-m-d H:i:s') }}</div>
            </div>

            <div class="divider"></div>

            <div class="row">
                <span>Shift</span>
                <span>#{{ $shift->id }}</span>
            </div>
            <div class="row">
                <span>Cashier</span>
                <span>{{ $shift->user->name ?? '-' }}</span>
            </div>
            <div class="row">
                <span>Start</span>
                <span>{{ $shift->started_at?->format('Y-m-d H:i') ?? '-' }}</span>
            </div>
            <div class="row">
                <span>End</span>
                <span>{{ $shift->ended_at?->format('Y-m-d H:i') ?? '-' }}</span>
            </div>
            <div class="row">
                <span>Opening Cash</span>
                <span>Rp {{ number_format((float) ($shift->opening_cash ?? 0), 0, ',', '.') }}</span>
            </div>
            <div class="row">
                <span>Closing Cash</span>
                <span>
                    @if($shift->closing_cash_actual !== null)
                        Rp {{ number_format((float) $shift->closing_cash_actual, 0, ',', '.') }}
                    @else
                        -
                    @endif
                </span>
            </div>

            <div class="divider"></div>

            <div class="row">
                <span>Total Trx</span>
                <span>{{ number_format((int) $transactions->count(), 0, ',', '.') }}</span>
            </div>
            <div class="row">
                <span>Completed</span>
                <span>{{ number_format((int) $completedTransactions->count(), 0, ',', '.') }}</span>
            </div>
            <div class="row">
                <span>Void</span>
                <span>{{ number_format((int) $voidTransactions->count(), 0, ',', '.') }}</span>
            </div>
            <div class="row">
                <span>Gross Sales</span>
                <span>Rp {{ number_format($grossSales, 0, ',', '.') }}</span>
            </div>
            <div class="row">
                <span>Discount</span>
                <span>- Rp {{ number_format($totalDiscount, 0, ',', '.') }}</span>
            </div>
            <div class="row grand">
                <span>Net Sales</span>
                <span>Rp {{ number_format($netSales, 0, ',', '.') }}</span>
            </div>

            <div class="divider"></div>

            <div class="center title">TRANSAKSI SHIFT</div>

            @forelse($transactions as $transaction)
                @php
                    $status = strtolower((string) ($transaction->status ?? '-'));
                    $isVoid = $status === 'void';
                    $paymentMethod = strtoupper(trim((string) ($transaction->payment_method ?? '-'))) ?: '-';
                    $displayTransactionNumber = $formatTransactionNumber($transaction->transaction_number ?? null);
                @endphp

                <div class="trx">
                    <div class="divider"></div>

                    <div class="trx-head">
                        {{ $loop->iteration }}. {{ $displayTransactionNumber }}
                        @if($isVoid)
                            <span class="status-void">[VOID]</span>
                        @endif
                    </div>

                    <div class="muted">
                        {{ $transaction->created_at?->format('Y-m-d H:i:s') ?? '-' }} | {{ $paymentMethod }}
                    </div>

                    @if($transaction->member)
                        <div class="muted">Member: {{ $transaction->member->name }}</div>
                    @endif

                    @forelse($transaction->items as $item)
                        <div class="item">
                            <div class="item-name">
                                {{ $item->product_name ?? '-' }}
                                @if($item->variant_name)
                                    - {{ $item->variant_name }}
                                @endif
                            </div>

                            @if($item->less_sugar || $item->less_ice)
                                <div class="muted">
                                    @if($item->less_sugar)
                                        Less Sugar
                                    @endif
                                    @if($item->less_sugar && $item->less_ice)
                                        /
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
                                <span>
                                    Rp {{ number_format((float) $item->line_total, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="item">Tidak ada item.</div>
                    @endforelse

                    @if(!empty($transaction->promo_name))
                        <div class="row">
                            <span>Promo</span>
                            <span>{{ $transaction->promo_name }}</span>
                        </div>
                    @endif

                    @if((float) ($transaction->discount_amount ?? 0) > 0)
                        <div class="row">
                            <span>Discount</span>
                            <span>- Rp {{ number_format((float) $transaction->discount_amount, 0, ',', '.') }}</span>
                        </div>
                    @endif

                    <div class="row trx-total">
                        <span>Total</span>
                        <span>Rp {{ number_format((float) ($transaction->grand_total ?? 0), 0, ',', '.') }}</span>
                    </div>

                    @if($isVoid)
                        <div class="muted">
                            Void reason: {{ $transaction->void_reason ?? '-' }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="divider"></div>
                <div class="center muted">Belum ada transaksi dalam shift ini.</div>
            @endforelse

            <div class="divider"></div>

            <div class="center title">PAYMENT SUMMARY</div>
            @foreach($paymentRows as $paymentLabel => $paymentTotal)
                @if($paymentTotal > 0)
                    <div class="row">
                        <span>{{ $paymentLabel }}</span>
                        <span>Rp {{ number_format($paymentTotal, 0, ',', '.') }}</span>
                    </div>
                @endif
            @endforeach

            <div class="divider"></div>

            <div class="row">
                <span>Expected Cash</span>
                <span>Rp {{ number_format((float) ($summary['expected_cash'] ?? 0), 0, ',', '.') }}</span>
            </div>
            <div class="row">
                <span>Difference</span>
                <span>Rp {{ number_format((float) ($summary['difference'] ?? 0), 0, ',', '.') }}</span>
            </div>

            @if(!empty($shift->closing_note))
                <div class="divider"></div>
                <div class="muted">
                    Note: {{ $shift->closing_note }}
                </div>
            @endif

            <div class="divider"></div>

            <div class="footer">
                End of shift report<br>
                Simpan print ini sebagai bukti tutup shift
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            const params = new URLSearchParams(window.location.search);

            if (params.get('autoprint') === '1') {
                window.print();
            }
        });
    </script>
</body>
</html>
