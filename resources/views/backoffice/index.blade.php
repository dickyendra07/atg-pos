<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Back Office - ATG POS</title>
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
            max-width: 1480px;
            margin: 0 auto;
            background: rgba(255,255,255,0.56);
            border: 1px solid rgba(255,255,255,0.90);
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
            padding: 24px 28px 0;
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
            width: 36px;
            height: 36px;
            border-radius: 12px;
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
            flex-wrap: wrap;
        }

        .mini-info {
            font-size: 13px;
            color: var(--muted);
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.80);
            border: 1px solid #e5e7eb;
        }

        .btn {
            border: 0;
            cursor: pointer;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 14px;
            color: white;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(15,23,42,0.10);
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            opacity: 0.96;
        }

        .btn-dark {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .btn-brand {
            background: linear-gradient(135deg, var(--brand) 0%, #f08a57 100%);
        }

        .content {
            padding: 14px 28px 28px;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 22px;
            margin-bottom: 26px;
        }

        .hero-main {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #ffffff 0%, #fff9f5 58%, #fff1ea 100%);
            border: 1px solid #f0e1d8;
            border-radius: 30px;
            padding: 34px;
            min-height: 270px;
        }

        .hero-main::after {
            content: "";
            position: absolute;
            right: -70px;
            top: -70px;
            width: 240px;
            height: 240px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(232,106,58,0.14) 0%, rgba(232,106,58,0.04) 55%, rgba(232,106,58,0) 78%);
            pointer-events: none;
        }

        .hero-kicker {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #f1e3da;
            color: var(--brand-dark);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .hero-title {
            position: relative;
            z-index: 1;
            margin: 0 0 14px;
            font-size: 48px;
            line-height: 0.98;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
            max-width: 720px;
        }

        .hero-subtitle {
            position: relative;
            z-index: 1;
            margin: 0;
            max-width: 620px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .session-card {
            background: rgba(255,255,255,0.84);
            border: 1px solid #eceff5;
            border-radius: 30px;
            padding: 24px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .session-title {
            font-size: 15px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 12px;
        }

        .session-line {
            font-size: 14px;
            line-height: 1.9;
            color: #374151;
        }

        .label {
            color: #6b7280;
            font-weight: 700;
            margin-right: 6px;
        }

        .session-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 26px;
        }

        .stat-card {
            position: relative;
            overflow: hidden;
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 26px;
            padding: 22px;
            box-shadow: var(--shadow-soft);
            min-height: 160px;
        }

        .stat-card.orange {
            background: linear-gradient(180deg, #fff9f6 0%, #ffffff 100%);
            border-color: #f4ddd0;
        }

        .stat-card.green {
            background: linear-gradient(180deg, #f5fcf7 0%, #ffffff 100%);
            border-color: #d8f0de;
        }

        .stat-card.blue {
            background: linear-gradient(180deg, #f7faff 0%, #ffffff 100%);
            border-color: #dbe7ff;
        }

        .stat-card.violet {
            background: linear-gradient(180deg, #f8f7ff 0%, #ffffff 100%);
            border-color: #e3deff;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .stat-value {
            font-size: 44px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 12px;
        }

        .orange .stat-value { color: var(--brand-dark); }
        .green .stat-value { color: var(--green); }
        .blue .stat-value { color: var(--blue); }
        .violet .stat-value { color: var(--violet); }

        .stat-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
            max-width: 220px;
        }

        .section-card {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 30px;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .section-head {
            padding: 26px 26px 0;
        }

        .section-title {
            margin: 0 0 8px;
            font-size: 28px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .section-subtitle {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
            max-width: 780px;
        }

        .module-grid {
            padding: 24px 26px 26px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 18px;
        }

        .module-card {
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e7edf5;
            border-radius: 24px;
            padding: 20px;
            min-height: 210px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .module-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 26px rgba(15, 23, 42, 0.08);
            border-color: #f2d8cb;
        }

        .module-card::after {
            content: "";
            position: absolute;
            right: -24px;
            bottom: -24px;
            width: 90px;
            height: 90px;
            border-radius: 999px;
            opacity: 0.35;
        }

        .module-card.orange::after {
            background: radial-gradient(circle, rgba(232,106,58,0.16) 0%, rgba(232,106,58,0) 72%);
        }

        .module-card.green::after {
            background: radial-gradient(circle, rgba(22,101,52,0.16) 0%, rgba(22,101,52,0) 72%);
        }

        .module-card.blue::after {
            background: radial-gradient(circle, rgba(29,78,216,0.12) 0%, rgba(29,78,216,0) 72%);
        }

        .module-card.violet::after {
            background: radial-gradient(circle, rgba(91,75,209,0.12) 0%, rgba(91,75,209,0) 72%);
        }

        .module-icon {
            width: 64px;
            height: 64px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
            border: 1px solid transparent;
            box-shadow: 0 10px 20px rgba(15,23,42,0.05);
        }

        .icon-orange {
            background: #fff3eb;
            border-color: #f3d7c9;
        }

        .icon-green {
            background: #eefaf1;
            border-color: #d8f0de;
        }

        .icon-blue {
            background: #eff6ff;
            border-color: #dbe7ff;
        }

        .icon-violet {
            background: #f4f3ff;
            border-color: #e3deff;
        }

        .module-icon svg {
            width: 28px;
            height: 28px;
            stroke: #111827;
            fill: none;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .module-title {
            font-size: 22px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 10px;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .module-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.75;
            max-width: 240px;
        }

        .module-link {
            margin-top: 18px;
            font-size: 13px;
            font-weight: 800;
            color: var(--brand-dark);
        }

        .bottom-bar {
            margin-top: 22px;
            padding: 15px 16px;
            border-radius: 18px;
            background: #eef2ff;
            color: #3730a3;
            border: 1px solid #dbe3ff;
            font-weight: 700;
            font-size: 14px;
        }

        @media (max-width: 1280px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .module-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 780px) {
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
            }

            .hero-title {
                font-size: 34px;
            }

            .stats-grid,
            .module-grid {
                grid-template-columns: 1fr;
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
                        <div class="brand-sub">Back Office Workspace</div>
                    </div>
                </div>

                <div class="top-actions">
                    <div class="mini-info">
                        {{ $user->name }} • {{ $user->role->name ?? '-' }}
                    </div>
                    <a href="{{ route('dashboard') }}" class="btn btn-dark">Mode Select</a>
                </div>
            </div>

            <div class="content">
                <div class="hero">
                    <div class="hero-main">
                        <div class="hero-kicker">Back Office Dashboard</div>
                        <h1 class="hero-title">Monitor and manage your operations in one elegant workspace.</h1>
                        <p class="hero-subtitle">
                            Kelola inventory, warehouse, transfer, recipe, produksi bahan setengah jadi, transaksi, dan monitoring shift dari dashboard yang lebih clean, ringan, dan nyaman dipresentasikan.
                        </p>
                    </div>

                    <div class="session-card">
                        <div>
                            <div class="session-title">Session Info</div>
                            <div class="session-line"><span class="label">User:</span>{{ $user->name }}</div>
                            <div class="session-line"><span class="label">Role:</span>{{ $user->role->name ?? '-' }}</div>
                            <div class="session-line"><span class="label">Outlet:</span>{{ $user->outlet->name ?? '-' }}</div>
                        </div>

                        <div class="session-actions">
                            <a href="{{ route('cashier.index') }}" class="btn btn-brand">Cashier</a>
                            <a href="{{ route('dashboard') }}" class="btn btn-dark">Back</a>
                        </div>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card orange">
                        <div class="stat-label">Master Data</div>
                        <div class="stat-value">6</div>
                        <div class="stat-desc">Outlets, warehouses, products, variants, ingredients, dan recipes aktif.</div>
                    </div>

                    <div class="stat-card green">
                        <div class="stat-label">Inventory Control</div>
                        <div class="stat-value">6</div>
                        <div class="stat-desc">Stock balances, stock movements, production recipes, productions, adjustment, dan opname gudang.</div>
                    </div>

                    <div class="stat-card blue">
                        <div class="stat-label">Transfers</div>
                        <div class="stat-value">3</div>
                        <div class="stat-desc">Warehouse ke outlet, outlet ke outlet, dan outlet ke gudang.</div>
                    </div>

                    <div class="stat-card violet">
                        <div class="stat-label">Sales Access</div>
                        <div class="stat-value">3</div>
                        <div class="stat-desc">Cashier flow, transaksi back office, dan monitoring shift sudah saling terhubung.</div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-head">
                        <h2 class="section-title">Core Modules</h2>
                        <p class="section-subtitle">
                            Semua modul utama tetap lengkap dalam satu area supaya tidak ada duplikasi, tidak ada fitur yang hilang, dan struktur antara Warehouse, Inventory Control, Production, Transfers, transaksi, dan audit operasional tetap jelas.
                        </p>
                    </div>

                    <div class="module-grid">
                        <a href="{{ route('backoffice.outlets.index') }}" class="module-card orange">
                            <div>
                                <div class="module-icon icon-orange">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M4 20h16"></path>
                                        <path d="M6 20V8l6-4 6 4v12"></path>
                                        <path d="M9 20v-5h6v5"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Outlets</div>
                                <div class="module-desc">Kelola outlet, alamat, nomor telepon, dan status aktif outlet.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.warehouses.index') }}" class="module-card green">
                            <div>
                                <div class="module-icon icon-green">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M3 10.5 12 5l9 5.5"></path>
                                        <path d="M5 9.5V19h14V9.5"></path>
                                        <path d="M9 19v-5h6v5"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Warehouses</div>
                                <div class="module-desc">Pantau gudang, stock warehouse, movement gudang, dan akses transfer operasional.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.users.index') }}" class="module-card violet">
                            <div>
                                <div class="module-icon icon-violet">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9.5" cy="7" r="4"></circle>
                                        <path d="M20 8v6"></path>
                                        <path d="M17 11h6"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Users</div>
                                <div class="module-desc">Tambah kasir, admin, role user, dan outlet akses utama dari back office.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.products.index') }}" class="module-card blue">
                            <div>
                                <div class="module-icon icon-blue">
                                    <svg viewBox="0 0 24 24">
                                        <rect x="4" y="4" width="16" height="16" rx="3"></rect>
                                        <path d="M8 9h8"></path>
                                        <path d="M8 13h8"></path>
                                        <path d="M8 17h4"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Products</div>
                                <div class="module-desc">Kelola product utama, brand, category, dan status aktif product.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.variants.index') }}" class="module-card violet">
                            <div>
                                <div class="module-icon icon-violet">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M7 7h10"></path>
                                        <path d="M7 12h10"></path>
                                        <path d="M7 17h6"></path>
                                        <path d="M5 7h.01"></path>
                                        <path d="M5 12h.01"></path>
                                        <path d="M5 17h.01"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Variants</div>
                                <div class="module-desc">Kelola variant size, harga jual, kode variant, dan status aktif product.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.ingredients.index') }}" class="module-card green">
                            <div>
                                <div class="module-icon icon-green">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M7 4h10"></path>
                                        <path d="M9 4v5l-4 7a3 3 0 0 0 2.6 4.5h8.8A3 3 0 0 0 19 16l-4-7V4"></path>
                                        <path d="M8 14h8"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Ingredients</div>
                                <div class="module-desc">Kelola bahan baku, minimum stock, cost per unit, dan data utama ingredient.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.recipes.index') }}" class="module-card orange">
                            <div>
                                <div class="module-icon icon-orange">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M6 4h12"></path>
                                        <path d="M8 4v16"></path>
                                        <path d="M16 4v16"></path>
                                        <path d="M8 9h8"></path>
                                        <path d="M8 14h8"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Recipes</div>
                                <div class="module-desc">Atur recipe manual dan recipe import untuk deduction operasional.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.production-recipes.index') }}" class="module-card violet">
                            <div>
                                <div class="module-icon icon-violet">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M7 4h10"></path>
                                        <path d="M9 4v4"></path>
                                        <path d="M15 4v4"></path>
                                        <path d="M5 10h14"></path>
                                        <path d="M6 20h12"></path>
                                        <path d="M8 14h8"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Production Recipes</div>
                                <div class="module-desc">Kelola recipe internal untuk bahan setengah jadi, termasuk output semi-finished dan komposisi bahan mentahnya.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.productions.index') }}" class="module-card green">
                            <div>
                                <div class="module-icon icon-green">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M4 7h16"></path>
                                        <path d="M6 7v10h12V7"></path>
                                        <path d="M9 12h6"></path>
                                        <path d="M12 9v6"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Productions</div>
                                <div class="module-desc">Jalankan produksi stok dari bahan mentah ke bahan setengah jadi dan monitor histori hasil produksinya.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.stock-balances.index') }}" class="module-card blue">
                            <div>
                                <div class="module-icon icon-blue">
                                    <svg viewBox="0 0 24 24">
                                        <rect x="4" y="4" width="16" height="16" rx="3"></rect>
                                        <path d="M8 9h8"></path>
                                        <path d="M8 13h8"></path>
                                        <path d="M8 17h4"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Inventory Control</div>
                                <div class="module-desc">Pusat kontrol stock balances, penerimaan barang, adjustment, import, dan opname gudang.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.stock-movements.index') }}" class="module-card violet">
                            <div>
                                <div class="module-icon icon-violet">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M5 17h14"></path>
                                        <path d="M5 12h10"></path>
                                        <path d="M5 7h6"></path>
                                        <path d="M17 9l2-2 2 2"></path>
                                        <path d="M19 7v10"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Stock Movements</div>
                                <div class="module-desc">Audit histori stock in, adjustment, opname, transfer, sales usage, dan produksi.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.transfers.index') }}" class="module-card orange">
                            <div>
                                <div class="module-icon icon-orange">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M7 7h11"></path>
                                        <path d="m14 4 4 3-4 3"></path>
                                        <path d="M17 17H6"></path>
                                        <path d="m10 14-4 3 4 3"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Transfers</div>
                                <div class="module-desc">Perpindahan stock antar lokasi internal: gudang ke outlet, outlet ke outlet, dan outlet ke gudang.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.transactions.index') }}" class="module-card green">
                            <div>
                                <div class="module-icon icon-green">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M4 7h16"></path>
                                        <rect x="3" y="5" width="18" height="14" rx="3"></rect>
                                        <path d="M7 15h3"></path>
                                        <path d="M14 15h3"></path>
                                    </svg>
                                </div>
                                <div class="module-title">Transactions</div>
                                <div class="module-desc">Lihat transaksi cashier, payment method, receipt, dan sales history yang sudah terhubung.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>

                        <a href="{{ route('backoffice.shifts.index') }}" class="module-card blue">
                            <div>
                                <div class="module-icon icon-blue">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M12 6v6l4 2"></path>
                                        <circle cx="12" cy="12" r="8"></circle>
                                    </svg>
                                </div>
                                <div class="module-title">Shifts</div>
                                <div class="module-desc">Monitor shift kasir, opening cash, closing cash, expected cash, dan performa transaksi per shift.</div>
                            </div>
                            <div class="module-link">Open module</div>
                        </a>
                    </div>
                </div>

                <div class="bottom-bar">
                    Back Office Dashboard active: semua modul utama tetap lengkap, tanpa duplikasi, dan dengan struktur yang lebih jelas antara Warehouse, Inventory Control, Production Recipes, Productions, Transfers, Transactions, Shift Monitoring, dan User Management.
                </div>
            </div>
        </div>
    </div>
</body>
</html>