<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 1350px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
        }

        .title {
            font-size: 32px;
            font-weight: bold;
            color: #111827;
        }

        .subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-top: 6px;
            line-height: 1.7;
        }

        .top-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            text-decoration: none;
            background: #111827;
            color: white;
            padding: 11px 16px;
            border-radius: 12px;
            font-weight: bold;
            display: inline-block;
            border: 0;
            cursor: pointer;
        }

        .btn-green {
            background: #166534;
        }

        .card {
            background: white;
            border-radius: 22px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
        }

        .alert {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
        }

        .alert-success {
            background: #e8fff1;
            color: #17663a;
            border: 1px solid #ccefd8;
        }

        .alert-error {
            background: #fff1f1;
            color: #b42318;
            border: 1px solid #fecaca;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 980px;
        }

        th, td {
            text-align: left;
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        th {
            background: #f9fafb;
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-active {
            background: #e8fff1;
            color: #17663a;
        }

        .status-inactive {
            background: #fff1f1;
            color: #b42318;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div>
                <div class="title">User Management</div>
                <div class="subtitle">
                    Tambah dan atur user kasir / admin beserta role dan outlet akses utamanya.
                </div>
            </div>

            <div class="top-actions">
                <a href="{{ route('backoffice.users.create') }}" class="btn btn-green">Tambah User</a>
                <a href="{{ route('backoffice.index') }}" class="btn">Back Office</a>
            </div>
        </div>

        <div class="card">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Outlet</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $managedUser)
                            <tr>
                                <td><strong>{{ $managedUser->name }}</strong></td>
                                <td>{{ $managedUser->email }}</td>
                                <td>{{ $managedUser->phone ?? '-' }}</td>
                                <td>{{ $managedUser->role->name ?? '-' }}</td>
                                <td>{{ $managedUser->outlet->name ?? '-' }}</td>
                                <td>
                                    @if($managedUser->is_active)
                                        <span class="status-badge status-active">Active</span>
                                    @else
                                        <span class="status-badge status-inactive">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ $managedUser->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td>
                                    <a href="{{ route('backoffice.users.edit', $managedUser->id) }}" class="btn">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">Belum ada user.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>