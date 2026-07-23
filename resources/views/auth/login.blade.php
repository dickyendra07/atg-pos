<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ATG POS</title>
    <link rel="icon" href="{{ asset('images/atg-icon.png') }}">
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


        /* LOGIN_TABLET_MOBILE_FIX_V2 */
        @media (max-width: 1100px) and (min-width: 701px) {
            html,
            body {
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }

            .page {
                min-height: 100vh !important;
                padding: 18px !important;
                align-items: center !important;
            }

            .shell {
                width: min(100%, 920px) !important;
                max-width: 920px !important;
                min-height: auto !important;
                display: grid !important;
                grid-template-columns: minmax(260px, 0.9fr) minmax(330px, 1.1fr) !important;
                border-radius: 32px !important;
                overflow: hidden !important;
            }

            .login-portal-cashier .shell {
                grid-template-columns: minmax(330px, 1.1fr) minmax(260px, 0.9fr) !important;
            }

            .visual-side {
                min-height: 640px !important;
                max-height: none !important;
                padding: 0 !important;
            }

            .visual-copy {
                left: 26px !important;
                right: 26px !important;
                bottom: 28px !important;
                max-width: 300px !important;
            }

            .visual-title {
                font-size: 42px !important;
                line-height: 0.98 !important;
                margin-bottom: 14px !important;
            }

            .visual-subtitle {
                font-size: 16px !important;
                line-height: 1.7 !important;
                max-width: 280px !important;
            }

            .brand-badge {
                top: 24px !important;
                left: 24px !important;
                font-size: 12px !important;
                padding: 10px 14px !important;
            }

            .form-side {
                min-width: 0 !important;
                padding: 42px 34px !important;
                align-items: center !important;
            }

            .form-card {
                width: 100% !important;
                max-width: 390px !important;
                min-width: 0 !important;
            }

            .top-row {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 14px !important;
                margin-bottom: 26px !important;
            }

            .logo-mini {
                min-width: 0 !important;
            }

            .logo-text {
                font-size: 16px !important;
                line-height: 1.1 !important;
            }

            .portal-switch {
                width: 100% !important;
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 8px !important;
                margin-top: 0 !important;
                font-size: 13px !important;
            }

            .portal-switch a {
                width: fit-content !important;
                max-width: 100% !important;
                min-height: 40px !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                white-space: normal !important;
                text-align: center !important;
                padding: 9px 14px !important;
            }

            .form-title {
                font-size: 46px !important;
                line-height: 1.02 !important;
                letter-spacing: -0.05em !important;
                margin-bottom: 14px !important;
            }

            .form-subtitle {
                font-size: 17px !important;
                line-height: 1.75 !important;
                margin-bottom: 28px !important;
            }

            .form-label {
                font-size: 15px !important;
            }

            .form-input {
                min-height: 58px !important;
                font-size: 17px !important;
                border-radius: 20px !important;
            }

            .password-toggle {
                right: 12px !important;
                min-height: 40px !important;
                padding: 8px 13px !important;
            }

            .btn-login {
                min-height: 62px !important;
                border-radius: 22px !important;
                font-size: 18px !important;
            }

            .footer-note {
                font-size: 14px !important;
                line-height: 1.7 !important;
            }
        }

        @media (max-width: 700px) {
            html,
            body {
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }

            .page {
                padding: 12px !important;
                align-items: flex-start !important;
            }

            .shell,
            .login-portal-cashier .shell,
            .login-portal-backoffice .shell {
                width: 100% !important;
                max-width: 100% !important;
                min-height: auto !important;
                display: grid !important;
                grid-template-columns: 1fr !important;
                border-radius: 28px !important;
                overflow: hidden !important;
            }

            .visual-side,
            .login-portal-cashier .visual-side,
            .login-portal-backoffice .visual-side {
                order: 1 !important;
                min-height: 230px !important;
                max-height: 260px !important;
            }

            .form-side,
            .login-portal-cashier .form-side,
            .login-portal-backoffice .form-side {
                order: 2 !important;
                padding: 26px 22px 28px !important;
            }

            .form-card {
                width: 100% !important;
                max-width: 100% !important;
            }

            .top-row {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 14px !important;
                margin-bottom: 22px !important;
            }

            .portal-switch {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 8px !important;
                margin-top: 0 !important;
            }

            .portal-switch a {
                width: fit-content !important;
                max-width: 100% !important;
                white-space: normal !important;
            }

            .visual-copy {
                left: 22px !important;
                right: 22px !important;
                bottom: 22px !important;
                max-width: 260px !important;
            }

            .visual-title {
                font-size: 30px !important;
                line-height: 1.02 !important;
            }

            .visual-subtitle {
                font-size: 13px !important;
                line-height: 1.55 !important;
                max-width: 260px !important;
            }

            .brand-badge {
                top: 18px !important;
                left: 18px !important;
                font-size: 11px !important;
            }

            .form-title {
                font-size: 34px !important;
                line-height: 1.04 !important;
            }

            .form-subtitle {
                font-size: 15px !important;
                line-height: 1.7 !important;
            }

            .form-input {
                min-height: 56px !important;
                font-size: 16px !important;
                border-radius: 18px !important;
            }

            .btn-login {
                min-height: 58px !important;
                border-radius: 20px !important;
                font-size: 17px !important;
            }
        }

        @media (max-width: 420px) {
            .visual-side,
            .login-portal-cashier .visual-side,
            .login-portal-backoffice .visual-side {
                min-height: 190px !important;
                max-height: 220px !important;
            }

            .visual-title {
                font-size: 26px !important;
            }

            .visual-subtitle {
                display: none !important;
            }

            .form-side {
                padding: 22px 18px 24px !important;
            }

            .form-title {
                font-size: 30px !important;
            }
        }


        /* LOGIN_HERO_FINAL_POLISH_V3 */
        @media (max-width: 1100px) {
            .visual-side,
            .login-portal-backoffice .visual-side,
            .login-portal-cashier .visual-side {
                background-position: center 58% !important;
            }

            .visual-side::after {
                background:
                    linear-gradient(180deg, rgba(17,24,39,0.20) 0%, rgba(17,24,39,0.38) 100%),
                    linear-gradient(135deg, rgba(232,106,58,0.10), rgba(17,24,39,0.08)) !important;
            }

            .visual-copy {
                left: 34px !important;
                right: 34px !important;
                bottom: 34px !important;
                max-width: 520px !important;
            }

            .visual-title {
                font-size: 38px !important;
                line-height: 1.02 !important;
                letter-spacing: -0.045em !important;
                margin-bottom: 12px !important;
                text-shadow: 0 5px 24px rgba(0,0,0,0.48) !important;
            }

            .visual-subtitle {
                font-size: 15px !important;
                line-height: 1.65 !important;
                max-width: 440px !important;
                text-shadow: 0 4px 18px rgba(0,0,0,0.48) !important;
            }

            .brand-badge {
                top: 22px !important;
                left: 22px !important;
                transform: scale(0.92);
                transform-origin: top left;
            }
        }

        @media (max-width: 700px) {
            .visual-side,
            .login-portal-backoffice .visual-side,
            .login-portal-cashier .visual-side {
                min-height: 230px !important;
                max-height: 240px !important;
                background-position: center 55% !important;
            }

            .visual-copy {
                left: 28px !important;
                right: 28px !important;
                bottom: 28px !important;
                max-width: 420px !important;
            }

            .visual-title {
                font-size: 34px !important;
                line-height: 1.02 !important;
                margin-bottom: 10px !important;
            }

            .visual-subtitle {
                font-size: 14px !important;
                line-height: 1.55 !important;
                max-width: 390px !important;
            }

            .form-side {
                padding-top: 32px !important;
            }

            .top-row {
                margin-bottom: 24px !important;
            }

            .login-chip {
                width: fit-content !important;
                max-width: 100% !important;
            }

            .form-title {
                font-size: 40px !important;
                line-height: 1.02 !important;
                margin-bottom: 14px !important;
            }
        }

        @media (max-width: 480px) {
            .visual-side,
            .login-portal-backoffice .visual-side,
            .login-portal-cashier .visual-side {
                min-height: 205px !important;
                max-height: 215px !important;
            }

            .visual-copy {
                left: 22px !important;
                right: 22px !important;
                bottom: 22px !important;
            }

            .visual-title {
                font-size: 28px !important;
            }

            .visual-subtitle {
                font-size: 12px !important;
                line-height: 1.45 !important;
                max-width: 310px !important;
            }

            .brand-badge {
                transform: scale(0.82);
            }

            .form-title {
                font-size: 34px !important;
            }
        }


        /* LOGIN_MOBILE_REMOVE_HERO_TEXT_V4 */
        @media (max-width: 1100px) {
            .visual-side,
            .login-portal-backoffice .visual-side,
            .login-portal-cashier .visual-side {
                min-height: 220px !important;
                max-height: 240px !important;
                background-position: center 58% !important;
            }

            .visual-copy {
                display: none !important;
            }

            .brand-badge {
                top: 18px !important;
                left: 18px !important;
                transform: none !important;
                background: rgba(17, 24, 39, 0.48) !important;
                border-color: rgba(255,255,255,0.24) !important;
                text-shadow: none !important;
            }

            .visual-side::before {
                content: "Back Office";
                position: absolute;
                left: 22px;
                bottom: 22px;
                z-index: 3;
                color: #ffffff;
                font-size: 26px;
                line-height: 1;
                font-weight: 900;
                letter-spacing: -0.04em;
                text-shadow: 0 5px 22px rgba(0,0,0,0.46);
            }

            .login-portal-cashier .visual-side::before {
                content: "Cashier";
            }

            .form-side {
                padding-top: 30px !important;
            }

            .form-title {
                margin-top: 0 !important;
            }
        }

        @media (max-width: 560px) {
            .visual-side,
            .login-portal-backoffice .visual-side,
            .login-portal-cashier .visual-side {
                min-height: 180px !important;
                max-height: 195px !important;
            }

            .visual-side::before {
                left: 18px;
                bottom: 18px;
                font-size: 24px;
            }

            .brand-badge {
                top: 14px !important;
                left: 14px !important;
                padding: 8px 11px !important;
                font-size: 10px !important;
            }

            .form-side {
                padding: 24px 20px 26px !important;
            }
        }



        @media (max-width: 1024px) {

            .shell {
                grid-template-columns: 1fr !important;
                max-width: 720px;
                overflow: hidden;
            }

            .visual-side {
                min-height: 240px;
                order: -1;
                border-radius: 28px 28px 0 0 !important;
            }

            .form-side {
                padding: 32px !important;
            }

            .visual-title {
                font-size: 42px !important;
                line-height: 1.05 !important;
            }

            .visual-subtitle {
                max-width: 90%;
                font-size: 16px !important;
            }

            .form-title {
                font-size: 42px !important;
            }

            .top-row {
                flex-wrap: wrap;
                gap: 14px;
            }

            .portal-switch {
                width: 100%;
                justify-content: flex-start;
            }
        }

        @media (max-width: 768px) {

            .page {
                padding: 18px !important;
            }

            .shell {
                border-radius: 24px !important;
            }

            .visual-side {
                min-height: 200px;
                padding: 26px !important;
            }

            .visual-title {
                font-size: 34px !important;
            }

            .form-side {
                padding: 26px !important;
            }

            .form-title {
                font-size: 28px !important;
                line-height: 1.1;
            }

            .form-subtitle {
                font-size: 14px !important;
            }

            .btn-login {
                min-height: 56px;
                font-size: 16px;
            }
        }



        /* Cashier tablet login cleanup */
        @media (max-width: 1024px) {
            .login-portal-cashier .shell {
                grid-template-columns: 1fr !important;
                max-width: 720px !important;
                min-height: auto !important;
            }

            .login-portal-cashier .visual-side {
                order: -1 !important;
                min-height: 230px !important;
                padding: 28px !important;
                border-radius: 28px 28px 0 0 !important;
            }

            .login-portal-cashier .form-side {
                padding: 30px !important;
            }

            .login-portal-cashier .form-title {
                font-size: 30px !important;
                line-height: 1.1 !important;
            }

            .login-portal-cashier .visual-title {
                font-size: 34px !important;
            }

            .login-portal-cashier .portal-switch {
                display: none !important;
            }
        }


        /* Compact tablet / APK login layout */
        @media (min-width: 860px) {
            .page {
                padding: 14px;
            }

            .shell {
                min-height: min(680px, calc(100vh - 28px));
                grid-template-columns: 1fr 0.86fr;
            }

            .visual-side {
                min-height: min(680px, calc(100vh - 28px));
            }

            .form-side {
                padding: 28px 30px;
                overflow-y: auto;
            }

            .form-card {
                max-width: 380px;
            }
        }

        @media (min-width: 860px) and (max-height: 820px) {
            .shell {
                min-height: calc(100vh - 24px);
                border-radius: 28px;
            }

            .visual-side {
                min-height: calc(100vh - 24px);
            }

            .brand-badge {
                top: 20px;
                left: 20px;
                padding: 8px 12px;
            }

            .visual-copy {
                left: 24px;
                bottom: 24px;
            }

            .visual-title {
                font-size: 34px;
                margin-bottom: 8px;
            }

            .visual-subtitle {
                font-size: 13px;
                line-height: 1.55;
            }

            .form-side {
                padding: 22px 28px;
                align-items: center;
            }

            .top-row {
                margin-bottom: 18px;
            }

            .form-title {
                font-size: 28px;
                margin-bottom: 6px;
            }

            .form-subtitle {
                font-size: 13px;
                line-height: 1.5;
                margin-bottom: 16px;
            }

            .form-group {
                margin-bottom: 14px;
            }

            .form-input {
                min-height: 46px;
            }

            .btn-login {
                min-height: 48px;
            }

            .footer-note {
                margin-top: 14px;
            }
        }


        /* APK / Tablet Landscape Final Login Layout */
        @media (orientation: landscape) and (min-width: 700px) {
            html,
            body {
                width: 100%;
                min-height: 100%;
                overflow: hidden;
            }

            .page {
                width: 100vw !important;
                height: 100vh !important;
                min-height: 100vh !important;
                padding: 16px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }

            .shell {
                width: min(1120px, calc(100vw - 32px)) !important;
                height: min(620px, calc(100vh - 32px)) !important;
                min-height: 0 !important;
                max-height: calc(100vh - 32px) !important;
                display: grid !important;
                grid-template-columns: 1.05fr 0.95fr !important;
                border-radius: 28px !important;
                overflow: hidden !important;
            }

            .visual-side {
                display: block !important;
                min-height: 0 !important;
                height: 100% !important;
            }

            .visual-copy {
                left: 26px !important;
                bottom: 26px !important;
                max-width: 420px !important;
            }

            .visual-title {
                font-size: clamp(28px, 4vw, 42px) !important;
                line-height: 1.02 !important;
                margin-bottom: 8px !important;
            }

            .visual-subtitle {
                font-size: 13px !important;
                line-height: 1.55 !important;
                max-width: 360px !important;
            }

            .brand-badge {
                top: 22px !important;
                left: 22px !important;
                padding: 8px 12px !important;
                font-size: 11px !important;
            }

            .form-side {
                min-height: 0 !important;
                height: 100% !important;
                padding: 22px 30px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                overflow: hidden !important;
            }

            .form-card {
                width: 100% !important;
                max-width: 380px !important;
                max-height: 100% !important;
                overflow-y: auto !important;
                padding-right: 2px !important;
            }

            .top-row {
                margin-bottom: 16px !important;
                align-items: flex-start !important;
            }

            .logo-box {
                width: 40px !important;
                height: 40px !important;
                border-radius: 12px !important;
            }

            .logo-box img {
                width: 24px !important;
                height: 24px !important;
            }

            .login-chip {
                font-size: 11px !important;
                padding: 7px 10px !important;
            }

            .portal-switch {
                display: none !important;
            }

            .form-title {
                font-size: 28px !important;
                line-height: 1.05 !important;
                margin-bottom: 6px !important;
            }

            .form-subtitle {
                font-size: 13px !important;
                line-height: 1.5 !important;
                margin-bottom: 15px !important;
            }

            .alert {
                padding: 10px 12px !important;
                margin-bottom: 12px !important;
                font-size: 12px !important;
            }

            .form-group {
                margin-bottom: 13px !important;
            }

            .form-label {
                font-size: 12px !important;
                margin-bottom: 6px !important;
            }

            .form-input {
                height: 46px !important;
                min-height: 46px !important;
                padding: 0 14px !important;
                font-size: 14px !important;
                border-radius: 14px !important;
            }

            .password-toggle {
                height: 34px !important;
                padding: 0 10px !important;
                font-size: 11px !important;
                right: 7px !important;
            }

            .form-row {
                margin: 4px 0 14px !important;
            }

            .remember-wrap {
                font-size: 12px !important;
            }

            .btn-login {
                height: 48px !important;
                min-height: 48px !important;
                border-radius: 16px !important;
                font-size: 14px !important;
            }

            .footer-note {
                margin-top: 12px !important;
                font-size: 11px !important;
            }
        }

        @media (orientation: landscape) and (min-width: 700px) and (max-height: 520px) {
            .shell {
                width: calc(100vw - 20px) !important;
                height: calc(100vh - 20px) !important;
                max-height: calc(100vh - 20px) !important;
                grid-template-columns: 0.95fr 1.05fr !important;
                border-radius: 22px !important;
            }

            .page {
                padding: 10px !important;
            }

            .visual-title {
                font-size: 28px !important;
            }

            .visual-subtitle {
                display: none !important;
            }

            .form-side {
                padding: 16px 24px !important;
            }

            .form-title {
                font-size: 24px !important;
            }

            .form-subtitle {
                margin-bottom: 10px !important;
            }

            .form-group {
                margin-bottom: 10px !important;
            }

            .form-input {
                height: 42px !important;
                min-height: 42px !important;
            }

            .btn-login {
                height: 44px !important;
                min-height: 44px !important;
            }

            .footer-note {
                display: none !important;
            }
        }


        /* FORCE Cashier Login Split Layout - APK Tablet */
        body.login-portal-cashier {
            overflow: hidden !important;
        }

        body.login-portal-cashier .page {
            width: 100vw !important;
            height: 100vh !important;
            min-height: 100vh !important;
            padding: 12px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .shell {
            width: calc(100vw - 24px) !important;
            height: calc(100vh - 24px) !important;
            min-height: 0 !important;
            max-height: calc(100vh - 24px) !important;
            display: grid !important;
            grid-template-columns: 48% 52% !important;
            grid-template-rows: 1fr !important;
            border-radius: 24px !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .visual-side {
            display: block !important;
            grid-column: 1 !important;
            grid-row: 1 !important;
            width: 100% !important;
            height: 100% !important;
            min-height: 0 !important;
        }

        body.login-portal-cashier .form-side {
            grid-column: 2 !important;
            grid-row: 1 !important;
            width: 100% !important;
            height: 100% !important;
            min-height: 0 !important;
            padding: 18px 26px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .form-card {
            width: 100% !important;
            max-width: 390px !important;
            max-height: 100% !important;
            overflow-y: auto !important;
        }

        body.login-portal-cashier .visual-title {
            font-size: 30px !important;
            line-height: 1.05 !important;
        }

        body.login-portal-cashier .visual-subtitle {
            font-size: 13px !important;
            line-height: 1.5 !important;
        }

        body.login-portal-cashier .form-title {
            font-size: 26px !important;
            margin-bottom: 6px !important;
        }

        body.login-portal-cashier .form-subtitle {
            font-size: 13px !important;
            line-height: 1.45 !important;
            margin-bottom: 14px !important;
        }

        body.login-portal-cashier .top-row {
            margin-bottom: 14px !important;
        }

        body.login-portal-cashier .form-group {
            margin-bottom: 12px !important;
        }

        body.login-portal-cashier .form-input {
            height: 42px !important;
            min-height: 42px !important;
            padding: 0 14px !important;
            border-radius: 14px !important;
            font-size: 13px !important;
        }

        body.login-portal-cashier .btn-login {
            height: 44px !important;
            min-height: 44px !important;
            border-radius: 16px !important;
        }

        body.login-portal-cashier .portal-switch,
        body.login-portal-cashier .footer-note {
            display: none !important;
        }

        @media (max-width: 520px) {
            body.login-portal-cashier {
                overflow: auto !important;
            }

            body.login-portal-cashier .page {
                height: auto !important;
                min-height: 100vh !important;
                overflow: auto !important;
            }

            body.login-portal-cashier .shell {
                height: auto !important;
                min-height: 0 !important;
                display: grid !important;
                grid-template-columns: 1fr !important;
                grid-template-rows: auto auto !important;
            }

            body.login-portal-cashier .visual-side {
                min-height: 180px !important;
                grid-column: 1 !important;
                grid-row: 1 !important;
            }

            body.login-portal-cashier .form-side {
                grid-column: 1 !important;
                grid-row: 2 !important;
            }
        }


        /* FINAL Full Split Cashier Login - Tablet APK */
        body.login-portal-cashier {
            background: #f4f6fb !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .page {
            width: 100vw !important;
            height: 100vh !important;
            min-height: 100vh !important;
            padding: 0 !important;
            display: flex !important;
            align-items: stretch !important;
            justify-content: stretch !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .shell {
            width: 100vw !important;
            height: 100vh !important;
            min-height: 100vh !important;
            max-height: 100vh !important;
            border-radius: 0 !important;
            border: 0 !important;
            box-shadow: none !important;
            background: #ffffff !important;
            display: grid !important;
            grid-template-columns: 50% 50% !important;
            grid-template-rows: 100vh !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .visual-side {
            grid-column: 1 !important;
            grid-row: 1 !important;
            display: block !important;
            width: 100% !important;
            height: 100vh !important;
            min-height: 100vh !important;
            border-radius: 0 !important;
            background:
                linear-gradient(180deg, rgba(17,24,39,0.12), rgba(17,24,39,0.38)),
                url('{{ asset('images/login-cover.jpg') }}') center center / cover no-repeat !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .visual-side::after {
            background: linear-gradient(135deg, rgba(15,23,42,0.10), rgba(232,106,58,0.18)) !important;
        }

        body.login-portal-cashier .brand-badge {
            top: 32px !important;
            left: 34px !important;
            padding: 10px 15px !important;
            font-size: 12px !important;
        }

        body.login-portal-cashier .visual-copy {
            left: 38px !important;
            right: 38px !important;
            bottom: 38px !important;
            max-width: 520px !important;
        }

        body.login-portal-cashier .visual-title {
            font-size: clamp(34px, 5vw, 58px) !important;
            line-height: 0.98 !important;
            letter-spacing: -0.05em !important;
            margin: 0 0 12px !important;
        }

        body.login-portal-cashier .visual-subtitle {
            display: block !important;
            font-size: 14px !important;
            line-height: 1.65 !important;
            max-width: 420px !important;
        }

        body.login-portal-cashier .form-side {
            grid-column: 2 !important;
            grid-row: 1 !important;
            width: 100% !important;
            height: 100vh !important;
            min-height: 100vh !important;
            padding: 34px 54px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background:
                radial-gradient(circle at top right, rgba(232,106,58,0.09), transparent 30%),
                #ffffff !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .form-card {
            width: 100% !important;
            max-width: 430px !important;
            max-height: calc(100vh - 68px) !important;
            overflow-y: auto !important;
            padding: 0 !important;
        }

        body.login-portal-cashier .top-row {
            display: grid !important;
            grid-template-columns: 1fr auto !important;
            align-items: center !important;
            gap: 14px !important;
            margin-bottom: 22px !important;
        }

        body.login-portal-cashier .logo-box {
            width: 44px !important;
            height: 44px !important;
            border-radius: 14px !important;
        }

        body.login-portal-cashier .logo-box img {
            width: 27px !important;
            height: 27px !important;
        }

        body.login-portal-cashier .logo-text {
            font-size: 14px !important;
        }

        body.login-portal-cashier .login-chip {
            font-size: 12px !important;
            padding: 8px 12px !important;
        }

        body.login-portal-cashier .portal-switch {
            display: none !important;
        }

        body.login-portal-cashier .form-title {
            font-size: 34px !important;
            line-height: 1.05 !important;
            letter-spacing: -0.04em !important;
            margin: 0 0 8px !important;
        }

        body.login-portal-cashier .form-subtitle {
            font-size: 14px !important;
            line-height: 1.6 !important;
            margin: 0 0 24px !important;
        }

        body.login-portal-cashier .form-group {
            margin-bottom: 17px !important;
        }

        body.login-portal-cashier .form-label {
            font-size: 12px !important;
            margin-bottom: 8px !important;
            font-weight: 800 !important;
        }

        body.login-portal-cashier .form-input {
            width: 100% !important;
            height: 50px !important;
            min-height: 50px !important;
            padding: 0 16px !important;
            border-radius: 15px !important;
            font-size: 14px !important;
            background: #ffffff !important;
        }

        body.login-portal-cashier .password-wrap .form-input {
            padding-right: 76px !important;
        }

        body.login-portal-cashier .password-toggle {
            height: 34px !important;
            right: 8px !important;
            border-radius: 999px !important;
            padding: 0 13px !important;
            font-size: 11px !important;
        }

        body.login-portal-cashier .form-row {
            margin: 2px 0 18px !important;
        }

        body.login-portal-cashier .remember-wrap {
            font-size: 13px !important;
        }

        body.login-portal-cashier .btn-login {
            width: 100% !important;
            height: 52px !important;
            min-height: 52px !important;
            border-radius: 16px !important;
            font-size: 15px !important;
            font-weight: 800 !important;
        }

        body.login-portal-cashier .footer-note {
            margin-top: 18px !important;
            font-size: 12px !important;
        }

        @media (max-height: 540px) {
            body.login-portal-cashier .form-side {
                padding: 22px 46px !important;
            }

            body.login-portal-cashier .form-card {
                max-width: 420px !important;
                max-height: calc(100vh - 44px) !important;
            }

            body.login-portal-cashier .top-row {
                margin-bottom: 14px !important;
            }

            body.login-portal-cashier .form-title {
                font-size: 28px !important;
            }

            body.login-portal-cashier .form-subtitle {
                font-size: 13px !important;
                line-height: 1.45 !important;
                margin-bottom: 14px !important;
            }

            body.login-portal-cashier .form-group {
                margin-bottom: 11px !important;
            }

            body.login-portal-cashier .form-input {
                height: 42px !important;
                min-height: 42px !important;
            }

            body.login-portal-cashier .btn-login {
                height: 44px !important;
                min-height: 44px !important;
            }

            body.login-portal-cashier .footer-note {
                display: none !important;
            }

            body.login-portal-cashier .visual-title {
                font-size: 34px !important;
            }

            body.login-portal-cashier .visual-subtitle {
                font-size: 12px !important;
                line-height: 1.45 !important;
            }
        }


        /* FINAL Centered 50:50 Cashier Login */
        body.login-portal-cashier {
            background:
                radial-gradient(circle at top left, rgba(232,106,58,0.08), transparent 28%),
                linear-gradient(180deg, #f7f8fc 0%, #eef2f8 100%) !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .page {
            width: 100vw !important;
            height: 100vh !important;
            min-height: 100vh !important;
            padding: 0 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .shell {
            width: min(980px, calc(100vw - 56px)) !important;
            height: min(560px, calc(100vh - 56px)) !important;
            min-height: 0 !important;
            max-height: calc(100vh - 56px) !important;
            display: grid !important;
            grid-template-columns: 50% 50% !important;
            grid-template-rows: 1fr !important;
            border-radius: 22px !important;
            border: 1px solid rgba(255,255,255,0.86) !important;
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.16) !important;
            background: #ffffff !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .visual-side {
            grid-column: 1 !important;
            grid-row: 1 !important;
            width: 100% !important;
            height: 100% !important;
            min-height: 0 !important;
            border-radius: 0 !important;
            display: block !important;
            background:
                linear-gradient(180deg, rgba(17,24,39,0.06), rgba(17,24,39,0.28)),
                url('{{ asset('images/login-cover.jpg') }}') center center / cover no-repeat !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .form-side {
            grid-column: 2 !important;
            grid-row: 1 !important;
            width: 100% !important;
            height: 100% !important;
            min-height: 0 !important;
            padding: 34px 50px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background:
                radial-gradient(circle at top right, rgba(232,106,58,0.08), transparent 32%),
                #ffffff !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .form-card {
            width: 100% !important;
            max-width: 360px !important;
            max-height: 100% !important;
            overflow-y: auto !important;
            margin: 0 auto !important;
        }

        body.login-portal-cashier .brand-badge {
            top: 30px !important;
            left: 30px !important;
            padding: 9px 14px !important;
            font-size: 11px !important;
        }

        body.login-portal-cashier .visual-copy {
            left: 30px !important;
            right: 30px !important;
            bottom: 28px !important;
            max-width: 360px !important;
        }

        body.login-portal-cashier .visual-title {
            font-size: 32px !important;
            line-height: 1 !important;
            margin: 0 !important;
            letter-spacing: -0.04em !important;
        }

        body.login-portal-cashier .visual-subtitle {
            display: none !important;
        }

        body.login-portal-cashier .top-row {
            display: grid !important;
            grid-template-columns: 1fr auto !important;
            align-items: center !important;
            gap: 12px !important;
            margin-bottom: 22px !important;
        }

        body.login-portal-cashier .logo-box {
            width: 38px !important;
            height: 38px !important;
            border-radius: 12px !important;
        }

        body.login-portal-cashier .logo-box img {
            width: 23px !important;
            height: 23px !important;
        }

        body.login-portal-cashier .logo-text {
            font-size: 13px !important;
        }

        body.login-portal-cashier .login-chip {
            font-size: 11px !important;
            padding: 7px 10px !important;
        }

        body.login-portal-cashier .portal-switch {
            display: none !important;
        }

        body.login-portal-cashier .form-title {
            font-size: 30px !important;
            line-height: 1.05 !important;
            margin: 0 0 8px !important;
            letter-spacing: -0.04em !important;
        }

        body.login-portal-cashier .form-subtitle {
            font-size: 13px !important;
            line-height: 1.55 !important;
            margin: 0 0 22px !important;
        }

        body.login-portal-cashier .form-group {
            margin-bottom: 15px !important;
        }

        body.login-portal-cashier .form-label {
            font-size: 12px !important;
            margin-bottom: 7px !important;
        }

        body.login-portal-cashier .form-input {
            height: 46px !important;
            min-height: 46px !important;
            border-radius: 15px !important;
            font-size: 13px !important;
            padding: 0 14px !important;
        }

        body.login-portal-cashier .password-wrap .form-input {
            padding-right: 72px !important;
        }

        body.login-portal-cashier .password-toggle {
            right: 7px !important;
            height: 32px !important;
            padding: 0 12px !important;
            font-size: 11px !important;
            border-radius: 999px !important;
        }

        body.login-portal-cashier .form-row {
            margin: 2px 0 16px !important;
        }

        body.login-portal-cashier .remember-wrap {
            font-size: 12px !important;
        }

        body.login-portal-cashier .btn-login {
            height: 48px !important;
            min-height: 48px !important;
            border-radius: 16px !important;
            font-size: 14px !important;
            font-weight: 800 !important;
        }

        body.login-portal-cashier .footer-note {
            display: none !important;
        }

        @media (max-height: 520px) {
            body.login-portal-cashier .shell {
                width: min(940px, calc(100vw - 40px)) !important;
                height: min(500px, calc(100vh - 40px)) !important;
                max-height: calc(100vh - 40px) !important;
            }

            body.login-portal-cashier .form-side {
                padding: 24px 46px !important;
            }

            body.login-portal-cashier .form-title {
                font-size: 26px !important;
            }

            body.login-portal-cashier .form-subtitle {
                margin-bottom: 14px !important;
            }

            body.login-portal-cashier .form-group {
                margin-bottom: 10px !important;
            }

            body.login-portal-cashier .form-input {
                height: 42px !important;
                min-height: 42px !important;
            }

            body.login-portal-cashier .btn-login {
                height: 44px !important;
                min-height: 44px !important;
            }
        }


        /* FINAL Force Full Image Split */
        body.login-portal-cashier .shell {
            position: relative !important;
            display: block !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .visual-side {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
            width: 50% !important;
            height: 100% !important;
            min-height: 100% !important;
            max-height: none !important;
            display: block !important;
            border-radius: 0 !important;
            background:
                linear-gradient(180deg, rgba(17,24,39,0.06), rgba(17,24,39,0.32)),
                url('{{ asset('images/login-cover.jpg') }}') center center / cover no-repeat !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .form-side {
            position: absolute !important;
            right: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
            width: 50% !important;
            height: 100% !important;
            min-height: 100% !important;
            max-height: none !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 34px 50px !important;
            overflow: hidden !important;
        }

        body.login-portal-cashier .visual-copy {
            position: absolute !important;
            left: 30px !important;
            right: 30px !important;
            bottom: 28px !important;
            z-index: 3 !important;
        }

        body.login-portal-cashier .brand-badge {
            position: absolute !important;
            top: 30px !important;
            left: 30px !important;
            z-index: 3 !important;
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
                            @if(($portal ?? 'backoffice') !== 'cashier')
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


<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!document.body.classList.contains('login-portal-cashier')) {
            return;
        }

        if (window.innerWidth <= 520) {
            return;
        }

        const shell = document.querySelector('.shell');
        const visual = document.querySelector('.visual-side');
        const form = document.querySelector('.form-side');

        if (shell) {
            shell.style.display = 'grid';
            shell.style.gridTemplateColumns = '48% 52%';
            shell.style.gridTemplateRows = '1fr';
            shell.style.width = 'calc(100vw - 24px)';
            shell.style.height = 'calc(100vh - 24px)';
            shell.style.minHeight = '0';
            shell.style.maxHeight = 'calc(100vh - 24px)';
            shell.style.overflow = 'hidden';
        }

        if (visual) {
            visual.style.display = 'block';
            visual.style.gridColumn = '1';
            visual.style.gridRow = '1';
            visual.style.height = '100%';
            visual.style.minHeight = '0';
        }

        if (form) {
            form.style.gridColumn = '2';
            form.style.gridRow = '1';
            form.style.height = '100%';
            form.style.minHeight = '0';
            form.style.overflow = 'hidden';
        }
    });
</script>


<script>
    /* FINAL_FULL_SPLIT_LOGIN_JS */
    document.addEventListener('DOMContentLoaded', function () {
        if (!document.body.classList.contains('login-portal-cashier')) {
            return;
        }

        const page = document.querySelector('.page');
        const shell = document.querySelector('.shell');
        const visual = document.querySelector('.visual-side');
        const form = document.querySelector('.form-side');
        const formCard = document.querySelector('.form-card');

        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';

        if (page) {
            page.style.width = '100vw';
            page.style.height = '100vh';
            page.style.padding = '0';
            page.style.overflow = 'hidden';
        }

        if (shell) {
            shell.style.width = '100vw';
            shell.style.height = '100vh';
            shell.style.minHeight = '100vh';
            shell.style.maxHeight = '100vh';
            shell.style.display = 'grid';
            shell.style.gridTemplateColumns = '50% 50%';
            shell.style.gridTemplateRows = '100vh';
            shell.style.borderRadius = '0';
            shell.style.boxShadow = 'none';
            shell.style.overflow = 'hidden';
        }

        if (visual) {
            visual.style.gridColumn = '1';
            visual.style.gridRow = '1';
            visual.style.width = '100%';
            visual.style.height = '100vh';
            visual.style.minHeight = '100vh';
            visual.style.borderRadius = '0';
            visual.style.display = 'block';
            visual.style.backgroundSize = 'cover';
            visual.style.backgroundPosition = 'center center';
        }

        if (form) {
            form.style.gridColumn = '2';
            form.style.gridRow = '1';
            form.style.width = '100%';
            form.style.height = '100vh';
            form.style.minHeight = '100vh';
            form.style.display = 'flex';
            form.style.alignItems = 'center';
            form.style.justifyContent = 'center';
            form.style.overflow = 'hidden';
        }

        if (formCard) {
            formCard.style.width = '100%';
            formCard.style.maxWidth = '430px';
            formCard.style.maxHeight = 'calc(100vh - 68px)';
            formCard.style.overflowY = 'auto';
        }
    });
</script>


<script>
    /* CENTERED_50_50_LOGIN_JS */
    document.addEventListener('DOMContentLoaded', function () {
        if (!document.body.classList.contains('login-portal-cashier')) {
            return;
        }

        const page = document.querySelector('.page');
        const shell = document.querySelector('.shell');
        const visual = document.querySelector('.visual-side');
        const form = document.querySelector('.form-side');
        const formCard = document.querySelector('.form-card');

        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';

        if (page) {
            page.style.width = '100vw';
            page.style.height = '100vh';
            page.style.display = 'flex';
            page.style.alignItems = 'center';
            page.style.justifyContent = 'center';
            page.style.padding = '0';
        }

        if (shell) {
            shell.style.width = 'min(980px, calc(100vw - 56px))';
            shell.style.height = 'min(560px, calc(100vh - 56px))';
            shell.style.minHeight = '0';
            shell.style.display = 'grid';
            shell.style.gridTemplateColumns = '50% 50%';
            shell.style.gridTemplateRows = '1fr';
            shell.style.borderRadius = '22px';
            shell.style.overflow = 'hidden';
        }

        if (visual) {
            visual.style.gridColumn = '1';
            visual.style.gridRow = '1';
            visual.style.width = '100%';
            visual.style.height = '100%';
            visual.style.minHeight = '0';
            visual.style.backgroundSize = 'cover';
            visual.style.backgroundPosition = 'center center';
        }

        if (form) {
            form.style.gridColumn = '2';
            form.style.gridRow = '1';
            form.style.width = '100%';
            form.style.height = '100%';
            form.style.minHeight = '0';
            form.style.display = 'flex';
            form.style.alignItems = 'center';
            form.style.justifyContent = 'center';
            form.style.padding = '34px 50px';
        }

        if (formCard) {
            formCard.style.maxWidth = '360px';
            formCard.style.margin = '0 auto';
        }
    });
</script>


<script>
    /* ABSOLUTE_FULL_IMAGE_SPLIT_JS */
    document.addEventListener('DOMContentLoaded', function () {
        if (!document.body.classList.contains('login-portal-cashier')) {
            return;
        }

        const shell = document.querySelector('.shell');
        const visual = document.querySelector('.visual-side');
        const form = document.querySelector('.form-side');

        if (shell) {
            shell.style.position = 'relative';
            shell.style.display = 'block';
            shell.style.overflow = 'hidden';
        }

        if (visual) {
            visual.style.position = 'absolute';
            visual.style.left = '0';
            visual.style.top = '0';
            visual.style.bottom = '0';
            visual.style.width = '50%';
            visual.style.height = '100%';
            visual.style.minHeight = '100%';
            visual.style.maxHeight = 'none';
            visual.style.display = 'block';
            visual.style.backgroundSize = 'cover';
            visual.style.backgroundPosition = 'center center';
        }

        if (form) {
            form.style.position = 'absolute';
            form.style.right = '0';
            form.style.top = '0';
            form.style.bottom = '0';
            form.style.width = '50%';
            form.style.height = '100%';
            form.style.minHeight = '100%';
            form.style.maxHeight = 'none';
            form.style.display = 'flex';
            form.style.alignItems = 'center';
            form.style.justifyContent = 'center';
        }
    });
</script>

</body>
</html>