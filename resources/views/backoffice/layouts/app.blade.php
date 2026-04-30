<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#111827">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="ATG POS">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle ?? 'Back Office - ATG POS' }}</title>
    <style>
        :root {
            --bg: #f3f5fa;
            --surface: rgba(255,255,255,0.94);
            --surface-strong: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --brand: #e86a3a;
            --brand-dark: #c9552a;
            --brand-soft: #fff3eb;
            --navy: #0f172a;
            --green: #166534;
            --green-soft: #eefaf1;
            --blue: #1d4ed8;
            --blue-soft: #eff6ff;
            --violet: #5b4bd1;
            --violet-soft: #f4f3ff;
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.14);
            --shadow-soft: 0 16px 34px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }

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
            padding: 24px;
        }

        .shell {
            max-width: 1680px;
            margin: 0 auto;
            background: rgba(255,255,255,0.56);
            border: 1px solid rgba(255,255,255,0.90);
            border-radius: 34px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }

        .workspace {
            display: grid;
            grid-template-columns: 280px minmax(0, 1fr);
            min-height: calc(100vh - 48px);
        }

        .sidebar {
            background: rgba(255,255,255,0.72);
            border-right: 1px solid #edf1f6;
            padding: 22px 16px;
        }

        .content {
            min-width: 0;
            padding: 24px;
        }

        .content-card {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 30px;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 18px;
            background: rgba(255,255,255,0.82);
            border: 1px solid #eceff5;
            margin-bottom: 18px;
        }

        .sidebar-brand-logo {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: var(--brand-soft);
            border: 1px solid #f3d7c9;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .sidebar-brand-logo img {
            width: 24px;
            height: 24px;
            object-fit: contain;
        }

        .sidebar-brand-name {
            font-size: 14px;
            font-weight: 800;
            color: #111827;
            letter-spacing: 0.04em;
        }

        .sidebar-brand-sub {
            font-size: 11px;
            color: #9ca3af;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-top: 2px;
        }

        .sidebar-section {
            margin-top: 18px;
        }

        .sidebar-title {
            font-size: 11px;
            font-weight: 800;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 0 10px 10px;
        }

        .sidebar-menu {
            display: grid;
            gap: 6px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #374151;
            padding: 10px 12px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 700;
            transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
        }

        .sidebar-link:hover {
            background: rgba(255,255,255,0.90);
            transform: translateX(2px);
        }

        .sidebar-link.active {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            color: white;
            box-shadow: 0 10px 20px rgba(15,23,42,0.14);
        }

        .sidebar-link.active .sidebar-nav-icon {
            background: rgba(255,255,255,0.12);
            border-color: rgba(255,255,255,0.10);
        }

        .sidebar-nav-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid transparent;
            box-shadow: 0 8px 16px rgba(15,23,42,0.04);
            flex-shrink: 0;
        }

        .sidebar-nav-icon svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .sidebar-nav-icon.orange {
            background: #fff3eb;
            border-color: #f3d7c9;
        }

        .sidebar-nav-icon.green {
            background: #eefaf1;
            border-color: #d8f0de;
        }

        .sidebar-nav-icon.blue {
            background: #eff6ff;
            border-color: #dbe7ff;
        }

        .sidebar-nav-icon.violet {
            background: #f4f3ff;
            border-color: #e3deff;
        }

        .sidebar-footer {
            margin-top: 22px;
            padding: 12px 14px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #e8edf4;
            font-size: 12px;
            line-height: 1.7;
            color: #6b7280;
            font-weight: 700;
        }

        .mobile-topbar {
            display: none;
        }

        @media (max-width: 1180px) {
            .workspace {
                grid-template-columns: 1fr;
            }

            .sidebar {
                border-right: 0;
                border-bottom: 1px solid #edf1f6;
            }
        }

        @media (max-width: 780px) {
            .page {
                padding: 14px;
            }

            .content {
                padding: 16px;
            }

            .sidebar {
                padding: 16px 14px;
            }
        }
    
        .table-center th,
        .table-center td,
        .inventory-table-center th,
        .inventory-table-center td {
            text-align: center;
            vertical-align: middle;
        }

        .table-center .text-left,
        .inventory-table-center .text-left,
        .table-center .recommended-action-cell,
        .inventory-table-center .recommended-action-cell,
        .table-center .note-text,
        .inventory-table-center .note-text {
            text-align: left;
        }

        .table-center .action-stack,
        .inventory-table-center .action-stack {
            justify-content: center;
            align-items: center;
        }

        .table-center form,
        .inventory-table-center form {
            justify-content: center;
        }

    </style>
</head>
<body>
    <div class="page">
        <div class="shell">
            <div class="workspace">
                <aside class="sidebar">
                    @include('backoffice.partials.sidebar')
                </aside>

                <main class="content">
                    @yield('content')
                </main>
            </div>
        </div>
    </div>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/service-worker.js').catch(function () {
                    // Silent fail supaya tidak ganggu POS flow.
                });
            });
        }
    </script>

</body>
</html>