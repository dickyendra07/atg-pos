<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Mode - ATG POS</title>
    <style>
        :root {
            --bg: #f3f5fa;
            --surface: rgba(255,255,255,0.94);
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --brand: #e86a3a;
            --brand-dark: #c9552a;
            --brand-soft: #fff3eb;
            --navy: #0f172a;
            --green: #166534;
            --green-soft: #eefaf1;
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.14);
            --shadow-soft: 0 16px 34px rgba(15, 23, 42, 0.08);
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
            max-width: 1220px;
            background: rgba(255,255,255,0.56);
            border: 1px solid rgba(255,255,255,0.9);
            border-radius: 34px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 26px 28px 0;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.78);
            border: 1px solid #eceff5;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .brand-logo {
            width: 34px;
            height: 34px;
            border-radius: 11px;
            background: var(--brand-soft);
            border: 1px solid #f3d7c9;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .brand-logo img {
            width: 22px;
            height: 22px;
            object-fit: contain;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .brand-name {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.06em;
            color: #111827;
        }

        .brand-sub {
            font-size: 11px;
            color: #9ca3af;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mini-info {
            font-size: 13px;
            color: var(--muted);
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.80);
            border: 1px solid #e5e7eb;
        }

        .logout-btn {
            border: 0;
            cursor: pointer;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 14px;
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            color: white;
            font-size: 13px;
            font-weight: 800;
            box-shadow: 0 10px 20px rgba(15,23,42,0.12);
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .logout-btn:hover {
            transform: translateY(-1px);
            opacity: 0.96;
        }

        .content {
            padding: 10px 28px 28px;
        }

        .hero {
            text-align: center;
            padding: 34px 10px 26px;
        }

        .hero-title {
            margin: 0 0 12px;
            font-size: 50px;
            line-height: 1.02;
            font-weight: 800;
            letter-spacing: -0.035em;
            color: #111827;
        }

        .hero-subtitle {
            margin: 0 auto;
            max-width: 560px;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.75;
        }

        .mode-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        .mode-card {
            position: relative;
            overflow: hidden;
            min-height: 410px;
            border-radius: 30px;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 28px;
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(255,255,255,0.72);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .mode-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 26px 48px rgba(15, 23, 42, 0.12);
        }

        .mode-card::before {
            content: "";
            position: absolute;
            right: -38px;
            top: -38px;
            width: 190px;
            height: 190px;
            border-radius: 999px;
            opacity: 0.46;
            pointer-events: none;
        }

        .cashier-card {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.08), rgba(255,255,255,0.03)),
                linear-gradient(135deg, #f4fbf6 0%, #edf9f0 52%, #f9fdf9 100%);
        }

        .cashier-card::before {
            background: radial-gradient(circle, rgba(22,101,52,0.12) 0%, rgba(22,101,52,0) 72%);
        }

        .backoffice-card {
            background:
                linear-gradient(180deg, rgba(255,255,255,0.08), rgba(255,255,255,0.03)),
                linear-gradient(135deg, #fff8f4 0%, #fff2ea 52%, #fffaf7 100%);
        }

        .backoffice-card::before {
            background: radial-gradient(circle, rgba(232,106,58,0.14) 0%, rgba(232,106,58,0) 72%);
        }

        .card-top,
        .card-bottom {
            position: relative;
            z-index: 1;
        }

        .mode-icon {
            width: 92px;
            height: 92px;
            border-radius: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            background: rgba(255,255,255,0.80);
            border: 1px solid rgba(255,255,255,0.86);
            box-shadow: 0 14px 28px rgba(15,23,42,0.06);
        }

        .mode-icon svg {
            width: 42px;
            height: 42px;
        }

        .cashier-icon svg {
            stroke: #17663a;
        }

        .backoffice-icon svg {
            stroke: #c9552a;
        }

        .mode-title {
            margin: 0 0 12px;
            font-size: 42px;
            line-height: 1.02;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #111827;
        }

        .mode-desc {
            margin: 0;
            max-width: 430px;
            color: #4b5563;
            font-size: 15px;
            line-height: 1.8;
        }

        .mode-meta {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
            max-width: 350px;
            margin-bottom: 18px;
        }

        .enter-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 52px;
            padding: 0 20px;
            border-radius: 16px;
            color: white;
            font-size: 14px;
            font-weight: 800;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.10);
            width: fit-content;
        }

        .cashier-enter {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
        }

        .backoffice-enter {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        @media (max-width: 1024px) {
            .mode-grid {
                grid-template-columns: 1fr;
            }

            .hero-title {
                font-size: 40px;
            }
        }

        @media (max-width: 640px) {
            .page {
                padding: 14px;
            }

            .topbar,
            .content {
                padding-left: 18px;
                padding-right: 18px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                padding-top: 18px;
            }

            .top-actions {
                width: 100%;
                justify-content: space-between;
            }

            .hero {
                padding-top: 24px;
            }

            .hero-title {
                font-size: 32px;
            }

            .mode-title {
                font-size: 34px;
            }

            .mode-card {
                min-height: 340px;
                padding: 22px;
            }

            .mini-info {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="shell">
            <div class="topbar">
                <div class="brand">
                    <div class="brand-logo">
                        <img src="{{ asset('images/atg-icon.png') }}" alt="ATG Logo">
                    </div>
                    <div class="brand-text">
                        <div class="brand-name">ATG POS</div>
                        <div class="brand-sub">Workspace</div>
                    </div>
                </div>

                <div class="top-actions">
                    <div class="mini-info">
                        {{ $user->name }} • {{ $user->role->name ?? '-' }}
                    </div>

                    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="logout-btn">Logout</button>
                    </form>
                </div>
            </div>

            <div class="content">
                <div class="hero">
                    <h1 class="hero-title">Choose your workspace.</h1>
                    <p class="hero-subtitle">
                        Start with the mode you need and keep the flow simple.
                    </p>
                </div>

                <div class="mode-grid">
                    <a href="{{ route('cashier.index') }}" class="mode-card cashier-card">
                        <div class="card-top">
                            <div class="mode-icon cashier-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3.5" y="6" width="17" height="12" rx="2.5"></rect>
                                    <path d="M7 6V4.8A1.8 1.8 0 0 1 8.8 3h6.4A1.8 1.8 0 0 1 17 4.8V6"></path>
                                    <path d="M7 10h10"></path>
                                    <path d="M8 14h2"></path>
                                    <path d="M12 14h4"></path>
                                </svg>
                            </div>

                            <h2 class="mode-title">Cashier</h2>
                            <p class="mode-desc">
                                Fast selling flow for checkout and customer service.
                            </p>
                        </div>

                        <div class="card-bottom">
                            <div class="mode-meta">
                                Best for daily outlet transactions.
                            </div>
                            <div class="enter-btn cashier-enter">Enter Cashier</div>
                        </div>
                    </a>

                    <a href="{{ route('backoffice.index') }}" class="mode-card backoffice-card">
                        <div class="card-top">
                            <div class="mode-icon backoffice-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="4" y="4" width="6.5" height="6.5" rx="1.6"></rect>
                                    <rect x="13.5" y="4" width="6.5" height="6.5" rx="1.6"></rect>
                                    <rect x="4" y="13.5" width="6.5" height="6.5" rx="1.6"></rect>
                                    <rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.6"></rect>
                                </svg>
                            </div>

                            <h2 class="mode-title">Back Office</h2>
                            <p class="mode-desc">
                                Full operational control for stock, warehouse, transfers, and reports.
                            </p>
                        </div>

                        <div class="card-bottom">
                            <div class="mode-meta">
                                Best for admin, owner, and operational monitoring.
                            </div>
                            <div class="enter-btn backoffice-enter">Enter Back Office</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>