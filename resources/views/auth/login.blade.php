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

    </style>
</head>
<body>
    <div class="page">
        <div class="shell">
            <div class="visual-side">
                <div class="brand-badge">
                    <span class="brand-dot"></span>
                    ATG POS
                </div>

                <div class="visual-copy">
                    <h1 class="visual-title">Modern login for daily operations.</h1>
                    <p class="visual-subtitle">Simple, clean, and ready for demo.</p>
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

                        <div class="login-chip">Secure Login</div>
                    </div>

                    <h2 class="form-title">Welcome back</h2>
                    <p class="form-subtitle">Please sign in to continue.</p>

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

                    <form method="POST" action="{{ route('login.store') }}">
                        @csrf

                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                class="form-input"
                                placeholder="Enter your email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                            >
                            @error('email')
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

</body>
</html>