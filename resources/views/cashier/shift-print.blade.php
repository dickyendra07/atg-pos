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

        html,
        body {
            margin: 0;
            padding: 0;
            background: #f3f4f6;
            color: #111827;
            font-family: "Courier New", monospace;
        }

        body {
            min-height: 100vh;
            padding: 18px;
        }

        .print-actions {
            width: min(620px, 100%);
            margin: 0 auto 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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

        .bluetooth-status {
            width: min(620px, 100%);
            margin: 0 auto 10px;
            padding: 10px 12px;
            border-radius: 14px;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            color: #312e81;
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 800;
            white-space: pre-wrap;
        }

        .receipt {
            width: 58mm;
            max-width: 100%;
            margin: 0 auto 24px;
            padding: 4mm 3mm 5mm;
            background: #fff;
            color: #111827;
            font-size: 12px;
            line-height: 1.32;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
        }

        .center {
            text-align: center;
        }

        .brand {
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.02em;
            line-height: 1.2;
        }

        .title {
            font-size: 11px;
            font-weight: 900;
            margin-top: 2px;
        }

        .muted {
            color: #4b5563;
        }

        .divider {
            border-top: 1px dashed #111827;
            margin: 6px 0;
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
            margin: 7px 0 5px;
        }

        .item {
            margin-bottom: 6px;
            break-inside: avoid;
            page-break-inside: avoid;
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
            font-size: 12px;
        }

        .footer {
            margin-top: 10px;
            text-align: center;
            font-weight: 800;
        }

        @media print {
            @page {
                size: 58mm 180mm;
                margin: 0;
            }

            html,
            body {
                width: 58mm !important;
                min-width: 58mm !important;
                max-width: 58mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                overflow: visible !important;
            }

            .print-actions,
            .bluetooth-status {
                display: none !important;
            }

            .receipt {
                width: 58mm !important;
                min-width: 58mm !important;
                max-width: 58mm !important;
                margin: 0 !important;
                padding: 4mm 3mm 5mm !important;
                box-shadow: none !important;
                background: #fff !important;
                color: #000 !important;
                font-size: 12px !important;
                line-height: 1.32 !important;
                break-inside: auto !important;
                page-break-inside: auto !important;
            }

            .item,
            .row,
            .category-title,
            .section-title {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
        }

        .category-title {
            text-align: center;
            font-size: 14px;
            font-weight: 900;
            letter-spacing: 0.04em;
            margin: 10px 0 6px;
            break-after: avoid;
            page-break-after: avoid;
        }

        .category-total {
            margin-top: 2px;
            font-weight: 800;
        }

    </style>
    <style id="dynamic-shift-print-style"></style>
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

        $shiftPaymentPayload = [];

        foreach ($paymentSummary as $method => $amount) {
            $shiftPaymentPayload[] = [
                'method' => $method ?: '-',
                'amount' => (float) $amount,
            ];
        }

        $shiftCategoryPayload = [];

        foreach ($categoryItemSummary as $category) {
            $shiftItemsPayload = [];

            foreach (collect($category['items'] ?? []) as $item) {
                $shiftItemsPayload[] = [
                    'name' => $item['name'] ?? '-',
                    'qty' => (float) ($item['qty'] ?? 0),
                    'line_total' => (float) ($item['line_total'] ?? 0),
                ];
            }

            $shiftCategoryPayload[] = [
                'category_name' => $category['category_name'] ?? '-',
                'qty' => (float) ($category['qty'] ?? 0),
                'line_total' => (float) ($category['line_total'] ?? 0),
                'items' => $shiftItemsPayload,
            ];
        }

        $shiftPrintPayload = [
            'brand_name' => "LEE ONG'S TEA X WASPFFLE",
            'title' => 'SHIFT ITEM SOLD SUMMARY',
            'outlet_name' => $shift->outlet->name ?? '-',
            'printed_at' => $printedAt,
            'shift_id' => $shift->id,
            'cashier_name' => $shift->user->name ?? '-',
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'opening_cash' => (float) ($shift->opening_cash ?? 0),
            'closing_cash' => $shift->closing_cash_actual !== null ? (float) $shift->closing_cash_actual : null,
            'total_transactions' => (int) $transactions->count(),
            'completed_transactions' => (int) $completedTransactions->count(),
            'void_transactions' => (int) $voidTransactions->count(),
            'categories' => $shiftCategoryPayload,
            'total_qty' => $totalQty,
            'gross_sales' => $grossSales,
            'total_discount' => $totalDiscount,
            'net_sales' => $netSales,
            'payments' => $shiftPaymentPayload,
            'closing_note' => $shift->closing_note ?? null,
        ];
    @endphp

    <div class="print-actions">
        <button type="button" class="btn btn-green" onclick="window.print()">Print Shift</button>
        <button type="button" class="btn btn-dark" id="connect-bluetooth-printer-btn">Connect BT</button>
        <button type="button" class="btn btn-green" id="direct-bluetooth-print-btn">Print BT</button>
        <a href="{{ route('cashier.index') }}" class="btn btn-dark">Kembali</a>
    </div>

    <div class="bluetooth-status" id="bluetooth-printer-status">
        Bluetooth printer belum connect.
    </div>

    <div class="receipt" id="shift-receipt">
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
            const receipt = document.getElementById('shift-receipt');
            const dynamicPrintStyle = document.getElementById('dynamic-shift-print-style');
            const params = new URLSearchParams(window.location.search);
            const shiftPrintPayload = @json($shiftPrintPayload);
            const connectBluetoothButton = document.getElementById('connect-bluetooth-printer-btn');
            const directBluetoothPrintButton = document.getElementById('direct-bluetooth-print-btn');
            const bluetoothStatus = document.getElementById('bluetooth-printer-status');

            let bluetoothDevice = null;
            let bluetoothServer = null;
            let bluetoothWriteCharacteristic = null;

            function setBluetoothStatus(message) {
                if (bluetoothStatus) {
                    bluetoothStatus.textContent = message;
                }
            }

            function bytesFromText(value) {
                return new TextEncoder().encode(String(value ?? ''));
            }

            function mergeChunks(chunks) {
                const total = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
                const output = new Uint8Array(total);
                let offset = 0;

                chunks.forEach((chunk) => {
                    output.set(chunk, offset);
                    offset += chunk.length;
                });

                return output;
            }

            function cleanLine(value) {
                return String(value ?? '').replace(/\s+/g, ' ').trim();
            }

            function money(value) {
                return new Intl.NumberFormat('id-ID', {
                    maximumFractionDigits: 0
                }).format(Number(value || 0));
            }

            function padRow(left, right, width = 32) {
                const leftText = cleanLine(left);
                const rightText = cleanLine(right);
                const space = Math.max(1, width - leftText.length - rightText.length);

                if (leftText.length + rightText.length >= width) {
                    return leftText + '\n' + rightText.padStart(width, ' ');
                }

                return leftText + ' '.repeat(space) + rightText;
            }

            function wrapText(value, width = 32) {
                const words = cleanLine(value).split(' ').filter(Boolean);
                const lines = [];
                let current = '';

                words.forEach((word) => {
                    const next = current ? current + ' ' + word : word;

                    if (next.length <= width) {
                        current = next;
                        return;
                    }

                    if (current) {
                        lines.push(current);
                        current = word;
                        return;
                    }

                    lines.push(word.slice(0, width));
                });

                if (current) {
                    lines.push(current);
                }

                return lines.length ? lines : [''];
            }

            function centerText(value, width = 32) {
                const line = cleanLine(value);
                const pad = Math.max(0, Math.floor((width - line.length) / 2));

                return ' '.repeat(pad) + line;
            }

            function buildShiftEscposBytes() {
                const ESC = 0x1B;
                const GS = 0x1D;
                const widthChars = 32;
                const chunks = [];

                function raw(bytes) {
                    chunks.push(new Uint8Array(bytes));
                }

                function line(value = '') {
                    chunks.push(bytesFromText(String(value) + '\n'));
                }

                function wrapped(value = '') {
                    wrapText(value, widthChars).forEach(line);
                }

                function divider() {
                    line('-'.repeat(widthChars));
                }

                function row(left, right) {
                    line(padRow(left, right, widthChars));
                }

                raw([ESC, 0x40]);
                raw([ESC, 0x61, 0x01]);

                wrapText(shiftPrintPayload.brand_name || "LEE ONG'S TEA X WASPFFLE", widthChars).forEach((value) => {
                    line(centerText(value, widthChars));
                });

                line(centerText(shiftPrintPayload.title || 'SHIFT SUMMARY', widthChars));
                line(centerText(shiftPrintPayload.outlet_name || '-', widthChars));
                line(centerText(shiftPrintPayload.printed_at || '-', widthChars));

                raw([ESC, 0x61, 0x00]);
                divider();

                row('Shift', '#' + (shiftPrintPayload.shift_id || '-'));
                row('Cashier', shiftPrintPayload.cashier_name || '-');
                row('Start', shiftPrintPayload.started_at || '-');
                row('End', shiftPrintPayload.ended_at || '-');
                row('Opening Cash', 'Rp ' + money(shiftPrintPayload.opening_cash || 0));
                row('Closing Cash', shiftPrintPayload.closing_cash === null ? '-' : 'Rp ' + money(shiftPrintPayload.closing_cash || 0));

                divider();

                row('Total Trx', money(shiftPrintPayload.total_transactions || 0));
                row('Completed', money(shiftPrintPayload.completed_transactions || 0));
                row('Void', money(shiftPrintPayload.void_transactions || 0));

                divider();
                raw([ESC, 0x61, 0x01]);
                line(centerText('ITEM TERJUAL', widthChars));
                raw([ESC, 0x61, 0x00]);
                divider();

                if (Array.isArray(shiftPrintPayload.categories) && shiftPrintPayload.categories.length) {
                    shiftPrintPayload.categories.forEach((category) => {
                        raw([ESC, 0x45, 0x01]);
                        raw([ESC, 0x61, 0x01]);
                        line(centerText(category.category_name || '-', widthChars));
                        raw([ESC, 0x61, 0x00]);
                        raw([ESC, 0x45, 0x00]);

                        if (Array.isArray(category.items)) {
                            category.items.forEach((item) => {
                                wrapText('*' + (item.name || '-'), widthChars).forEach(line);
                                row(money(item.qty || 0), money(item.line_total || 0));
                            });
                        }

                        row('Total', money(category.line_total || 0));
                        line('');
                    });
                } else {
                    raw([ESC, 0x61, 0x01]);
                    line(centerText('Belum ada item terjual.', widthChars));
                    raw([ESC, 0x61, 0x00]);
                }

                divider();

                row('Total Item', money(shiftPrintPayload.total_qty || 0));
                row('Gross Sales', 'Rp ' + money(shiftPrintPayload.gross_sales || 0));
                row('Discount', '- Rp ' + money(shiftPrintPayload.total_discount || 0));

                raw([ESC, 0x45, 0x01]);
                row('Net Sales', 'Rp ' + money(shiftPrintPayload.net_sales || 0));
                raw([ESC, 0x45, 0x00]);

                if (Array.isArray(shiftPrintPayload.payments) && shiftPrintPayload.payments.length) {
                    divider();
                    raw([ESC, 0x61, 0x01]);
                    line(centerText('PAYMENT', widthChars));
                    raw([ESC, 0x61, 0x00]);
                    divider();

                    shiftPrintPayload.payments.forEach((payment) => {
                        row(payment.method || '-', 'Rp ' + money(payment.amount || 0));
                    });
                }

                if (shiftPrintPayload.closing_note) {
                    divider();
                    wrapped('Note: ' + shiftPrintPayload.closing_note);
                }

                divider();
                raw([ESC, 0x61, 0x01]);
                line(centerText('End of shift summary', widthChars));
                line('');
                line('');
                line('');
                raw([GS, 0x56, 0x42, 0x00]);

                return mergeChunks(chunks);
            }

            async function writeBluetoothInChunks(characteristic, data) {
                const chunkSize = 180;

                for (let i = 0; i < data.length; i += chunkSize) {
                    const chunk = data.slice(i, i + chunkSize);

                    if (characteristic.writeValueWithoutResponse) {
                        await characteristic.writeValueWithoutResponse(chunk);
                    } else {
                        await characteristic.writeValue(chunk);
                    }

                    await new Promise(resolve => setTimeout(resolve, 40));
                }
            }

            async function findWritableCharacteristic(server) {
                const services = await server.getPrimaryServices();

                for (const service of services) {
                    let characteristics = [];

                    try {
                        characteristics = await service.getCharacteristics();
                    } catch (error) {
                        continue;
                    }

                    for (const characteristic of characteristics) {
                        if (characteristic.properties.write || characteristic.properties.writeWithoutResponse) {
                            return characteristic;
                        }
                    }
                }

                return null;
            }

            async function connectBluetoothPrinter() {
                if (!navigator.bluetooth) {
                    setBluetoothStatus('Browser tidak support Web Bluetooth.');
                    return;
                }

                setBluetoothStatus('Mencari printer Bluetooth...');

                bluetoothDevice = await navigator.bluetooth.requestDevice({
                    acceptAllDevices: true,
                    optionalServices: [
                        0x1800,
                        0x1801,
                        '0000ffe0-0000-1000-8000-00805f9b34fb',
                        '0000fff0-0000-1000-8000-00805f9b34fb',
                        '0000ff00-0000-1000-8000-00805f9b34fb',
                        '49535343-fe7d-4ae5-8fa9-9fafd205e455',
                        'e7810a71-73ae-499d-8c15-faa9aef0c3f2'
                    ]
                });

                setBluetoothStatus('Menghubungkan ke ' + (bluetoothDevice.name || 'printer') + '...');

                bluetoothServer = await bluetoothDevice.gatt.connect();
                bluetoothWriteCharacteristic = await findWritableCharacteristic(bluetoothServer);

                if (!bluetoothWriteCharacteristic) {
                    setBluetoothStatus('Printer connect, tapi writable characteristic tidak ditemukan.');
                    return;
                }

                setBluetoothStatus('Printer siap: ' + (bluetoothDevice.name || 'Bluetooth Printer'));
            }

            async function directBluetoothPrintShift() {
                try {
                    if (!bluetoothWriteCharacteristic || !bluetoothDevice?.gatt?.connected) {
                        await connectBluetoothPrinter();
                    }

                    if (!bluetoothWriteCharacteristic) {
                        return;
                    }

                    setBluetoothStatus('Mengirim shift summary ke printer...');
                    await writeBluetoothInChunks(bluetoothWriteCharacteristic, buildShiftEscposBytes());
                    setBluetoothStatus('Shift summary berhasil dikirim ke printer.');
                } catch (error) {
                    setBluetoothStatus('Bluetooth print error: ' + error.message);
                }
            }

            if (connectBluetoothButton) {
                connectBluetoothButton.addEventListener('click', function () {
                    connectBluetoothPrinter().catch((error) => {
                        setBluetoothStatus('Bluetooth connect error: ' + error.message);
                    });
                });
            }

            if (directBluetoothPrintButton) {
                directBluetoothPrintButton.addEventListener('click', directBluetoothPrintShift);
            }

            function updatePrintSize() {
                if (!receipt || !dynamicPrintStyle) {
                    return;
                }

                const receiptWidthPx = receipt.offsetWidth || 1;
                const receiptHeightPx = receipt.scrollHeight || receipt.offsetHeight || 1;
                const shiftHeightMm = Math.max(90, Math.ceil((receiptHeightPx / receiptWidthPx) * 58) + 8);

                dynamicPrintStyle.textContent = `
                    @media print {
                        @page {
                            size: 58mm ${shiftHeightMm}mm;
                            margin: 0;
                        }

                        html,
                        body {
                            width: 58mm !important;
                            min-width: 58mm !important;
                            max-width: 58mm !important;
                            height: ${shiftHeightMm}mm !important;
                            min-height: ${shiftHeightMm}mm !important;
                            max-height: ${shiftHeightMm}mm !important;
                            margin: 0 !important;
                            padding: 0 !important;
                            background: #ffffff !important;
                        }

                        #shift-receipt {
                            width: 58mm !important;
                            min-width: 58mm !important;
                            max-width: 58mm !important;
                            height: auto !important;
                            min-height: 0 !important;
                            margin: 0 !important;
                            padding: 4mm 3mm 5mm !important;
                            box-shadow: none !important;
                        }
                    }
                `;
            }

            updatePrintSize();

            setTimeout(function () {
                updatePrintSize();

                if (params.get('autoprint') === '1') {
                    window.print();
                }
            }, 150);
        });
    </script>
</body>
</html>
