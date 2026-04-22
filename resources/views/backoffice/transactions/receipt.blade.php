<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $transaction->transaction_number ?? 'ATG POS' }}</title>
    <style>
        :root {
            --paper-width: 58mm;
            --text: #000000;
            --muted: #555555;
            --line: #000000;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            background: #ececec;
            color: var(--text);
            font-family: "Courier New", Courier, monospace;
            -webkit-font-smoothing: none;
            font-size: 12px;
            line-height: 1.35;
        }

        body {
            padding: 16px 0;
        }

        .receipt-preview-wrap {
            width: 100%;
            display: flex;
            justify-content: center;
            padding: 0 12px;
        }

        .receipt-paper {
            width: var(--paper-width);
            max-width: 100%;
            background: #ffffff;
            color: #000000;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            padding: 10px 8px 14px;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: 700;
        }

        .muted {
            color: var(--muted);
        }

        .tiny {
            font-size: 10px;
        }

        .small {
            font-size: 11px;
        }

        .normal {
            font-size: 12px;
        }

        .big {
            font-size: 14px;
            font-weight: 700;
        }

        .brand-name {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .brand-sub {
            font-size: 11px;
            margin-bottom: 2px;
        }

        .header-block,
        .meta-block,
        .items-block,
        .summary-block,
        .footer-block {
            width: 100%;
        }

        .divider {
            border-top: 1px dashed var(--line);
            margin: 8px 0;
            width: 100%;
        }

        .solid-divider {
            border-top: 1px solid var(--line);
            margin: 8px 0;
            width: 100%;
        }

        .meta-line,
        .summary-line,
        .item-total-line {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            width: 100%;
        }

        .meta-line .label,
        .summary-line .label {
            flex: 1 1 auto;
            min-width: 0;
            word-break: break-word;
        }

        .meta-line .value,
        .summary-line .value {
            flex: 0 0 auto;
            text-align: right;
            white-space: nowrap;
        }

        .item-row {
            padding: 4px 0 6px;
        }

        .item-name {
            font-weight: 700;
            word-break: break-word;
            white-space: normal;
        }

        .item-variant {
            margin-top: 1px;
            word-break: break-word;
        }

        .item-modifier {
            margin-top: 1px;
            word-break: break-word;
        }

        .item-total-line {
            margin-top: 2px;
        }

        .item-total-line .left {
            flex: 1 1 auto;
            min-width: 0;
            word-break: break-word;
        }

        .item-total-line .right {
            flex: 0 0 auto;
            text-align: right;
            white-space: nowrap;
        }

        .summary-line.grand-total {
            font-size: 13px;
            font-weight: 700;
        }

        .status-badge {
            display: inline-block;
            padding: 1px 6px;
            border: 1px solid #000000;
            font-size: 10px;
            font-weight: 700;
            margin-top: 4px;
        }

        .void-box {
            border: 1px solid #000000;
            padding: 6px;
            margin-top: 8px;
        }

        .print-toolbar {
            width: 100%;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
            padding: 0 12px;
        }

        .print-btn {
            border: 0;
            cursor: pointer;
            min-height: 40px;
            padding: 0 16px;
            border-radius: 10px;
            background: #111827;
            color: #ffffff;
            font-family: Arial, sans-serif;
            font-size: 13px;
            font-weight: 700;
        }

        .print-btn.secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .footer-space {
            height: 10px;
        }

        @page {
            size: 58mm auto;
            margin: 0;
        }

        @media print {
            html, body {
                background: #ffffff;
                width: 58mm;
                min-width: 58mm;
                max-width: 58mm;
                margin: 0;
                padding: 0;
                font-size: 11px;
                line-height: 1.3;
            }

            body {
                padding: 0;
            }

            .print-toolbar {
                display: none !important;
            }

            .receipt-preview-wrap {
                padding: 0;
                margin: 0;
                display: block;
                width: 58mm;
            }

            .receipt-paper {
                width: 58mm;
                min-width: 58mm;
                max-width: 58mm;
                box-shadow: none;
                padding: 6px 6px 10px;
                margin: 0;
            }

            .brand-name {
                font-size: 15px;
            }

            .brand-sub,
            .small {
                font-size: 10px;
            }

            .normal {
                font-size: 11px;
            }

            .big {
                font-size: 12px;
            }

            .divider,
            .solid-divider {
                margin: 6px 0;
            }

            .footer-space {
                height: 16px;
            }
        }
    </style>
</head>
<body>
    @php
        $source = $source ?? request('source', 'backoffice');
        $autoprint = (bool) ($autoprint ?? request('autoprint'));
        $transactionNumber = $transaction->transaction_number ?? ('TRX-' . $transaction->id);
        $outletName = $transaction->outlet->name ?? 'ATG POS';
        $cashierName = $transaction->user->name ?? '-';
        $memberName = $transaction->member->name ?? null;
        $memberPhone = $transaction->member->phone ?? null;
        $createdAt = $transaction->created_at?->format('Y-m-d H:i:s') ?? '-';
        $paymentMethod = strtoupper((string) ($transaction->payment_method ?? '-'));
        $status = strtoupper((string) ($transaction->status ?? '-'));
        $subtotal = (float) ($transaction->subtotal ?? 0);
        $discountAmount = (float) ($transaction->discount_amount ?? 0);
        $taxAmount = (float) ($transaction->tax_amount ?? 0);
        $grandTotal = (float) ($transaction->grand_total ?? 0);
        $amountPaid = (float) ($transaction->amount_paid ?? 0);
        $changeAmount = (float) ($transaction->change_amount ?? 0);
        $isVoid = strtolower((string) ($transaction->status ?? '')) === 'void';
    @endphp

    <div class="print-toolbar">
        <button type="button" class="print-btn" onclick="window.print()">Print Receipt</button>

        @if($source === 'cashier')
            <button type="button" class="print-btn secondary" onclick="window.location.href='{{ route('cashier.index') }}'">
                Kembali ke Cashier
            </button>
        @else
            <button type="button" class="print-btn secondary" onclick="window.history.back()">
                Kembali
            </button>
        @endif
    </div>

    <div class="receipt-preview-wrap">
        <div class="receipt-paper">
            <div class="header-block center">
                <div class="brand-name">{{ $outletName }}</div>
                <div class="brand-sub">ATG POS RECEIPT</div>
                <div class="small">{{ $createdAt }}</div>

                @if($isVoid)
                    <div class="status-badge">VOID</div>
                @endif
            </div>

            <div class="divider"></div>

            <div class="meta-block small">
                <div class="meta-line">
                    <div class="label">No</div>
                    <div class="value">{{ $transactionNumber }}</div>
                </div>
                <div class="meta-line">
                    <div class="label">Cashier</div>
                    <div class="value">{{ $cashierName }}</div>
                </div>
                <div class="meta-line">
                    <div class="label">Payment</div>
                    <div class="value">{{ $paymentMethod }}</div>
                </div>
                <div class="meta-line">
                    <div class="label">Status</div>
                    <div class="value">{{ $status }}</div>
                </div>

                @if($memberName || $memberPhone)
                    <div class="meta-line">
                        <div class="label">Member</div>
                        <div class="value">
                            {{ $memberName ?: '-' }}
                            @if($memberPhone)
                                <br>{{ $memberPhone }}
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="divider"></div>

            <div class="items-block normal">
                @forelse($transaction->items as $item)
                    <div class="item-row">
                        <div class="item-name">
                            {{ $item->product_name ?? '-' }}
                        </div>

                        @if(!empty($item->variant_name))
                            <div class="item-variant small muted">
                                {{ $item->variant_name }}
                            </div>
                        @endif

                        @php
                            $modifiers = [];
                            if (!empty($item->less_sugar)) {
                                $modifiers[] = 'Less Sugar';
                            }
                            if (!empty($item->less_ice)) {
                                $modifiers[] = 'Less Ice';
                            }
                        @endphp

                        @if(count($modifiers))
                            <div class="item-modifier small muted">
                                {{ implode(' • ', $modifiers) }}
                            </div>
                        @endif

                        <div class="item-total-line small">
                            <div class="left">
                                {{ number_format((float) ($item->qty ?? 0), 0, ',', '.') }}
                                x
                                {{ number_format((float) ($item->price ?? 0), 0, ',', '.') }}
                            </div>
                            <div class="right">
                                {{ number_format((float) ($item->line_total ?? 0), 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="center small">Tidak ada item.</div>
                @endforelse
            </div>

            <div class="divider"></div>

            <div class="summary-block normal">
                <div class="summary-line">
                    <div class="label">Subtotal</div>
                    <div class="value">{{ number_format($subtotal, 0, ',', '.') }}</div>
                </div>

                @if($discountAmount > 0)
                    <div class="summary-line">
                        <div class="label">Discount</div>
                        <div class="value">-{{ number_format($discountAmount, 0, ',', '.') }}</div>
                    </div>
                @endif

                @if($taxAmount > 0)
                    <div class="summary-line">
                        <div class="label">Tax</div>
                        <div class="value">{{ number_format($taxAmount, 0, ',', '.') }}</div>
                    </div>
                @endif

                <div class="solid-divider"></div>

                <div class="summary-line grand-total">
                    <div class="label">TOTAL</div>
                    <div class="value">{{ number_format($grandTotal, 0, ',', '.') }}</div>
                </div>

                <div class="solid-divider"></div>

                <div class="summary-line">
                    <div class="label">Paid</div>
                    <div class="value">{{ number_format($amountPaid, 0, ',', '.') }}</div>
                </div>

                <div class="summary-line">
                    <div class="label">Change</div>
                    <div class="value">{{ number_format($changeAmount, 0, ',', '.') }}</div>
                </div>
            </div>

            @if($isVoid)
                <div class="void-box small">
                    <div class="bold">VOID INFO</div>

                    @if(!empty($transaction->void_at))
                        <div>Void At: {{ $transaction->void_at?->format('Y-m-d H:i:s') }}</div>
                    @endif

                    @if(!empty($transaction->voidBy?->name))
                        <div>Void By: {{ $transaction->voidBy->name }}</div>
                    @endif

                    @if(!empty($transaction->void_reason))
                        <div style="margin-top:4px;">
                            Reason: {{ $transaction->void_reason }}
                        </div>
                    @endif
                </div>
            @endif

            <div class="divider"></div>

            <div class="footer-block center">
                <div class="small bold">Terima kasih</div>
                <div class="tiny">Simpan struk ini sebagai bukti transaksi</div>
                <div class="tiny">Powered by ATG POS</div>
            </div>

            <div class="footer-space"></div>
        </div>
    </div>

    <script>
        (function () {
            const shouldAutoPrint = @json($autoprint);

            if (!shouldAutoPrint) {
                return;
            }

            window.addEventListener('load', function () {
                setTimeout(function () {
                    window.print();
                }, 400);
            });

            window.addEventListener('afterprint', function () {
                // Biarkan tetap di halaman receipt setelah print
            });
        })();
    </script>
</body>
</html>