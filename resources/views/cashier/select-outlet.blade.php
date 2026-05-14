<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pilih Outlet - ATG POS Cashier</title>
    <style>
        :root {
            --brand: #e86a3a;
            --brand-dark: #c9552a;
            --brand-soft: #fff3eb;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --surface: #ffffff;
            --green: #166534;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(232, 106, 58, 0.16), transparent 34%),
                linear-gradient(135deg, #fff7ed 0%, #f8fafc 48%, #eef2ff 100%);
            color: var(--text);
            display: grid;
            place-items: center;
            padding: 28px;
        }

        .shell {
            width: min(940px, 100%);
            display: grid;
            grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
            background: rgba(255,255,255,0.86);
            border: 1px solid rgba(255,255,255,0.92);
            border-radius: 34px;
            box-shadow: 0 30px 90px rgba(15, 23, 42, 0.14);
            overflow: hidden;
        }

        .visual {
            min-height: 560px;
            padding: 36px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background-image:
                linear-gradient(180deg, rgba(17,24,39,0.14) 0%, rgba(17,24,39,0.24) 42%, rgba(17,24,39,0.78) 100%),
                url('{{ asset('images/login-cover.jpg') }}');
            background-size: cover;
            background-position: center;
            color: #fff;
        }

        .brand-badge {
            width: fit-content;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 999px;
            background: rgba(17,24,39,0.38);
            border: 1px solid rgba(255,255,255,0.22);
            backdrop-filter: blur(10px);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .brand-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: var(--brand);
            box-shadow: 0 0 0 4px rgba(232,106,58,.18);
        }

        .visual-title {
            margin: 0 0 12px;
            font-size: 40px;
            line-height: 1.02;
            letter-spacing: -0.055em;
            text-shadow: 0 4px 22px rgba(0,0,0,0.34);
        }

        .visual-subtitle {
            margin: 0;
            max-width: 430px;
            font-size: 15px;
            line-height: 1.8;
            color: rgba(255,255,255,0.92);
            font-weight: 700;
            text-shadow: 0 3px 18px rgba(0,0,0,0.32);
        }

        .content {
            padding: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background:
                radial-gradient(circle at top left, rgba(232,106,58,0.08), transparent 32%),
                #ffffff;
        }

        .card {
            width: 100%;
            max-width: 460px;
        }

        .kicker {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--brand-soft);
            color: var(--brand-dark);
            border: 1px solid #fed7aa;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .07em;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        h1 {
            margin: 0 0 9px;
            font-size: 36px;
            line-height: 1.04;
            letter-spacing: -0.045em;
        }

        .subtitle {
            margin: 0 0 24px;
            color: var(--muted);
            line-height: 1.65;
            font-size: 14px;
            font-weight: 700;
        }

        .error {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            background: #fff1f1;
            border: 1px solid #fecaca;
            color: #991b1b;
            font-weight: 800;
            line-height: 1.6;
        }

        .outlet-grid {
            display: grid;
            gap: 10px;
            margin-bottom: 18px;
        }

        .outlet-card {
            position: relative;
            display: block;
            cursor: pointer;
        }

        .outlet-card input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .outlet-card-body {
            min-height: 76px;
            border-radius: 18px;
            border: 1px solid #e3e8ef;
            background: #ffffff;
            padding: 15px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            box-shadow: 0 10px 22px rgba(15,23,42,0.04);
            transition: 0.16s ease;
        }

        .outlet-card:hover .outlet-card-body {
            border-color: #f3b08f;
            transform: translateY(-1px);
            box-shadow: 0 16px 30px rgba(232,106,58,0.12);
        }

        .outlet-card input:checked + .outlet-card-body {
            border-color: var(--brand);
            background: linear-gradient(135deg, #fff7ed 0%, #ffffff 100%);
            box-shadow: 0 18px 34px rgba(232,106,58,0.18);
        }

        .outlet-name {
            font-size: 16px;
            font-weight: 900;
            letter-spacing: -0.02em;
            color: var(--text);
        }

        .outlet-desc {
            margin-top: 5px;
            font-size: 12px;
            font-weight: 800;
            color: var(--muted);
        }

        .check {
            width: 30px;
            height: 30px;
            border-radius: 999px;
            border: 1px solid #d7dce5;
            display: grid;
            place-items: center;
            color: transparent;
            background: #f8fafc;
            flex: 0 0 auto;
            font-weight: 900;
            transition: 0.16s ease;
        }

        .outlet-card input:checked + .outlet-card-body .check {
            background: var(--brand);
            border-color: var(--brand);
            color: #fff;
        }

        .actions {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: center;
            margin-top: 18px;
        }

        .btn {
            min-height: 52px;
            border: 0;
            border-radius: 17px;
            padding: 0 18px;
            cursor: pointer;
            color: white;
            font-size: 14px;
            font-weight: 900;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-brand {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
            box-shadow: 0 16px 28px rgba(232,106,58,0.24);
        }

        .btn-dark {
            background: #111827;
            min-width: 130px;
        }

        .user-pill {
            margin-top: 20px;
            padding: 11px 13px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
            font-weight: 800;
            line-height: 1.5;
        }

        .user-pill strong {
            color: #111827;
        }

        @media (max-width: 900px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .visual {
                min-height: 320px;
                order: 2;
            }

            .content {
                padding: 34px 24px;
                order: 1;
            }

            .actions {
                grid-template-columns: 1fr;
            }

            .btn-dark {
                width: 100%;
            }
        }
    
        /* Outlet selector final polish */
        .shell {
            width: min(1040px, 100%) !important;
            grid-template-columns: minmax(0, 0.92fr) minmax(0, 1.08fr) !important;
            border-radius: 36px !important;
            box-shadow: 0 34px 100px rgba(15, 23, 42, 0.16) !important;
        }

        .content {
            padding: 58px 54px !important;
        }

        .card {
            max-width: 480px !important;
        }

        .kicker {
            margin-bottom: 18px !important;
            padding: 8px 13px !important;
            font-size: 10.5px !important;
            letter-spacing: .08em !important;
        }

        h1 {
            font-size: 38px !important;
            margin-bottom: 10px !important;
        }

        .subtitle {
            max-width: 420px !important;
            margin-bottom: 28px !important;
            font-size: 14px !important;
            line-height: 1.7 !important;
        }

        .outlet-grid {
            gap: 12px !important;
            margin-bottom: 20px !important;
        }

        .outlet-card-body {
            min-height: 82px !important;
            border-radius: 20px !important;
            padding: 17px 18px !important;
            border-color: #e6ebf2 !important;
            background:
                linear-gradient(135deg, rgba(255,255,255,1) 0%, rgba(248,250,252,.92) 100%) !important;
            box-shadow: 0 12px 26px rgba(15,23,42,0.045) !important;
        }

        .outlet-card:hover .outlet-card-body {
            transform: translateY(-2px) !important;
        }

        .outlet-card input:checked + .outlet-card-body {
            background:
                linear-gradient(135deg, #fff3eb 0%, #ffffff 78%) !important;
            border-color: #e86a3a !important;
            box-shadow: 0 18px 36px rgba(232,106,58,0.18) !important;
        }

        .outlet-name {
            font-size: 16.5px !important;
            margin-bottom: 2px !important;
        }

        .outlet-desc {
            font-size: 12px !important;
            line-height: 1.45 !important;
            color: #6b7280 !important;
        }

        .check {
            width: 32px !important;
            height: 32px !important;
            border-width: 1.5px !important;
            font-size: 14px !important;
        }

        .actions {
            margin-top: 22px !important;
            display: block !important;
        }

        .btn-brand {
            width: 100% !important;
            min-height: 56px !important;
            border-radius: 18px !important;
            font-size: 14px !important;
        }

        form[action*="logout"] {
            margin-top: 12px !important;
        }

        .btn-dark {
            width: 100% !important;
            min-height: 48px !important;
            border-radius: 16px !important;
            background: #f8fafc !important;
            color: #111827 !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: none !important;
        }

        .btn-dark:hover {
            background: #f3f4f6 !important;
        }

        .user-pill {
            margin-top: 16px !important;
            padding: 12px 14px !important;
            border-radius: 16px !important;
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%) !important;
            font-size: 12px !important;
        }

        .visual {
            min-height: 620px !important;
            padding: 42px !important;
            background-position: center center !important;
        }

        .visual-title {
            font-size: 44px !important;
            margin-bottom: 14px !important;
        }

        .visual-subtitle {
            max-width: 460px !important;
            font-size: 15px !important;
            line-height: 1.85 !important;
        }

        .brand-badge {
            padding: 9px 13px !important;
            box-shadow: 0 14px 34px rgba(0,0,0,0.18) !important;
        }

        @media (max-width: 900px) {
            .content {
                padding: 36px 24px !important;
            }

            .visual {
                min-height: 360px !important;
            }

            h1 {
                font-size: 32px !important;
            }
        }

    </style>
    <link rel="manifest" href="{{ asset('manifest-cashier.json') }}">
    <meta name="theme-color" content="#e86a3a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="ATG Cashier">
    <link rel="apple-touch-icon" href="{{ asset('images/atg-icon.png') }}">

</head>
<body>
    <div class="shell">
        <div class="content">
            <div class="card">
                <div class="kicker">Cashier Outlet</div>
                <h1>Pilih Outlet Kerja</h1>
                <p class="subtitle">
                    Pilih outlet aktif untuk transaksi kasir. Semua shift, receipt, void, dan closing akan tercatat di outlet ini.
                </p>

                @if($errors->any())
                    <div class="error">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('cashier.select-outlet.store') }}">
                    @csrf

                    <div class="outlet-grid">
                        @foreach($outlets as $outlet)
                            <label class="outlet-card">
                                <input
                                    type="radio"
                                    name="outlet_id"
                                    value="{{ $outlet->id }}"
                                    @checked((string) old('outlet_id', $selectedOutletId) === (string) $outlet->id)
                                    required
                                >
                                <div class="outlet-card-body">
                                    <div>
                                        <div class="outlet-name">{{ $outlet->name }}</div>
                                        <div class="outlet-desc">Gunakan outlet ini untuk sesi cashier sekarang</div>
                                    </div>
                                    <div class="check">✓</div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn btn-brand">Masuk Cashier</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('logout') }}" style="margin-top:10px;">
                    @csrf
                    <button type="submit" class="btn btn-dark">Log Out</button>
                </form>

                <div class="user-pill">
                    Login sebagai: <strong>{{ $user->name }}</strong> • {{ $user->role->name ?? 'Kasir' }}
                </div>
            </div>
        </div>

        <div class="visual">
            <div class="brand-badge">
                <span class="brand-dot"></span>
                ATG POS
            </div>

            <div>
                <h2 class="visual-title">Outlet Session</h2>
                <p class="visual-subtitle">
                    Pastikan outlet yang dipilih sudah sesuai sebelum mulai transaksi, supaya laporan dan stok tetap akurat.
                </p>
            </div>
        </div>
    </div>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw-cashier.js').catch(function () {});
            });
        }
    </script>

</body>
</html>
