<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfers - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
        }

        .wrap {
            max-width: 1680px;
            margin: 36px auto;
            padding: 0 20px 40px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .title {
            font-size: 30px;
            font-weight: 800;
            color: #111827;
        }

        .actions {
            display: flex;
            gap: 10px;
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

        .btn-primary { background: #166534; }
        .btn-dark { background: #111827; }
        .btn-filter { background: #166534; }
        .btn-reset { background: #6b7280; }
        .btn-warning { background: #b45309; }
        .btn-danger { background: #b91c1c; }
        .btn-info { background: #1d4ed8; }
        .btn-secondary { background: #475569; }

        .btn-small {
            min-height: 34px;
            padding: 8px 12px;
            font-size: 12px;
            border-radius: 10px;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .info, .success, .filter-box {
            margin-bottom: 18px;
            border-radius: 14px;
            padding: 14px 16px;
        }

        .info {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            line-height: 1.75;
        }

        .success {
            background: #e8fff1;
            color: #17663a;
            border: 1px solid #ccefd8;
            font-weight: 700;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .summary-card {
            border-radius: 18px;
            padding: 18px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
            background: white;
        }

        .summary-label {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 10px;
            color: #6b7280;
        }

        .summary-value {
            font-size: 34px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 8px;
        }

        .summary-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.5;
        }

        .summary-total {
            background: linear-gradient(180deg, #f8faff 0%, #ffffff 100%);
            border-color: #dbe7ff;
        }

        .summary-total .summary-value {
            color: #1d4ed8;
        }

        .summary-transit {
            background: linear-gradient(180deg, #eff6ff 0%, #ffffff 100%);
            border-color: #bfdbfe;
        }

        .summary-transit .summary-value {
            color: #1d4ed8;
        }

        .summary-received {
            background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
            border-color: #bbf7d0;
        }

        .summary-received .summary-value {
            color: #166534;
        }

        .summary-cancelled {
            background: linear-gradient(180deg, #fff1f2 0%, #ffffff 100%);
            border-color: #fecdd3;
        }

        .summary-cancelled .summary-value {
            color: #b91c1c;
        }

        .filter-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }

        .filter-title {
            font-size: 16px;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr auto auto;
            gap: 12px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #4b5563;
        }

        .field select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 12px 13px;
            font-size: 14px;
            min-height: 44px;
            background: white;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 2200px;
            background: white;
        }

        th, td {
            text-align: left;
            padding: 15px 14px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            background: #f9fafb;
            font-size: 12px;
            color: #6b7280;
            font-weight: 700;
            text-transform: uppercase;
        }

        .number {
            font-weight: 800;
            color: #3730a3;
        }

        .qty {
            font-weight: 800;
            color: #1d4ed8;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .badge-pending {
            background: #fff7ed;
            color: #9a3412;
        }

        .badge-in-transit {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .badge-received {
            background: #e8fff1;
            color: #17663a;
        }

        .badge-cancelled {
            background: #ffe8e8;
            color: #9b1c1c;
        }

        .action-stack {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            min-width: 210px;
        }

        .inline-form {
            display: inline-block;
            margin: 0;
        }

        .status-note {
            margin-top: 4px;
            font-size: 12px;
            color: #6b7280;
            line-height: 1.4;
        }

        .empty {
            padding: 18px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 14px;
            margin-top: 12px;
            font-weight: 700;
            border: 1px solid #fed7aa;
        }

        .note {
            margin-top: 20px;
            background: #eef2ff;
            color: #3730a3;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #dbe3ff;
        }

        @media (max-width: 1100px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .actions {
                flex-wrap: wrap;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title">Back Office - Transfers</div>

            <div class="actions">
                <a href="{{ route('backoffice.transfers.create') }}" class="btn btn-primary">Buat Transfer</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Kembali</a>
            </div>
        </div>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="info">
                <strong>User:</strong> {{ $user->name }}<br>
                <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}
            </div>

            <div class="summary-grid">
                <div class="summary-card summary-total">
                    <div class="summary-label">Total Transfer</div>
                    <div class="summary-value">{{ $summary['total'] ?? 0 }}</div>
                    <div class="summary-desc">Total semua transfer yang tampil sesuai filter aktif.</div>
                </div>

                <div class="summary-card summary-transit">
                    <div class="summary-label">In Transit</div>
                    <div class="summary-value">{{ $summary['in_transit'] ?? 0 }}</div>
                    <div class="summary-desc">Transfer yang masih dalam proses pengiriman.</div>
                </div>

                <div class="summary-card summary-received">
                    <div class="summary-label">Received</div>
                    <div class="summary-value">{{ $summary['received'] ?? 0 }}</div>
                    <div class="summary-desc">Transfer yang sudah diterima di lokasi tujuan.</div>
                </div>

                <div class="summary-card summary-cancelled">
                    <div class="summary-label">Cancelled</div>
                    <div class="summary-value">{{ $summary['cancelled'] ?? 0 }}</div>
                    <div class="summary-desc">Transfer yang dibatalkan dan tidak dilanjutkan.</div>
                </div>
            </div>

            <form method="GET" action="{{ route('backoffice.transfers.index') }}" class="filter-box">
                <div class="filter-title">Filter Transfer</div>

                <div class="filter-grid">
                    <div class="field">
                        <label>Dari Tipe</label>
                        <select name="from_location_type">
                            <option value="">Semua asal</option>
                            <option value="warehouse" @selected(($filters['from_location_type'] ?? '') === 'warehouse')>warehouse</option>
                            <option value="outlet" @selected(($filters['from_location_type'] ?? '') === 'outlet')>outlet</option>
                        </select>
                    </div>

                    <div class="field">
                        <label>Ke Tipe</label>
                        <select name="to_location_type">
                            <option value="">Semua tujuan</option>
                            <option value="warehouse" @selected(($filters['to_location_type'] ?? '') === 'warehouse')>warehouse</option>
                            <option value="outlet" @selected(($filters['to_location_type'] ?? '') === 'outlet')>outlet</option>
                        </select>
                    </div>

                    <div class="field">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Semua status</option>
                            <option value="pending" @selected(($filters['status'] ?? '') === 'pending')>pending</option>
                            <option value="in_transit" @selected(($filters['status'] ?? '') === 'in_transit')>in_transit</option>
                            <option value="received" @selected(($filters['status'] ?? '') === 'received')>received</option>
                            <option value="cancelled" @selected(($filters['status'] ?? '') === 'cancelled')>cancelled</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-filter">Apply Filter</button>
                    <a href="{{ route('backoffice.transfers.index') }}" class="btn btn-reset">Reset</a>
                </div>
            </form>

            @if($transfers->count())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Transfer No</th>
                                <th>Date</th>
                                <th>Dari Tipe</th>
                                <th>Dari Lokasi</th>
                                <th>Ke Tipe</th>
                                <th>Ke Lokasi</th>
                                <th>Category</th>
                                <th>Ingredient</th>
                                <th>Qty</th>
                                <th>Status</th>
                                <th>Dikirim Oleh</th>
                                <th>Diterima Oleh</th>
                                <th>Tanggal Dikirim</th>
                                <th>Tanggal Diterima</th>
                                <th>User Input</th>
                                <th>Note</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transfers as $transfer)
                                @php
                                    $badgeClass = match($transfer->status) {
                                        'pending' => 'badge-pending',
                                        'received' => 'badge-received',
                                        'cancelled' => 'badge-cancelled',
                                        default => 'badge-in-transit',
                                    };
                                @endphp
                                <tr>
                                    <td class="number">{{ $transfer->transfer_number ?? '-' }}</td>
                                    <td>{{ $transfer->created_at?->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ $transfer->from_location_type ?? '-' }}</td>
                                    <td>{{ $transfer->from_location_name ?? '-' }}</td>
                                    <td>{{ $transfer->to_location_type ?? '-' }}</td>
                                    <td>{{ $transfer->to_location_name ?? '-' }}</td>
                                    <td>{{ $transfer->ingredient->category->name ?? '-' }}</td>
                                    <td>{{ $transfer->ingredient->name ?? '-' }}</td>
                                    <td class="qty">{{ number_format((float) $transfer->qty, 0, ',', '.') }}</td>
                                    <td>
                                        <span class="badge {{ $badgeClass }}">{{ strtoupper($transfer->status ?? '-') }}</span>
                                        <div class="status-note">
                                            @if($transfer->status === 'in_transit')
                                                Barang sedang dalam proses pengiriman.
                                            @elseif($transfer->status === 'received')
                                                Barang sudah diterima di lokasi tujuan.
                                            @elseif($transfer->status === 'cancelled')
                                                Transfer dibatalkan.
                                            @elseif($transfer->status === 'pending')
                                                Transfer belum dikirim.
                                            @else
                                                Status transfer aktif.
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $transfer->sender_name ?? '-' }}</td>
                                    <td>{{ $transfer->receiver_name ?? '-' }}</td>
                                    <td>{{ $transfer->sent_at ? $transfer->sent_at->format('Y-m-d H:i') : '-' }}</td>
                                    <td>{{ $transfer->received_at ? $transfer->received_at->format('Y-m-d H:i') : '-' }}</td>
                                    <td>{{ $transfer->transferredBy->name ?? '-' }}</td>
                                    <td>{{ $transfer->note ?? '-' }}</td>
                                    <td>
                                        <div class="action-stack">
                                            @if($transfer->status === 'pending')
                                                <form method="POST" action="{{ route('backoffice.transfers.mark-in-transit', $transfer) }}" class="inline-form" onsubmit="return confirm('Ubah status transfer ini menjadi in transit?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-info btn-small">Kirimkan</button>
                                                </form>

                                                <form method="POST" action="{{ route('backoffice.transfers.mark-cancelled', $transfer) }}" class="inline-form" onsubmit="return confirm('Batalkan transfer ini?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-danger btn-small">Batalkan</button>
                                                </form>
                                            @elseif($transfer->status === 'in_transit')
                                                <form method="POST" action="{{ route('backoffice.transfers.mark-received', $transfer) }}" class="inline-form" onsubmit="return confirm('Tandai transfer ini sebagai diterima?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-info btn-small">Tandai Diterima</button>
                                                </form>

                                                <form method="POST" action="{{ route('backoffice.transfers.mark-cancelled', $transfer) }}" class="inline-form" onsubmit="return confirm('Batalkan transfer ini?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-danger btn-small">Batalkan</button>
                                                </form>
                                            @elseif($transfer->status === 'received')
                                                <form method="POST" action="{{ route('backoffice.transfers.mark-in-transit', $transfer) }}" class="inline-form" onsubmit="return confirm('Kembalikan status transfer ini ke in transit?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-warning btn-small">Kembalikan ke In Transit</button>
                                                </form>
                                            @elseif($transfer->status === 'cancelled')
                                                <form method="POST" action="{{ route('backoffice.transfers.mark-in-transit', $transfer) }}" class="inline-form" onsubmit="return confirm('Aktifkan lagi transfer ini ke status in transit?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-secondary btn-small">Aktifkan Lagi</button>
                                                </form>
                                            @else
                                                <span style="color:#6b7280; font-size:12px; font-weight:700;">Tidak ada aksi</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">Belum ada transfer tersimpan.</div>
            @endif

            <div class="note">
                Transfer 3F aktif: halaman transfer sekarang punya card summary status di atas tabel supaya monitoring lebih cepat dibaca.
            </div>
        </div>
    </div>
</body>
</html>