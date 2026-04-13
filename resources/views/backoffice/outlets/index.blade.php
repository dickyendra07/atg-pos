<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outlets - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
        }

        .wrap {
            max-width: 1400px;
            margin: 36px auto;
            padding: 0 20px 40px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
        }

        .title {
            font-size: 32px;
            font-weight: 700;
            color: #111827;
        }

        .subtitle {
            margin-top: 6px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
            max-width: 780px;
        }

        .actions {
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
        }

        .btn-primary {
            background: #e86a3a;
        }

        .btn-dark {
            background: #111827;
        }

        .btn-info {
            background: #2563eb;
        }

        .btn-danger {
            background: #dc2626;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .success {
            margin-bottom: 18px;
            background: #e8fff1;
            color: #17663a;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #ccefd8;
        }

        .error {
            margin-bottom: 18px;
            background: #ffe8e8;
            color: #9b1c1c;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #fecaca;
        }

        .helper {
            margin-bottom: 20px;
            background: #eef2ff;
            color: #3730a3;
            padding: 15px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #dbe3ff;
            line-height: 1.75;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            min-width: 1180px;
        }

        th, td {
            text-align: left;
            padding: 15px 14px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
            font-size: 14px;
        }

        th {
            background: #f9fafb;
            font-size: 12px;
            color: #6b7280;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .code-text {
            font-weight: 700;
            color: #374151;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
        }

        .badge-active {
            background: #e8fff1;
            color: #17663a;
        }

        .badge-inactive {
            background: #ffe8e8;
            color: #9b1c1c;
        }

        .action-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .delete-form {
            margin: 0;
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
            background: #fff7ed;
            color: #b45309;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #fed7aa;
            line-height: 1.7;
        }

        @media (max-width: 860px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div>
                <div class="title">Back Office - Outlets</div>
                <div class="subtitle">
                    Halaman ini dipakai untuk mengelola outlet aktif sebagai lokasi operasional penjualan dan inventory. Data outlet di sini akan dipakai untuk cashier, stock movement, transfer antar lokasi, dan referensi opening stock import.
                </div>
            </div>

            <div class="actions">
                <a href="{{ route('backoffice.outlets.create') }}" class="btn btn-primary">Tambah Outlet</a>
                <a href="{{ route('backoffice.index') }}" class="btn btn-dark">Kembali</a>
            </div>
        </div>

        @if(session('success'))
            <div class="success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="error">
                {{ session('error') }}
            </div>
        @endif

        <div class="card">
            <div class="helper">
                Outlet adalah lokasi operasional yang dipakai untuk transaksi cashier, stock intake outlet, adjustment outlet, dan transfer antar lokasi. Setiap outlet punya <strong>ID lokasi otomatis</strong> yang bisa dipakai sebagai referensi pada CSV import.
            </div>

            @if($outlets->count())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nama Outlet</th>
                                <th>Kode</th>
                                <th>Alamat</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($outlets as $outlet)
                                <tr>
                                    <td>{{ $outlet->id }}</td>
                                    <td>{{ $outlet->name }}</td>
                                    <td><span class="code-text">{{ $outlet->code }}</span></td>
                                    <td>{{ $outlet->address ?? '-' }}</td>
                                    <td>{{ $outlet->phone ?? '-' }}</td>
                                    <td>
                                        @if($outlet->is_active)
                                            <span class="badge badge-active">Active</span>
                                        @else
                                            <span class="badge badge-inactive">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="action-row">
                                            <a href="{{ route('backoffice.outlets.edit', $outlet) }}" class="btn btn-info">Edit</a>

                                            <form method="POST" action="{{ route('backoffice.outlets.destroy', $outlet) }}" class="delete-form" onsubmit="return confirm('Yakin hapus outlet ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger">Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">Belum ada outlet tersimpan.</div>
            @endif

            <div class="note">
                Kolom <strong>ID</strong> ditampilkan supaya lebih mudah dipakai sebagai referensi untuk <strong>location_id</strong> di import opening stock. Tombol hapus tetap memakai pengaman konfirmasi dasar agar tidak salah hapus data outlet.
            </div>
        </div>
    </div>
</body>
</html>