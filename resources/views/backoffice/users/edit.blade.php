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

        .outlet-dropdown {
            position: relative;
        }

        .outlet-dropdown-button {
            width: 100%;
            min-height: 46px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            background: #fff;
            padding: 12px;
            font-size: 14px;
            color: #111827;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            text-align: left;
        }

        .outlet-dropdown-button::after {
            content: "⌄";
            font-size: 16px;
            color: #6b7280;
            margin-left: 12px;
        }

        .outlet-dropdown-panel {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            z-index: 20;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 14px;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.16);
            padding: 10px;
            max-height: 240px;
            overflow-y: auto;
        }

        .outlet-dropdown.is-open .outlet-dropdown-panel {
            display: block;
        }

        .outlet-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 9px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            cursor: pointer;
        }

        .outlet-option:hover {
            background: #f3f4f6;
        }

        .outlet-option input {
            width: auto;
            margin: 0;
        }

        .field-help {
            display: block;
            margin-top: 6px;
            color: #6b7280;
            font-size: 12px;
            line-height: 1.5;
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

        .role-picker summary,
        .outlet-picker summary {
            width: 100%;
            min-height: 46px;
            box-sizing: border-box;
            border: 1px solid #d7dce5;
            border-radius: 12px;
            background: #ffffff;
            padding: 12px 14px;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            cursor: pointer;
            list-style: none;
        }

        .role-picker summary::-webkit-details-marker,
        .outlet-picker summary::-webkit-details-marker {
            display: none;
        }

        .role-picker summary::after,
        .outlet-picker summary::after {
            content: '⌄';
            float: right;
            color: #6b7280;
        }

        .role-picker[open] summary::after,
        .outlet-picker[open] summary::after {
            content: '⌃';
        }

        .role-options,
        .outlet-options {
            margin-top: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 18px 40px rgba(15,23,42,0.14);
            padding: 8px;
            display: grid;
            gap: 6px;
        }

        .role-option,
        .outlet-option {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 40px;
            padding: 8px 10px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            color: #111827;
            cursor: pointer;
        }

        .role-option:hover,
        .outlet-option:hover {
            background: #f3f4f6;
        }

        .role-option input,
        .outlet-option input {
            width: auto;
            margin: 0;
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
                    @php
                        $selectedRoleIds = collect(old('role_ids', $managedUser->roles->pluck('id')->push($managedUser->role_id)->filter()->unique()->values()->all()))
                            ->filter()
                            ->map(fn($id) => (string) $id)
                            ->unique()
                            ->values();
                    @endphp

                    <details class="outlet-picker role-picker">
                        <summary>
                            @if($selectedRoleIds->count())
                                {{ $selectedRoleIds->count() }} role dipilih
                            @else
                                Pilih role
                            @endif
                        </summary>

                        <div class="outlet-options role-options">
                            @foreach($roles as $role)
                                <label class="outlet-option role-option">
                                    <input
                                        type="checkbox"
                                        name="role_ids[]"
                                        value="{{ $role->id }}"
                                        @checked($selectedRoleIds->contains((string) $role->id))
                                    >
                                    <span>{{ $role->name }} ({{ $role->code }})</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                    <span class="field-help">Bisa pilih lebih dari 1 role. Role pertama akan dipakai sebagai role utama untuk kompatibilitas sistem.</span>
                </div>

                <div class="field">
                    <label>Outlet</label>
                    @php
                    $selectedOutletIds = collect(old('outlet_ids', $managedUser->outlets->pluck('id')->all()))
                        ->map(fn ($id) => (int) $id)
                        ->all();
                @endphp
                    <div class="outlet-dropdown" data-outlet-dropdown>
                        <button type="button" class="outlet-dropdown-button" data-outlet-dropdown-button>
                            Pilih Outlet
                        </button>

                        <div class="outlet-dropdown-panel">
                            @foreach($outlets as $outlet)
                                <label class="outlet-option">
                                    <input
                                        type="checkbox"
                                        name="outlet_ids[]"
                                        value="{{ $outlet->id }}"
                                        data-outlet-checkbox
                                        data-outlet-name="{{ $outlet->name }}"
                                        @checked(in_array((int) $outlet->id, $selectedOutletIds, true))
                                    >
                                    <span>{{ $outlet->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <small class="field-help">Bisa pilih lebih dari 1 outlet. Kosongkan hanya untuk user global.</small>
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-outlet-dropdown]').forEach(function (dropdown) {
                const button = dropdown.querySelector('[data-outlet-dropdown-button]');
                const checkboxes = dropdown.querySelectorAll('[data-outlet-checkbox]');

                function refreshLabel() {
                    const selected = Array.from(checkboxes)
                        .filter(function (checkbox) { return checkbox.checked; })
                        .map(function (checkbox) { return checkbox.getAttribute('data-outlet-name'); });

                    if (selected.length === 0) {
                        button.textContent = 'Pilih Outlet';
                    } else if (selected.length === 1) {
                        button.textContent = selected[0];
                    } else {
                        button.textContent = selected.length + ' outlet dipilih';
                    }
                }

                button.addEventListener('click', function () {
                    dropdown.classList.toggle('is-open');
                });

                checkboxes.forEach(function (checkbox) {
                    checkbox.addEventListener('change', refreshLabel);
                });

                document.addEventListener('click', function (event) {
                    if (!dropdown.contains(event.target)) {
                        dropdown.classList.remove('is-open');
                    }
                });

                refreshLabel();
            });
        });
    </script>

</body>
</html>