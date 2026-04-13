<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 1450px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .top-actions {
            display: flex;
            gap: 12px;
        }

        .title {
            font-size: 28px;
            font-weight: bold;
        }

        .btn {
            text-decoration: none;
            background: #111827;
            color: white;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: bold;
            display: inline-block;
            border: 0;
            cursor: pointer;
        }

        .btn-success {
            background: #166534;
            color: white;
        }

        .btn-warning {
            background: #1d4ed8;
            color: white;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 12px;
        }

        .btn-danger {
            background: #b91c1c;
            color: white;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 12px;
        }

        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
        }

        .info {
            margin-bottom: 18px;
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
        }

        .success {
            margin-bottom: 18px;
            background: #e8fff1;
            color: #17663a;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
        }

        .error {
            margin-bottom: 18px;
            background: #ffe8e8;
            color: #9b1c1c;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
        }

        .table-wrap {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1380px;
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
        }

        .variant-pill {
            display: inline-block;
            background: #eef2ff;
            color: #3730a3;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            margin: 2px 6px 2px 0;
        }

        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-active {
            background: #e8fff1;
            color: #17663a;
        }

        .badge-inactive {
            background: #ffe8e8;
            color: #9b1c1c;
        }

        .note {
            margin-top: 20px;
            background: #e8fff1;
            color: #17663a;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
        }

        .empty {
            padding: 16px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 12px;
            margin-top: 16px;
            font-weight: bold;
        }

        .action-cell {
            white-space: nowrap;
        }

        .inline-form {
            display: inline-block;
            margin-left: 6px;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title">Back Office - Products</div>

            <div class="top-actions">
                <a href="{{ route('backoffice.products.create') }}" class="btn btn-success">Tambah Product</a>
                <a href="{{ route('backoffice.index') }}" class="btn">Kembali</a>
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
            <div class="info">
                <strong>User:</strong> {{ $user->name }}<br>
                <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}
            </div>

            @if($products->count())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Brand</th>
                                <th>Category</th>
                                <th>Product</th>
                                <th>Code</th>
                                <th>Description</th>
                                <th>Variants</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $product)
                                <tr>
                                    <td>{{ $product->brand->name ?? '-' }}</td>
                                    <td>{{ $product->category->name ?? '-' }}</td>
                                    <td><strong>{{ $product->name }}</strong></td>
                                    <td>{{ $product->code }}</td>
                                    <td>{{ $product->description ?? '-' }}</td>
                                    <td>
                                        @forelse($product->variants as $variant)
                                            <span class="variant-pill">
                                                {{ $variant->name }} - Rp{{ number_format((float) $variant->price, 0, ',', '.') }}
                                            </span>
                                        @empty
                                            -
                                        @endforelse
                                    </td>
                                    <td>
                                        @if($product->is_active)
                                            <span class="badge badge-active">Active</span>
                                        @else
                                            <span class="badge badge-inactive">Inactive</span>
                                        @endif
                                    </td>
                                    <td class="action-cell">
                                        <a href="{{ route('backoffice.products.edit', $product->id) }}" class="btn-warning">Edit</a>

                                        <form method="POST" action="{{ route('backoffice.products.destroy', $product->id) }}" class="inline-form" onsubmit="return confirm('Yakin mau hapus product ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty">
                    Belum ada product tersimpan.
                </div>
            @endif

            <div class="note">
                CRUD Products 1C aktif: sekarang back office sudah bisa hapus product, dengan pengaman kalau product masih punya variants.
            </div>
        </div>
    </div>
</body>
</html>