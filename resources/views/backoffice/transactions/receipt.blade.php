<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $transaction->transaction_number ?? 'ATG POS' }}</title>

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: #eef2f7;
            color: #111827;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            padding: 18px;
        }

        .page-shell {
            width: 100%;
            display: grid;
            gap: 14px;
            justify-items: center;
        }

        .toolbar {
            width: min(520px, 100%);
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 12px;
            border-radius: 18px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.10);
        }

        .btn {
            border: 0;
            cursor: pointer;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 12px;
            background: #111827;
            color: #ffffff;
            font-size: 13px;
            font-weight: 800;
        }

        .btn.secondary {
            background: #e5e7eb;
            color: #111827;
        }

        .btn.green {
            background: #166534;
        }

        .hint {
            width: min(520px, 100%);
            padding: 12px 14px;
            border-radius: 16px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 13px;
            line-height: 1.6;
            font-weight: 700;
        }

        .receipt-preview {
            width: min(420px, 100%);
            display: flex;
            justify-content: center;
            padding: 14px;
            border-radius: 22px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.12);
        }

        #receipt-image {
            width: 384px;
            max-width: 100%;
            height: auto;
            display: block;
            background: #ffffff;
        }

        #receipt-canvas {
            display: none;
        }

        @page {
            size: 58mm auto;
            margin: 0;
        }

        @media print {
            html,
            body {
                width: 58mm;
                min-width: 58mm;
                max-width: 58mm;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }

            .toolbar,
            .hint {
                display: none !important;
            }

            .page-shell,
            .receipt-preview {
                width: 58mm !important;
                min-width: 58mm !important;
                max-width: 58mm !important;
                margin: 0 !important;
                padding: 0 !important;
                display: block !important;
                border: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                background: #ffffff !important;
            }

            #receipt-image {
                width: 58mm !important;
                min-width: 58mm !important;
                max-width: 58mm !important;
                height: auto !important;
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>
<body>
@php
    $source = $source ?? request('source', 'backoffice');
    $autoprint = (bool) ($autoprint ?? request('autoprint'));
    $autoClose = (bool) request('autoclose', false);

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

    $items = $transaction->items->map(function ($item) {
        $modifiers = [];

        if (! empty($item->less_sugar)) {
            $modifiers[] = 'Less Sugar';
        }

        if (! empty($item->less_ice)) {
            $modifiers[] = 'Less Ice';
        }

        return [
            'product_name' => $item->product_name ?? '-',
            'variant_name' => $item->variant_name ?? '',
            'modifiers' => $modifiers,
            'qty' => (float) ($item->qty ?? 0),
            'price' => (float) ($item->price ?? 0),
            'line_total' => (float) ($item->line_total ?? 0),
        ];
    })->values();

    $receiptPayload = [
        'outlet_name' => $outletName,
        'transaction_number' => $transactionNumber,
        'cashier_name' => $cashierName,
        'member_name' => $memberName,
        'member_phone' => $memberPhone,
        'created_at' => $createdAt,
        'payment_method' => $paymentMethod,
        'status' => $status,
        'is_void' => $isVoid,
        'subtotal' => $subtotal,
        'discount_amount' => $discountAmount,
        'tax_amount' => $taxAmount,
        'grand_total' => $grandTotal,
        'amount_paid' => $amountPaid,
        'change_amount' => $changeAmount,
        'items' => $items,
        'void_at' => ! empty($transaction->void_at) ? $transaction->void_at?->format('Y-m-d H:i:s') : null,
        'void_by' => $transaction->voidBy->name ?? null,
        'void_reason' => $transaction->void_reason ?? null,
    ];
@endphp

<div class="page-shell">
    <div class="toolbar">
        <button type="button" class="btn green" onclick="window.print()">Print PNG Receipt</button>
        <button type="button" class="btn secondary" id="download-receipt-btn">Download PNG</button>

        @if($source === 'cashier')
            <button type="button" class="btn secondary" onclick="window.location.href='{{ route('cashier.index') }}'">
                Kembali ke Cashier
            </button>
        @else
            <button type="button" class="btn secondary" onclick="window.history.back()">
                Kembali
            </button>
        @endif
    </div>

    <div class="hint">
        Preview ini dibuat sebagai gambar PNG fixed width 384px untuk thermal 58mm. Kalau print dari Android masih kecil, berarti driver printer Android tetap melakukan scaling otomatis.
    </div>

    <div class="receipt-preview">
        <img id="receipt-image" alt="Thermal Receipt Preview">
        <canvas id="receipt-canvas"></canvas>
    </div>
</div>

<script>
    (function () {
        const receipt = @json($receiptPayload);
        const shouldAutoPrint = @json($autoprint);
        const shouldAutoClose = @json($autoClose);

        const canvas = document.getElementById('receipt-canvas');
        const image = document.getElementById('receipt-image');
        const downloadButton = document.getElementById('download-receipt-btn');

        const width = 384;
        const paddingX = 18;
        const topPadding = 18;
        const bottomPadding = 28;
        const lineGap = 6;
        const fontFamily = '"Courier New", monospace';

        const ctx = canvas.getContext('2d');

        function money(value) {
            return new Intl.NumberFormat('id-ID', {
                maximumFractionDigits: 0
            }).format(Number(value || 0));
        }

        function text(value) {
            return String(value ?? '').replace(/\s+/g, ' ').trim();
        }

        function setFont(size, weight = 'normal') {
            ctx.font = `${weight} ${size}px ${fontFamily}`;
            ctx.fillStyle = '#000000';
            ctx.textBaseline = 'top';
        }

        function splitWords(input) {
            return text(input).split(' ').filter(Boolean);
        }

        function wrapText(input, maxWidth, size = 22, weight = 'normal') {
            setFont(size, weight);

            const words = splitWords(input);

            if (!words.length) {
                return [''];
            }

            const lines = [];
            let current = '';

            words.forEach((word) => {
                const next = current ? `${current} ${word}` : word;

                if (ctx.measureText(next).width <= maxWidth) {
                    current = next;
                    return;
                }

                if (current) {
                    lines.push(current);
                    current = word;
                    return;
                }

                lines.push(word);
            });

            if (current) {
                lines.push(current);
            }

            return lines;
        }

        function buildLines() {
            const maxTextWidth = width - (paddingX * 2);
            const lines = [];

            function push(type, value = '', options = {}) {
                lines.push({
                    type,
                    value,
                    size: options.size || 22,
                    weight: options.weight || 'normal',
                    align: options.align || 'left',
                    gap: options.gap ?? lineGap,
                });
            }

            function pushWrapped(value, options = {}) {
                const wrapped = wrapText(value, maxTextWidth, options.size || 22, options.weight || 'normal');

                wrapped.forEach((line) => {
                    push('text', line, options);
                });
            }

            function divider() {
                push('divider', '', { gap: 10 });
            }

            function row(left, right, options = {}) {
                lines.push({
                    type: 'row',
                    left: text(left),
                    right: text(right),
                    size: options.size || 22,
                    weight: options.weight || 'normal',
                    gap: options.gap ?? lineGap,
                });
            }

            pushWrapped(receipt.outlet_name || 'ATG POS', {
                size: 28,
                weight: '700',
                align: 'center',
                gap: 4,
            });

            push('text', 'ATG POS RECEIPT', {
                size: 20,
                weight: '700',
                align: 'center',
                gap: 4,
            });

            push('text', receipt.created_at || '-', {
                size: 20,
                align: 'center',
                gap: 8,
            });

            if (receipt.is_void) {
                push('text', '*** VOID ***', {
                    size: 24,
                    weight: '700',
                    align: 'center',
                    gap: 8,
                });
            }

            divider();

            row('No', receipt.transaction_number || '-', { size: 20 });
            row('Cashier', receipt.cashier_name || '-', { size: 20 });
            row('Payment', receipt.payment_method || '-', { size: 20 });
            row('Status', receipt.status || '-', { size: 20 });

            if (receipt.member_name || receipt.member_phone) {
                row('Member', receipt.member_name || '-', { size: 20 });

                if (receipt.member_phone) {
                    row('Phone', receipt.member_phone, { size: 20 });
                }
            }

            divider();

            if (Array.isArray(receipt.items) && receipt.items.length) {
                receipt.items.forEach((item) => {
                    pushWrapped(item.product_name || '-', {
                        size: 23,
                        weight: '700',
                        gap: 4,
                    });

                    if (item.variant_name) {
                        pushWrapped(item.variant_name, {
                            size: 20,
                            gap: 3,
                        });
                    }

                    if (Array.isArray(item.modifiers) && item.modifiers.length) {
                        pushWrapped(item.modifiers.join(' / '), {
                            size: 19,
                            gap: 3,
                        });
                    }

                    row(
                        `${money(item.qty)} x ${money(item.price)}`,
                        money(item.line_total),
                        { size: 21, gap: 10 }
                    );
                });
            } else {
                push('text', 'Tidak ada item.', {
                    size: 21,
                    align: 'center',
                    gap: 8,
                });
            }

            divider();

            row('Subtotal', money(receipt.subtotal), { size: 21 });

            if (Number(receipt.discount_amount || 0) > 0) {
                row('Discount', `-${money(receipt.discount_amount)}`, { size: 21 });
            }

            if (Number(receipt.tax_amount || 0) > 0) {
                row('Tax', money(receipt.tax_amount), { size: 21 });
            }

            push('solid', '', { gap: 10 });
            row('TOTAL', money(receipt.grand_total), {
                size: 28,
                weight: '700',
                gap: 10,
            });
            push('solid', '', { gap: 10 });

            row('Paid', money(receipt.amount_paid), { size: 21 });
            row('Change', money(receipt.change_amount), { size: 21 });

            if (receipt.is_void) {
                divider();

                push('text', 'VOID INFO', {
                    size: 22,
                    weight: '700',
                    align: 'center',
                    gap: 8,
                });

                if (receipt.void_at) {
                    row('Void At', receipt.void_at, { size: 19 });
                }

                if (receipt.void_by) {
                    row('Void By', receipt.void_by, { size: 19 });
                }

                if (receipt.void_reason) {
                    pushWrapped(`Reason: ${receipt.void_reason}`, {
                        size: 19,
                        gap: 5,
                    });
                }
            }

            divider();

            push('text', 'Terima kasih', {
                size: 22,
                weight: '700',
                align: 'center',
                gap: 4,
            });

            push('text', 'Simpan struk ini sebagai bukti transaksi', {
                size: 18,
                align: 'center',
                gap: 4,
            });

            push('text', 'Powered by ATG POS', {
                size: 18,
                align: 'center',
                gap: 4,
            });

            return lines;
        }

        function measureHeight(lines) {
            let height = topPadding + bottomPadding;

            lines.forEach((line) => {
                if (line.type === 'divider' || line.type === 'solid') {
                    height += 14 + line.gap;
                    return;
                }

                height += line.size + line.gap;
            });

            return Math.max(420, height);
        }

        function drawLines(lines) {
            const maxTextWidth = width - (paddingX * 2);
            let y = topPadding;

            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            lines.forEach((line) => {
                if (line.type === 'divider' || line.type === 'solid') {
                    ctx.strokeStyle = '#000000';
                    ctx.lineWidth = line.type === 'solid' ? 2 : 1;
                    ctx.setLineDash(line.type === 'divider' ? [8, 5] : []);
                    ctx.beginPath();
                    ctx.moveTo(paddingX, y + 5);
                    ctx.lineTo(width - paddingX, y + 5);
                    ctx.stroke();
                    ctx.setLineDash([]);
                    y += 14 + line.gap;
                    return;
                }

                setFont(line.size, line.weight);

                if (line.type === 'row') {
                    const rightWidth = ctx.measureText(line.right).width;
                    const leftMaxWidth = maxTextWidth - rightWidth - 12;
                    const leftLines = wrapText(line.left, leftMaxWidth, line.size, line.weight);

                    leftLines.forEach((leftLine, index) => {
                        ctx.fillText(leftLine, paddingX, y);

                        if (index === 0) {
                            ctx.fillText(line.right, width - paddingX - rightWidth, y);
                        }

                        y += line.size + 2;
                    });

                    y += line.gap;
                    return;
                }

                const value = text(line.value);
                const textWidth = ctx.measureText(value).width;
                let x = paddingX;

                if (line.align === 'center') {
                    x = Math.max(paddingX, (width - textWidth) / 2);
                } else if (line.align === 'right') {
                    x = width - paddingX - textWidth;
                }

                ctx.fillText(value, x, y);
                y += line.size + line.gap;
            });
        }

        function renderReceipt() {
            canvas.width = width;

            const lines = buildLines();
            canvas.height = measureHeight(lines);

            drawLines(lines);

            const dataUrl = canvas.toDataURL('image/png');
            image.src = dataUrl;

            if (downloadButton) {
                downloadButton.onclick = function () {
                    const link = document.createElement('a');
                    link.href = dataUrl;
                    link.download = `receipt-${receipt.transaction_number || 'atg-pos'}.png`;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                };
            }
        }

        renderReceipt();

        if (shouldAutoPrint) {
            window.addEventListener('load', function () {
                setTimeout(function () {
                    window.print();
                }, 600);
            });

            window.addEventListener('afterprint', function () {
                if (shouldAutoClose) {
                    window.close();
                }
            });

            setTimeout(function () {
                if (shouldAutoClose) {
                    window.close();
                }
            }, 4500);
        }
    })();
</script>
</body>
</html>
