<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Akses Outlet Cashier - ATG POS</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f5f7fb; font-family: system-ui, sans-serif; color: #111827; }
        .card { max-width: 460px; margin: 16px; padding: 28px; background: #fff; border: 1px solid #e8edf4; border-radius: 22px; box-shadow: 0 16px 34px rgba(15,23,42,.08); }
        h1 { margin: 0 0 10px; font-size: 22px; }
        p { margin: 0 0 18px; color: #4b5563; line-height: 1.7; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { border: 0; border-radius: 12px; padding: 11px 16px; font-weight: 800; text-decoration: none; cursor: pointer; background: #ea580c; color: #fff; font-size: 14px; }
        .btn-dark { background: #111827; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Akun ini belum memiliki akses outlet Cashier.</h1>
        <p>Halo {{ $user->name }}, akun kamu belum di-assign ke outlet aktif untuk Cashier. Hubungi admin Back Office agar outlet Cashier ditambahkan ke akun ini.</p>
        <div class="actions">
            @if($user->canAccessBackofficeDashboard())
                <a class="btn" href="{{ route('backoffice.index') }}">Ke Back Office</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-dark">Logout</button>
            </form>
        </div>
    </div>
</body>
</html>
