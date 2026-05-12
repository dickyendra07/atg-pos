<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ATG POS</title>
    <style>
        :root {
            --bg: #f3f5fa;
            --surface: rgba(255,255,255,0.92);
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --brand: #e86a3a;
            --brand-dark: #c9552a;
            --navy: #111827;
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.14);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(232,106,58,0.10), transparent 20%),
                linear-gradient(180deg, #f7f8fc 0%, #eef2f8 100%);
            color: var(--text);
        }

        .page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .shell {
            width: 100%;
            max-width: 1240px;
            min-height: 760px;
            background: rgba(255,255,255,0.55);
            border: 1px solid rgba(255,255,255,0.85);
            border-radius: 34px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1.18fr 0.82fr;
        }

        .visual-side {
            position: relative;
            min-height: 760px;
            background:
                linear-gradient(180deg, rgba(17,24,39,0.18), rgba(17,24,39,0.30)),
                url('{{ asset('images/login-cover.jpg') }}') center center / cover no-repeat;
        }

        .visual-side::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(17,24,39,0.08), rgba(232,106,58,0.12));
            pointer-events: none;
        }

        .brand-badge {
            position: absolute;
            top: 28px;
            left: 28px;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.20);
            backdrop-filter: blur(10px);
            color: white;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .brand-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: #ffb089;
            display: inline-block;
            box-shadow: 0 0 12px rgba(255,176,137,0.7);
        }

        .visual-copy {
            position: absolute;
            left: 32px;
            bottom: 32px;
            z-index: 2;
            max-width: 520px;
            color: white;
        }

        .visual-title {
            margin: 0 0 10px;
            font-size: 44px;
            line-height: 1.02;
            font-weight: 800;
            letter-spacing: -0.03em;
        }

        .visual-subtitle {
            margin: 0;
            font-size: 15px;
            line-height: 1.8;
            color: rgba(255,255,255,0.88);
            max-width: 420px;
        }

        .form-side {
            background: rgba(255,255,255,0.94);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 42px 34px;
        }

        .form-card {
            width: 100%;
            max-width: 390px;
        }

        .top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 28px;
        }

        .logo-mini {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .logo-box {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: #fff4ed;
            border: 1px solid #f4d6c8;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-box img {
            width: 28px;
            height: 28px;
            object-fit: contain;
        }

        .logo-text {
            font-size: 13px;
            font-weight: 800;
            color: #111827;
            letter-spacing: 0.04em;
        }

        .login-chip {
            font-size: 12px;
            font-weight: 700;
            color: var(--brand-dark);
            background: #fff4ed;
            border: 1px solid #f4d6c8;
            padding: 8px 12px;
            border-radius: 999px;
        }

        .form-title {
            font-size: 36px;
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin: 0 0 8px;
            color: #111827;
        }

        .form-subtitle {
            margin: 0 0 24px;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .alert {
            padding: 14px 15px;
            border-radius: 14px;
            margin-bottom: 18px;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.6;
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

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #374151;
        }

        .form-input {
            width: 100%;
            min-height: 54px;
            border: 1px solid #d7dce5;
            border-radius: 16px;
            background: white;
            padding: 0 16px;
            font-size: 15px;
            color: #111827;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-input:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .error-text {
            margin-top: 6px;
            font-size: 12px;
            color: #b42318;
            font-weight: 700;
        }

        .form-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin: 8px 0 22px;
            flex-wrap: wrap;
        }

        .remember-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #4b5563;
            font-weight: 700;
        }

        .remember-wrap input {
            width: 16px;
            height: 16px;
            accent-color: var(--brand);
        }

        .btn-login {
            width: 100%;
            min-height: 56px;
            border: 0;
            border-radius: 16px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 800;
            color: white;
            background: linear-gradient(135deg, var(--navy) 0%, #1f2937 100%);
            box-shadow: 0 14px 28px rgba(17,24,39,0.16);
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            opacity: 0.97;
        }

        .footer-note {
            margin-top: 18px;
            font-size: 12px;
            color: #9ca3af;
            line-height: 1.7;
        }

        @media (max-width: 1024px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .visual-side {
                min-height: 360px;
            }

            .visual-title {
                font-size: 34px;
            }

            .form-side {
                padding: 30px 22px;
            }
        }

        @media (max-width: 640px) {
            .page {
                padding: 14px;
            }

            .shell {
                border-radius: 24px;
            }

            .visual-copy {
                left: 22px;
                right: 22px;
                bottom: 22px;
            }

            .brand-badge {
                top: 20px;
                left: 20px;
            }

            .form-title {
                font-size: 30px;
            }

            .visual-title {
                font-size: 28px;
            }
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap .form-input {
            padding-right: 72px;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: #fff4ed;
            color: var(--brand-dark);
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: inset 0 0 0 1px #f4d6c8;
        }

        .password-toggle:hover {
            background: #ffe9db;
            color: #111827;
        }

    
        /* Portal-specific login design */
        body.login-portal-backoffice {
            background:
                radial-gradient(circle at top left, rgba(232, 106, 58, 0.18), transparent 34%),
                linear-gradient(135deg, #fff7ed 0%, #f8fafc 48%, #eef2ff 100%) !important;
        }

        body.login-portal-cashier {
            background:
                radial-gradient(circle at top left, rgba(22, 101, 52, 0.20), transparent 34%),
                linear-gradient(135deg, #ecfdf5 0%, #f8fafc 50%, #fff7ed 100%) !important;
        }

        .login-portal-backoffice .visual-side {
            background:
                linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(31, 41, 55, 0.93)),
                radial-gradient(circle at top right, rgba(232, 106, 58, 0.40), transparent 42%) !important;
        }

        .login-portal-cashier .visual-side {
            background:
                linear-gradient(135deg, rgba(20, 83, 45, 0.96), rgba(22, 101, 52, 0.92)),
                radial-gradient(circle at top right, rgba(249, 115, 22, 0.30), transparent 42%) !important;
        }

        .login-portal-cashier .brand-badge {
            background: rgba(255,255,255,0.16) !important;
            border-color: rgba(187, 247, 208, 0.32) !important;
        }

        .login-portal-cashier .brand-dot {
            background: #22c55e !important;
        }

        .login-portal-backoffice .login-chip {
            background: #fff3eb !important;
            color: #c9552a !important;
            border-color: #fed7aa !important;
        }

        .login-portal-cashier .login-chip {
            background: #e8fff1 !important;
            color: #166534 !important;
            border-color: #bbf7d0 !important;
        }

        .login-portal-backoffice .btn-login {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%) !important;
        }

        .login-portal-cashier .btn-login {
            background: linear-gradient(135deg, #15803d 0%, #166534 100%) !important;
        }

        .login-portal-cashier .password-toggle {
            background: #e8fff1 !important;
            color: #166534 !important;
            box-shadow: inset 0 0 0 1px #bbf7d0 !important;
        }

        .portal-switch {
            margin-top: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            color: #6b7280;
            font-size: 12px;
            font-weight: 800;
        }

        .portal-switch a {
            color: #111827;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            padding: 7px 10px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 900;
        }

        .login-portal-cashier .portal-switch a {
            color: #166534;
            background: #e8fff1;
            border-color: #bbf7d0;
        }

        .login-portal-backoffice .portal-switch a {
            color: #c9552a;
            background: #fff3eb;
            border-color: #fed7aa;
        }

        .top-row {
            align-items: flex-start !important;
        }

    
        /* Keep orange brand, flip layout only for cashier */
        body.login-portal-cashier {
            background:
                radial-gradient(circle at top left, rgba(232, 106, 58, 0.18), transparent 34%),
                linear-gradient(135deg, #fff7ed 0%, #f8fafc 48%, #eef2ff 100%) !important;
        }

        .login-portal-cashier .shell {
            grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr) !important;
        }

        .login-portal-cashier .form-side {
            order: 1 !important;
        }

        .login-portal-cashier .visual-side {
            order: 2 !important;
            background:
                linear-gradient(135deg, rgba(17, 24, 39, 0.96), rgba(31, 41, 55, 0.93)),
                radial-gradient(circle at top right, rgba(232, 106, 58, 0.40), transparent 42%) !important;
        }

        .login-portal-cashier .brand-badge {
            background: rgba(255,255,255,0.14) !important;
            border-color: rgba(255,255,255,0.22) !important;
        }

        .login-portal-cashier .brand-dot {
            background: #e86a3a !important;
        }

        .login-portal-cashier .login-chip,
        .login-portal-backoffice .login-chip {
            background: #fff3eb !important;
            color: #c9552a !important;
            border-color: #fed7aa !important;
        }

        .login-portal-cashier .btn-login,
        .login-portal-backoffice .btn-login {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%) !important;
        }

        .login-portal-cashier .password-toggle {
            background: #fff4ed !important;
            color: #c9552a !important;
            box-shadow: inset 0 0 0 1px #f4d6c8 !important;
        }

        .login-portal-cashier .portal-switch a,
        .login-portal-backoffice .portal-switch a {
            color: #c9552a !important;
            background: #fff3eb !important;
            border-color: #fed7aa !important;
        }

        @media (max-width: 900px) {
            .login-portal-cashier .form-side,
            .login-portal-cashier .visual-side {
                order: unset !important;
            }
        }

    
        /* Login cover image */
        .visual-side {
            position: relative !important;
            overflow: hidden !important;
            background-image:
                linear-gradient(135deg, rgba(17, 24, 39, 0.72), rgba(17, 24, 39, 0.34)),
                url('{{ asset('images/login-cover.jpg') }}') !important;
            background-size: cover !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
        }

        .visual-side > * {
            position: relative !important;
            z-index: 2 !important;
        }

        .login-portal-backoffice .visual-side,
        .login-portal-cashier .visual-side {
            background-image:
                linear-gradient(135deg, rgba(17, 24, 39, 0.72), rgba(17, 24, 39, 0.34)),
                url('{{ asset('images/login-cover.jpg') }}') !important;
            background-size: cover !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
        }

        .visual-title,
        .visual-subtitle,
        .brand-badge {
            text-shadow: 0 2px 14px rgba(0,0,0,0.35);
        }

        .brand-badge {
            background: rgba(17, 24, 39, 0.42) !important;
            border-color: rgba(255,255,255,0.22) !important;
            backdrop-filter: blur(8px);
        }

    
        /* Professional login polish */
        .page {
            padding: 28px !important;
        }

        .shell {
            max-width: 1180px !important;
            min-height: 680px !important;
            border-radius: 34px !important;
            overflow: hidden !important;
            box-shadow: 0 30px 90px rgba(15, 23, 42, 0.14) !important;
            border: 1px solid rgba(255,255,255,0.9) !important;
            background: rgba(255,255,255,0.86) !important;
        }

        .form-side {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 56px !important;
            background:
                radial-gradient(circle at top left, rgba(232,106,58,0.08), transparent 32%),
                #ffffff !important;
        }

        .form-card {
            width: min(430px, 100%) !important;
            padding: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        .top-row {
            display: grid !important;
            grid-template-columns: 1fr auto !important;
            gap: 14px !important;
            align-items: center !important;
            margin-bottom: 34px !important;
        }

        .logo-mini {
            gap: 10px !important;
        }

        .logo-box {
            width: 42px !important;
            height: 42px !important;
            border-radius: 14px !important;
        }

        .logo-text {
            font-size: 14px !important;
            line-height: 1.05 !important;
            letter-spacing: -0.02em !important;
        }

        .login-chip {
            font-size: 11px !important;
            padding: 8px 11px !important;
            white-space: nowrap !important;
        }

        .top-row > div[style*="margin-top:10px"] {
            grid-column: 1 / -1 !important;
            margin-top: 0 !important;
            max-width: 360px !important;
            font-size: 13px !important;
            line-height: 1.55 !important;
            color: #6b7280 !important;
        }

        .portal-switch {
            grid-column: 1 / -1 !important;
            margin-top: -2px !important;
            padding-top: 0 !important;
            font-size: 12px !important;
        }

        .portal-switch a {
            padding: 6px 10px !important;
            font-size: 11px !important;
        }

        .form-title {
            font-size: 34px !important;
            line-height: 1.05 !important;
            letter-spacing: -0.045em !important;
            margin-bottom: 10px !important;
        }

        .form-subtitle {
            font-size: 14px !important;
            line-height: 1.6 !important;
            margin-bottom: 28px !important;
            color: #6b7280 !important;
        }

        .form-group {
            margin-bottom: 17px !important;
        }

        .form-label {
            font-size: 12px !important;
            margin-bottom: 8px !important;
            color: #374151 !important;
        }

        .form-input {
            min-height: 54px !important;
            border-radius: 16px !important;
            padding: 0 16px !important;
            font-size: 14px !important;
            border-color: #dfe4ec !important;
            background: #ffffff !important;
            box-shadow: 0 8px 20px rgba(15,23,42,0.03) !important;
        }

        .form-input:focus {
            border-color: #e86a3a !important;
            box-shadow: 0 0 0 4px rgba(232,106,58,0.12) !important;
        }

        .remember-row {
            margin: 2px 0 24px !important;
        }

        .btn-login {
            min-height: 56px !important;
            border-radius: 17px !important;
            font-size: 14px !important;
            box-shadow: 0 16px 28px rgba(232,106,58,0.24) !important;
        }

        .form-footer {
            margin-top: 16px !important;
            font-size: 12px !important;
            color: #9ca3af !important;
        }

        .visual-side {
            padding: 42px !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: space-between !important;
            background-image:
                linear-gradient(180deg, rgba(17,24,39,0.18) 0%, rgba(17,24,39,0.22) 42%, rgba(17,24,39,0.78) 100%),
                url('{{ asset('images/login-cover.jpg') }}') !important;
            background-size: cover !important;
            background-position: center !important;
        }

        .brand-badge {
            width: fit-content !important;
            background: rgba(17,24,39,0.38) !important;
            border: 1px solid rgba(255,255,255,0.22) !important;
            backdrop-filter: blur(10px) !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.16) !important;
        }

        .visual-copy {
            max-width: 520px !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .visual-title {
            font-size: 42px !important;
            line-height: 1.02 !important;
            letter-spacing: -0.055em !important;
            margin-bottom: 12px !important;
            text-shadow: 0 4px 22px rgba(0,0,0,0.34) !important;
        }

        .visual-subtitle {
            max-width: 450px !important;
            font-size: 15px !important;
            line-height: 1.8 !important;
            color: rgba(255,255,255,0.92) !important;
            text-shadow: 0 3px 18px rgba(0,0,0,0.32) !important;
        }

        .login-portal-cashier .shell {
            grid-template-columns: minmax(0, 0.92fr) minmax(0, 1.08fr) !important;
        }

        .login-portal-backoffice .shell {
            grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr) !important;
        }

        .login-portal-cashier .form-side {
            order: 1 !important;
        }

        .login-portal-cashier .visual-side {
            order: 2 !important;
        }

        .login-portal-backoffice .visual-side {
            order: 1 !important;
        }

        .login-portal-backoffice .form-side {
            order: 2 !important;
        }

        @media (max-width: 900px) {
            .shell {
                min-height: auto !important;
                grid-template-columns: 1fr !important;
            }

            .form-side {
                padding: 34px 24px !important;
            }

            .visual-side {
                min-height: 360px !important;
                order: unset !important;
            }

            .login-portal-cashier .form-side,
            .login-portal-cashier .visual-side,
            .login-portal-backoffice .form-side,
            .login-portal-backoffice .visual-side {
                order: unset !important;
            }
        }

    </style>
    @if(($portal ?? 'backoffice') === 'cashier')
        <link rel="manifest" href="{{ asset('manifest-cashier.json') }}">
        <meta name="theme-color" content="#e86a3a">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="ATG Cashier">
        <link rel="apple-touch-icon" href="{{ asset('images/atg-icon.png') }}">
    @endif

</head>
<body class="login-portal-{{ $portal ?? 'backoffice' }}">
    <div class="page">
        <div class="shell">
            <div class="visual-side">
                <div class="brand-badge">
                    <span class="brand-dot"></span>
                    ATG POS
                </div>

                <div class="visual-copy">
                    <h1 class="visual-title">{{ $portalTitle ?? 'Modern login for daily operations.' }}</h1>
                    <p class="visual-subtitle">
                        @if(($portal ?? 'backoffice') === 'cashier')
                            Khusus operasional kasir outlet: pilih outlet, buka shift, transaksi, receipt, dan closing.
                        @else
                            Khusus Back Office: dashboard, inventory, recipes, promos, users, reports, dan approval.
                        @endif
                    </p>
                </div>
            </div>

            <div class="form-side">
                <div class="form-card">
                    <div class="top-row">
                        <div class="logo-mini">
                            <div class="logo-box">
                                <img src="{{ asset('images/atg-icon.png') }}" alt="ATG Logo">
                            </div>
                            <div class="logo-text">ATG POS</div>
                        </div>

                        <div class="login-chip">{{ $portalTitle ?? 'Secure Login' }}</div>
                        @isset($portalSubtitle)
                            <div style="margin-top:10px; color:#6b7280; font-size:14px; line-height:1.6; font-weight:700;">{{ $portalSubtitle }}</div>
                        @endisset

                        <div class="portal-switch">
                            @if(($portal ?? 'backoffice') === 'cashier')
                                <span>Butuh akses admin?</span>
                                <a href="{{ route('backoffice.login') }}">Login Back Office</a>
                            @else
                                <span>Masuk sebagai kasir?</span>
                                <a href="{{ route('cashier.login') }}">Login Cashier</a>
                            @endif
                        </div>
                    </div>

                    <h2 class="form-title">
                        @if(($portal ?? 'backoffice') === 'cashier')
                            Cashier sign in
                        @else
                            Back Office sign in
                        @endif
                    </h2>
                    <p class="form-subtitle">
                        @if(($portal ?? 'backoffice') === 'cashier')
                            Gunakan akun kasir yang sudah diberikan akses outlet.
                        @else
                            Gunakan akun back office sesuai role dan akses outlet.
                        @endif
                    </p>

                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-error">
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ $loginRoute ?? route('login.store') }}">
                        @csrf

                        <div class="form-group">
                            <label class="form-label" for="login">Username / Email</label>
                            <input
                                id="login"
                                type="text"
                                name="login"
                                class="form-input"
                                placeholder="Enter username or email"
                                value="{{ old('login') }}"
                                required
                                autofocus
                            >
                            @error('login')
                                <div class="error-text">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <div class="password-wrap">
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    class="form-input"
                                    placeholder="Enter your password"
                                    required
                                >
                                <button type="button" class="password-toggle" id="password-toggle" aria-label="Show password">Show</button>
                            </div>
                            @error('password')
                                <div class="error-text">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-row">
                            <label class="remember-wrap">
                                <input type="checkbox" name="remember">
                                Remember me
                            </label>
                        </div>

                        <button type="submit" class="btn-login">Sign In</button>
                    </form>

                    <div class="footer-note">
                        ATG POS operational access.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('password-toggle');

            if (!passwordInput || !passwordToggle) return;

            passwordToggle.addEventListener('click', function () {
                const isHidden = passwordInput.type === 'password';

                passwordInput.type = isHidden ? 'text' : 'password';
                passwordToggle.textContent = isHidden ? 'Hide' : 'Show';
                passwordToggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            });
        });
    </script>

    @if(($portal ?? 'backoffice') === 'cashier')
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('/sw-cashier.js').catch(function () {});
                });
            }
        </script>
    @endif

</body>
</html>