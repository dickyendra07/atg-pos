<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Back Office ATG POS</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6fb;
            color: #222;
        }

        .wrap {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
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
        }

        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
        }

        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .field input,
        .field select {
            width: 100%;
            box-sizing: border-box;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px;
            font-size: 14px;
        }

        .error-box {
            margin-bottom: 18px;
            background: #ffe8e8;
            color: #9b1c1c;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }

        .note {
            margin-bottom: 18px;
            background: #eef2ff;
            color: #3730a3;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title">Edit User</div>
            <a href="{{ route('backoffice.users.index') }}" class="btn">Kembali</a>
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
            <div class="note">
                Kosongkan password jika tidak ingin mengganti password user ini.
            </div>

            <form method="POST" action="{{ route('backoffice.users.update', $managedUser->id) }}">
                @csrf
                @method('PUT')

                <div class="field">
                    <label>Nama</label>
                    <input type="text" name="name" value="{{ old('name', $managedUser->name) }}" required>
                </div>

                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email', $managedUser->email) }}" required>
                </div>

                <div class="field">
                    <label>Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $managedUser->phone) }}">
                </div>

                <div class="field">
                    <label>Password Baru</label>
                    <input type="password" name="password">
                </div>

                <div class="field">
                    <label>Role</label>
                    <select name="role_id" required>
                        <option value="">Pilih role</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected(old('role_id', $managedUser->role_id) == $role->id)>
                                {{ $role->name }} ({{ $role->code }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label>Outlet</label>
                    <select name="outlet_id">
                        <option value="">Tanpa outlet / global</option>
                        @foreach($outlets as $outlet)
                            <option value="{{ $outlet->id }}" @selected(old('outlet_id', $managedUser->outlet_id) == $outlet->id)>
                                {{ $outlet->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label>Status</label>
                    <select name="is_active" required>
                        <option value="1" @selected(old('is_active', (string) $managedUser->is_active) == '1')>Active</option>
                        <option value="0" @selected(old('is_active', (string) $managedUser->is_active) == '0')>Inactive</option>
                    </select>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-success">Update User</button>
                    <a href="{{ route('backoffice.users.index') }}" class="btn">Batal</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>