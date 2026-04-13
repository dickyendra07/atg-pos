<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATG POS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f6f7fb;
            padding: 40px;
            color: #222;
        }

        .card {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        h1 {
            margin-top: 0;
        }

        .box {
            background: #f2f4f8;
            border-radius: 12px;
            padding: 16px;
            margin-top: 16px;
        }

        .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .value {
            font-size: 18px;
            font-weight: bold;
        }

        .ok {
            margin-top: 24px;
            padding: 14px 16px;
            background: #e8fff1;
            color: #17663a;
            border-radius: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>ATG POS</h1>
        <p>Test awal berhasil. Sekarang kita cek user, role, dan outlet.</p>

        @if($user)
            <div class="box">
                <div class="label">Nama User</div>
                <div class="value">{{ $user->name }}</div>
            </div>

            <div class="box">
                <div class="label">Email</div>
                <div class="value">{{ $user->email }}</div>
            </div>

            <div class="box">
                <div class="label">Role</div>
                <div class="value">{{ $user->role->name ?? '-' }}</div>
            </div>

            <div class="box">
                <div class="label">Outlet</div>
                <div class="value">{{ $user->outlet->name ?? '-' }}</div>
            </div>

            <div class="ok">
                Data user, role, dan outlet sudah kebaca dengan benar.
            </div>
        @else
            <div class="box">
                Belum ada user.
            </div>
        @endif
    </div>
</body>
</html>