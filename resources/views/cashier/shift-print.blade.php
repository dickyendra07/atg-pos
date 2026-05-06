<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Print - LEE ONG'S TEA X WASPFFLE</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f3f4f6;
            color: #111827;
            font-family: "Courier New", monospace;
        }

        .print-actions {
            width: 320px;
            margin: 18px auto 10px;
            display: flex;
            gap: 8px;
        }

        .btn {
            border: 0;
            border-radius: 8px;
            padding: 10px 14px;
            font-weight: 800;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            flex: 1;
            font-family: Arial, sans-serif;
        }

        .btn-green {
            background: #15803d;
            color: #fff;
        }

        .btn-dark {
            background: #111827;
            color: #fff;
        }

        .receipt {
            width: 320px;
            margin: 0 auto 24px;
            padding: 14px 12px;
            background: #fff;
            color: #111827;
            font-size: 12px;
            line-height: 1.35;
        }

        .center {
            text-align: center;
        }

        .brand {
            font-size: 15px;
            font-weight: 900;
            letter-spacing: 0.08em;
        }

        .title {
            font-size: 13px;
            font-weight: 900;
            margin-top: 2px;
        }

        .muted {
            color: #4b5563;
        }

        .divider {
            border-top: 1px dashed #111827;
            margin: 8px 0;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .row span:first-child {
            flex: 1;
        }

        .row strong,
        .row span:last-child {
            text-align: right;
        }

        .section-title {
            text-align: center;
            font-weight: 900;
            margin: 8px 0 6px;
        }

        .item {
            margin-bottom: 8px;
            break-inside: avoid;
        }

        .item-name {
            font-weight: 900;
            word-break: break-word;
        }

        .item-meta {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin-top: 2px;
        }

        .item-meta span:first-child {
            flex: 1;
        }

        .item-meta strong {
            text-align: right;
            white-space: nowrap;
        }

        .grand {
            font-weight: 900;
            font-size: 13px;
        }

        .footer {
            margin-top: 10px;
            text-align: center;
            font-weight: 800;
        }

        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }

            body {
                background: #fff;
            }

            .print-actions {
                display: none;
            }

            .receipt {
                width: 80mm;
                margin: 0;
                padding: 10px;
                box-shadow: none;
            }
        }

        .category-title {
            text-align: center;
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 0.08em;
            margin: 12px 0 6px;
        }

        .category-total {
            margin-top: 2px;
            font-weight: 800;
        }

    </style>
</head>
<body>
    @php
$completedTransactions = $transactions
            ->filter(fn ($transaction) => strtolower((string) ($transaction->status ?? '')) === 'completed')
            ->values();

        $voidTransactions = $transactions
            ->filter(fn ($transaction) => strtolower((string) ($transaction->status ?? '')) === 'void')
            ->values();

        $cleanShiftText = function ($value) {
            return trim(preg_replace('/\s*\[(DINE IN|DELIVERY|TAKE AWAY|TAKEAWAY)\]\s*/i', ' ', (string) $value));
        };

        $categoryItemSummary = $completedTransactions
            ->flatMap(fn ($transaction) => $transaction->items)
            ->filter(fn ($item) => (float) ($item->qty ?? 0) > 0)
            ->groupBy(function ($item) {
                $categoryName = trim((string) ($item->category_name ?? ''));

                if ($categoryName !== '') {
                    return $categoryName;
                }

                return trim((string) ($item->variant?->product?->category?->name ?? 'Lainnya'));
            })
            ->map(function ($items, $categoryName) use ($cleanShiftText) {
                $summaryItems = $items
                    ->groupBy(function ($item) use ($cleanShiftText) {
                        $productName = $cleanShiftText($item->product_name ?? '-');
                        $variantName = $cleanShiftText($item->variant_name ?? '');

                        return $variantName !== ''
                            ? $productName . ' ' . $variantName
                            : $productName;
                    })
                    ->map(function ($groupedItems, $name) {
                        return [
                            'name' => $name,
                            'qty' => (float) $groupedItems->sum('qty'),
                            'line_total' => (float) $groupedItems->sum('line_total'),
                        ];
                    })
                    ->sortBy('name')
                    ->values();

                return [
                    'category_name' => $categoryName ?: 'Lainnya',
                    'items' => $summaryItems,
                    'qty' => (float) $summaryItems->sum('qty'),
                    'line_total' => (float) $summaryItems->sum('line_total'),
                ];
            })
            ->sortKeys()
            ->values();

        $totalQty = (float) $categoryItemSummary->sum('qty');
        $grossSales = (float) $completedTransactions->sum('subtotal');
        $totalDiscount = (float) $completedTransactions->sum('discount_amount');
        $netSales = (float) $completedTransactions->sum('grand_total');

        $paymentSummary = $completedTransactions
            ->groupBy(fn ($transaction) => strtoupper((string) ($transaction->payment_method ?? '-')))
            ->map(fn ($rows) => (float) $rows->sum('grand_total'))
            ->sortKeys();

        $startedAt = $shift->started_at?->format('Y-m-d H:i') ?? '-';
        $endedAt = $shift->ended_at?->format('Y-m-d H:i') ?? '-';
        $printedAt = now()->format('Y-m-d H:i:s');
    @endphp

    <div class="print-actions">
        <button type="button" class="btn btn-green" onclick="window.print()">Print Shift</button>
        <a href="{{ route('cashier.index') }}" class="btn btn-dark">Kembali</a>
    </div>

    <div class="receipt">
        <div class="center">
            <div class="brand">LEE ONG'S TEA X WASPFFLE</div>
            <div class="title">SHIFT ITEM SOLD SUMMARY</div>
            <div class="muted">{{ $shift->outlet->name ?? '-' }}</div>
            <div class="muted">{{ $printedAt }}</div>
        </div>

        <div class="divider"></div>

        <div class="row">
            <span>Shift</span>
            <strong>#{{ $shift->id }}</strong>
        </div>
        <div class="row">
            <span>Cashier</span>
            <strong>{{ $shift->user->name ?? '-' }}</strong>
        </div>
        <div class="row">
            <span>Start</span>
            <strong>{{ $startedAt }}</strong>
        </div>
        <div class="row">
            <span>End</span>
            <strong>{{ $endedAt }}</strong>
        </div>
        <div class="row">
            <span>Opening Cash</span>
            <strong>Rp {{ number_format((float) ($shift->opening_cash ?? 0), 0, ',', '.') }}</strong>
        </div>
        <div class="row">
            <span>Closing Cash</span>
            <strong>
                @if($shift->closing_cash_actual !== null)
                    Rp {{ number_format((float) $shift->closing_cash_actual, 0, ',', '.') }}
                @else
                    -
                @endif
            </strong>
        </div>

        <div class="divider"></div>

        <div class="row">
            <span>Total Trx</span>
            <strong>{{ number_format($transactions->count(), 0, ',', '.') }}</strong>
        </div>
        <div class="row">
            <span>Completed</span>
            <strong>{{ number_format($completedTransactions->count(), 0, ',', '.') }}</strong>
        </div>
        <div class="row">
            <span>Void</span>
            <strong>{{ number_format($voidTransactions->count(), 0, ',', '.') }}</strong>
        </div>

        <div class="divider"></div>
        <div class="section-title">ITEM TERJUAL</div>
        <div class="divider"></div>

        @forelse($categoryItemSummary as $category)
            <div class="category-title">{{ $category['category_name'] }}</div>

            @foreach($category['items'] as $item)
                <div class="item">
                    <div class="item-name">*{{ $item['name'] }}</div>
                    <div class="item-meta">
                        <span>{{ number_format((float) $item['qty'], 0, ',', '.') }}</span>
                        <strong>{{ number_format((float) $item['line_total'], 0, ',', '.') }}</strong>
                    </div>
                </div>
            @endforeach

            <div class="row category-total">
                <span>Total</span>
                <strong>{{ number_format((float) $category['line_total'], 0, ',', '.') }}</strong>
            </div>
            <br>
        @empty
            <div class="center muted">Belum ada item terjual.</div>
        @endforelse

        <div class="divider"></div>

        <div class="row">
            <span>Total Item</span>
            <strong>{{ number_format($totalQty, 0, ',', '.') }}</strong>
        </div>
        <div class="row">
            <span>Gross Sales</span>
            <strong>Rp {{ number_format($grossSales, 0, ',', '.') }}</strong>
        </div>
        <div class="row">
            <span>Discount</span>
            <strong>- Rp {{ number_format($totalDiscount, 0, ',', '.') }}</strong>
        </div>
        <div class="row grand">
            <span>Net Sales</span>
            <strong>Rp {{ number_format($netSales, 0, ',', '.') }}</strong>
        </div>

        @if($paymentSummary->count())
            <div class="divider"></div>
            <div class="section-title">PAYMENT</div>
            <div class="divider"></div>

            @foreach($paymentSummary as $method => $amount)
                <div class="row">
                    <span>{{ $method ?: '-' }}</span>
                    <strong>Rp {{ number_format((float) $amount, 0, ',', '.') }}</strong>
                </div>
            @endforeach
        @endif

        @if(!empty($shift->closing_note))
            <div class="divider"></div>
            <div>
                <strong>Note:</strong><br>
                {{ $shift->closing_note }}
            </div>
        @endif

        <div class="divider"></div>
        <div class="footer">
            End of shift summary
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
