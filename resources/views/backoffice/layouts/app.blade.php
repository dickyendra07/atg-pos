<!DOCTYPE html>
<html lang="en">
<head>

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

        .backoffice-context-bar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-bottom: 18px;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
        }

        .backoffice-context-bar label { font-size: 12px; font-weight: 800; color: #64748b; letter-spacing: .06em; }
        .backoffice-context-bar select { min-width: 280px; border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 12px; background: #fff; color: #0f172a; font-weight: 700; }
        .backoffice-context-bar button { border: 0; border-radius: 10px; padding: 10px 15px; background: #f97316; color: #fff; font-weight: 800; cursor: pointer; }
        .context-warning { margin-bottom: 14px; padding: 12px 15px; border-radius: 12px; background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }

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

        .mobile-menu-button,
        .mobile-sidebar-close {
            border: 0;
            cursor: pointer;
            min-height: 42px;
            padding: 0 14px;
            border-radius: 14px;
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            color: #ffffff;
            font-size: 13px;
            font-weight: 900;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 10px 20px rgba(15,23,42,0.12);
        }

        .mobile-sidebar-close {
            width: 100%;
            margin: 0 0 12px;
            background: #f3f4f6;
            color: #111827;
            box-shadow: none;
            border: 1px solid #e5e7eb;
        }

        .sidebar-overlay {
            display: none;
        }

        .mobile-topbar-title {
            display: grid;
            gap: 2px;
            min-width: 0;
        }

        .mobile-topbar-title strong {
            font-size: 14px;
            color: #111827;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-topbar-title span {
            font-size: 11px;
            color: #6b7280;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        @media (max-width: 1180px) {
            .page {
                padding: 0;
            }

            .shell {
                max-width: none;
                min-height: 100vh;
                border-radius: 0;
                border: 0;
                box-shadow: none;
                background: transparent;
            }

            .workspace {
                display: block;
                min-height: 100vh;
            }

            .mobile-topbar {
                position: sticky;
                top: 0;
                z-index: 80;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 12px 14px;
                background: rgba(255,255,255,0.94);
                border-bottom: 1px solid #e5e7eb;
                backdrop-filter: blur(10px);
            }

            .sidebar {
                position: fixed;
                z-index: 100;
                top: 0;
                left: 0;
                bottom: 0;
                width: min(86vw, 340px);
                overflow-y: auto;
                padding: 16px 14px 24px;
                border-right: 1px solid #e5e7eb;
                border-bottom: 0;
                background: rgba(255,255,255,0.98);
                transform: translateX(-105%);
                transition: transform 0.22s ease;
                box-shadow: 24px 0 60px rgba(15,23,42,0.18);
            }

            body.backoffice-sidebar-open .sidebar {
                transform: translateX(0);
            }

            .sidebar-overlay {
                position: fixed;
                inset: 0;
                z-index: 90;
                display: block;
                background: rgba(15,23,42,0.42);
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.22s ease;
            }

            body.backoffice-sidebar-open .sidebar-overlay {
                opacity: 1;
                pointer-events: auto;
            }

            body.backoffice-sidebar-open {
                overflow: hidden;
            }

            .content {
                padding: 16px;
            }
        }

        @media (max-width: 780px) {
            body {
                background: #f6f7fb;
            }

            .content {
                padding: 12px;
            }

            .content-card,
            .card,
            .section-card,
            .panel-card,
            .import-card,
            .form-card,
            .detail-card {
                border-radius: 20px !important;
            }

            .table-wrap,
            .table-responsive,
            .overflow-table {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 760px;
            }

            .btn,
            button.btn,
            a.btn {
                min-height: 44px;
                border-radius: 14px;
            }

            input,
            select,
            textarea {
                font-size: 16px !important;
            }
        }

        @media (max-width: 560px) {
            .mobile-topbar {
                padding: 10px 12px;
            }

            .mobile-menu-button {
                min-height: 40px;
                padding: 0 12px;
            }

            .content {
                padding: 10px;
            }

            table {
                min-width: 720px;
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


        /* BACKOFFICE_MOBILE_LAYOUT_FIX_V2 */
        @media (max-width: 1180px) {
            html,
            body {
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }

            .page,
            .shell,
            .workspace,
            .content {
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }

            .mobile-topbar {
                min-height: 72px;
            }

            .mobile-topbar-title {
                text-align: right;
            }

            .mobile-topbar-title strong {
                font-size: 20px;
                line-height: 1.15;
            }

            .mobile-topbar-title span {
                font-size: 14px;
            }

            .mobile-menu-button {
                min-width: 132px;
                min-height: 54px;
                border-radius: 22px;
                font-size: 18px;
            }

            .sidebar {
                width: min(360px, 92vw) !important;
                max-width: 92vw !important;
                background: #ffffff !important;
                border-radius: 0 28px 28px 0;
                box-shadow: 22px 0 70px rgba(15,23,42,0.32) !important;
            }

            .sidebar-overlay {
                background: rgba(15, 23, 42, 0.62) !important;
                backdrop-filter: blur(2px);
            }

            .sidebar-link {
                min-height: 58px;
                font-size: 16px;
                border-radius: 18px;
            }

            .sidebar-nav-icon {
                width: 46px;
                height: 46px;
                border-radius: 16px;
            }

            .sidebar-brand {
                padding: 14px;
                border-radius: 22px;
            }

            .sidebar-brand-name {
                font-size: 17px;
            }

            .sidebar-brand-sub {
                font-size: 12px;
            }

            .sidebar-title {
                font-size: 13px;
                margin-top: 20px;
            }

            .content {
                padding: 18px;
            }
        }

        @media (max-width: 560px) {
            .mobile-topbar {
                min-height: 64px;
            }

            .mobile-menu-button {
                min-width: 112px;
                min-height: 48px;
                font-size: 16px;
            }

            .mobile-topbar-title strong {
                font-size: 16px;
            }

            .mobile-topbar-title span {
                font-size: 12px;
            }

            .content {
                padding: 12px;
            }
        }

    </style>
</head>
<body>
    <div class="page">
        <div class="shell">
            <div class="workspace">
                <div class="mobile-topbar">
                    <button type="button" class="mobile-menu-button" id="backoffice-mobile-menu-button" aria-label="Open back office menu">
                        ☰ Menu
                    </button>

                    <div class="mobile-topbar-title">
                        <strong>{{ $pageTitle ?? 'Back Office - ATG POS' }}</strong>
                        <span>Back Office</span>
                    </div>
                </div>

                <div class="sidebar-overlay" id="backoffice-sidebar-overlay"></div>

                <aside class="sidebar" id="backoffice-sidebar">
                    <button type="button" class="mobile-sidebar-close" id="backoffice-sidebar-close">
                        Close Menu
                    </button>

                    @include('backoffice.partials.sidebar')
                </aside>

                <main class="content">
                    @isset($backofficeOutletOptions)
                        <form class="backoffice-context-bar" method="POST" action="{{ route('backoffice.active-outlet.update') }}">
                            @csrf
                            <label for="active-backoffice-outlet">OUTLET</label>
                            <select id="active-backoffice-outlet" name="outlet_id" onchange="this.form.submit()">
                                <option value="">Semua Outlet yang Diizinkan</option>
                                @foreach($backofficeOutletOptions as $contextOutlet)
                                    <option value="{{ $contextOutlet->id }}" @selected((int) ($activeBackofficeOutlet?->id ?? 0) === (int) $contextOutlet->id)>
                                        {{ $contextOutlet->name }}
                                    </option>
                                @endforeach
                            </select>
                            <noscript><button type="submit">Terapkan</button></noscript>
                        </form>
                    @endisset

                    @if(session('warning'))
                        <div class="context-warning">{{ session('warning') }}</div>
                    @endif
                    @yield('content')
                </main>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const openButton = document.getElementById('backoffice-mobile-menu-button');
            const closeButton = document.getElementById('backoffice-sidebar-close');
            const overlay = document.getElementById('backoffice-sidebar-overlay');

            function openSidebar() {
                document.body.classList.add('backoffice-sidebar-open');
            }

            function closeSidebar() {
                document.body.classList.remove('backoffice-sidebar-open');
            }

            if (openButton) {
                openButton.addEventListener('click', openSidebar);
            }

            if (closeButton) {
                closeButton.addEventListener('click', closeSidebar);
            }

            if (overlay) {
                overlay.addEventListener('click', closeSidebar);
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeSidebar();
                }
            });

            document.querySelectorAll('.sidebar a').forEach(function (link) {
                link.addEventListener('click', closeSidebar);
            });
        })();
    </script>

</body>
</html>
