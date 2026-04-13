<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Outlet - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f6fb;
            color: #1f2937;
        }

        .wrap {
            max-width: 900px;
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
            font-weight: 700;
            color: #111827;
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

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .info {
            margin-bottom: 22px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px 18px;
            line-height: 1.75;
            font-size: 14px;
        }

        .error-box {
            margin-bottom: 18px;
            background: #ffe8e8;
            color: #9b1c1c;
            padding: 14px 16px;
            border-radius: 14px;
            font-weight: 700;
            border: 1px solid #fecaca;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 7px;
            color: #4b5563;
        }

        .field input,
        .field textarea,
        .field select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 12px 13px;
            font-size: 14px;
            background: white;
            color: #111827;
        }

        .field textarea {
            min-height: 90px;
            resize: vertical;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 8px;
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
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title">Edit Outlet</div>
            <a href="{{ route('backoffice.outlets.index') }}" class="btn btn-dark">Kembali</a>
        </div>

        @if($errors->any())
            <div class="error-box">
                <div>Form belum valid:</div>
                <ul style="margin:10px 0 0 18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="info">
                <strong>User:</strong> {{ $user->name }}<br>
                <strong>Role:</strong> {{ $user->role->name ?? '-' }}<br>
                <strong>Outlet:</strong> {{ $user->outlet->name ?? '-' }}
            </div>

            <form method="POST" action="{{ route('backoffice.outlets.update', $outlet) }}">
                @csrf
                @method('PUT')

                <div class="field">
                    <label>Outlet Name</label>
                    <input type="text" name="name" value="{{ old('name', $outlet->name) }}" required>
                </div>

                <div class="field">
                    <label>Outlet Code</label>
                    <input type="text" name="code" value="{{ old('code', $outlet->code) }}" required>
                </div>

                <div class="field">
                    <label>Address</label>
                    <textarea name="address">{{ old('address', $outlet->address) }}</textarea>
                </div>

                <div class="field">
                    <label>Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $outlet->phone) }}">
                </div>

                <div class="field">
                    <label>Status</label>
                    <select name="is_active" required>
                        <option value="1" @selected(old('is_active', (string) (int) $outlet->is_active) == '1')>Active</option>
                        <option value="0" @selected(old('is_active', (string) (int) $outlet->is_active) == '0')>Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">Update Outlet</button>
                    <a href="{{ route('backoffice.outlets.index') }}" class="btn btn-dark">Batal</a>
                </div>
            </form>

            <div class="note">
                CRUD Outlet 1B fokus ke edit outlet dasar. Delete outlet kita lanjut di tahap berikutnya.
            </div>
        </div>
    </div>
</body>
</html>