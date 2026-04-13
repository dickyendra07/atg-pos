<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ATG POS</title>
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

        .logout-form button {
            border: 0;
            background: #111827;
            color: white;
            padding: 10px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
        }

        .card {
            background: white;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.06);
            padding: 24px;
        }

        .box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px;
            margin-top: 14px;
        }

        .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 6px;
        }

        .value {
            font-size: 18px;
            font-weight: bold;
        }

        .ok {
            margin-top: 20px;
            background: #e8fff1;
            color: #17663a;
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topbar">
            <div class="title">Dashboard ATG POS</div>

            <form class="logout-form" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Logout</button>
            </form>
        </div>

        <div class="card">
            <p>Login berhasil. Data user aktif sekarang:</p>

            <div class="box">
                <div class="label">Nama</div>
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
                Login basic sudah jadi. Habis ini kita bisa lanjut pisahin cashier dan backoffice.
            </div>
        </div>
    </div>
</body>
</html>