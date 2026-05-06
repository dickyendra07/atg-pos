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
    <title>Cashier - ATG POS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            --bg: #f3f5fa;
            --surface: rgba(255,255,255,0.94);
            --surface-soft: #f8fafc;
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
            --red: #dc2626;
            --red-soft: #fff1f1;
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.14);
            --shadow-soft: 0 16px 34px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            font-family: Arial, sans-serif;
            min-height: 100%;
            background:
                radial-gradient(circle at top left, rgba(232,106,58,0.10), transparent 20%),
                linear-gradient(180deg, #f7f8fc 0%, #eef2f8 100%);
            color: var(--text);
        }

        body.modal-open {
            overflow: hidden;
        }

        .page {
            min-height: 100vh;
            padding: 20px;
        }

        .shell {
            max-width: 1620px;
            margin: 0 auto;
            background: rgba(255,255,255,0.56);
            border: 1px solid rgba(255,255,255,0.9);
            border-radius: 34px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
            overflow: visible;
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

        .btn:disabled,
        .mini-btn:disabled,
        .btn-wide:disabled,
        .order-type-btn:disabled,
        .quick-amount-btn:disabled,
        .shift-btn:disabled,
        .product-pick-btn:disabled,
        .modal-add-btn:disabled,
        .tab-btn:disabled,
        .qty-btn:disabled,
        .receipt-action-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-dark { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); }
        .btn-brand { background: linear-gradient(135deg, var(--brand) 0%, #f08a57 100%); }
        .btn-green { background: linear-gradient(135deg, #166534 0%, #1f7a44 100%); }
        .btn-red { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }

        .content {
            padding: 14px 28px 28px;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 22px;
            align-items: stretch;
            margin-bottom: 22px;
        }

        .hero-main {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #ffffff 0%, #fff8f4 55%, #fff1ea 100%);
            border: 1px solid #f0e1d8;
            border-radius: 28px;
            padding: 28px;
        }

        .hero-main::after {
            content: "";
            position: absolute;
            right: -60px;
            top: -60px;
            width: 220px;
            height: 220px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(232,106,58,0.14) 0%, rgba(232,106,58,0.04) 55%, rgba(232,106,58,0) 75%);
            pointer-events: none;
        }

        .hero-title {
            position: relative;
            z-index: 1;
            margin: 0 0 10px;
            font-size: 42px;
            line-height: 1.02;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: #111827;
            max-width: 700px;
        }

        .hero-subtitle {
            position: relative;
            z-index: 1;
            margin: 0;
            max-width: 640px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.8;
        }

        .hero-pills {
            position: relative;
            z-index: 1;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .hero-pill {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #f1e3da;
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
        }

        .session-card {
            background: rgba(255,255,255,0.84);
            border: 1px solid #eceff5;
            border-radius: 28px;
            padding: 22px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
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

        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .toolbar-pill {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: white;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 12px;
            font-weight: 700;
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 400px;
            gap: 22px;
            align-items: start;
            overflow: visible;
        }

        .cashier-main-column,
        .cashier-side-column {
            min-width: 0;
            overflow: visible;
        }

        .cashier-side-column {
            position: sticky;
            top: 20px;
            align-self: start;
        }

        .cashier-sticky-wrap {
            position: static;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            padding-right: 2px;
        }

        .cashier-sticky-wrap::-webkit-scrollbar {
            width: 8px;
        }

        .cashier-sticky-wrap::-webkit-scrollbar-thumb {
            background: #d7dce5;
            border-radius: 999px;
        }

        .section-card {
            background: rgba(255,255,255,0.90);
            border: 1px solid #eceff5;
            border-radius: 28px;
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .section-head {
            padding: 22px 22px 0;
        }

        .section-title {
            margin: 0 0 6px;
            font-size: 24px;
            font-weight: 800;
            color: #111827;
        }

        .section-subtitle {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
        }

        .alert {
            margin: 0 22px 18px;
            padding: 14px 16px;
            border-radius: 14px;
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

        .alert-info {
            background: #eef4ff;
            color: #1d4ed8;
            border: 1px solid #dbe5ff;
        }

        .tab-wrap {
            padding: 18px 22px 0;
        }

        .tab-nav {
            display: inline-flex;
            gap: 10px;
            background: #f8fafc;
            border: 1px solid #e8edf4;
            border-radius: 18px;
            padding: 8px;
            flex-wrap: wrap;
        }

        .tab-btn {
            min-height: 44px;
            padding: 0 18px;
            border-radius: 12px;
            border: 0;
            background: transparent;
            color: #6b7280;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.15s ease;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            color: white;
            box-shadow: 0 10px 20px rgba(15,23,42,0.14);
        }

        .tab-panel.hidden {
            display: none !important;
        }

        .shift-box {
            margin: 18px 22px 22px;
            padding: 18px;
            border-radius: 22px;
            border: 1px solid #d8f0de;
            background: linear-gradient(180deg, #f3fff7 0%, #ffffff 100%);
        }

        .shift-box.start {
            border-color: #f4ddd1;
            background: linear-gradient(180deg, #fff8f4 0%, #ffffff 100%);
        }

        .shift-title {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 6px;
        }

        .shift-subtitle {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 14px;
        }

        .shift-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .shift-stat {
            padding: 14px;
            border-radius: 16px;
            background: white;
            border: 1px solid #e5e7eb;
            min-height: 110px;
        }

        .shift-stat-label {
            font-size: 11px;
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .shift-stat-value {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            line-height: 1.4;
            word-break: break-word;
        }

        .shift-form {
            display: grid;
            gap: 12px;
        }

        .shift-field label,
        .field label {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #374151;
            margin-bottom: 8px;
        }

        .shift-field input,
        .shift-field textarea,
        .field input,
        .field select {
            width: 100%;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
            padding: 14px 16px;
            font-size: 15px;
            outline: none;
            font-family: Arial, sans-serif;
        }

        .shift-field input,
        .field input,
        .field select {
            min-height: 52px;
        }

        .shift-field textarea {
            min-height: 88px;
            resize: vertical;
        }

        .shift-field input:focus,
        .shift-field textarea:focus,
        .field input:focus,
        .field select:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .shift-actions,
        .checkout-success-actions,
        .member-actions,
        .receipt-history-actions,
        .cart-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .shift-btn {
            min-height: 52px;
            padding: 0 18px;
            border-radius: 14px;
            border: 0;
            cursor: pointer;
            color: white;
            font-size: 15px;
            font-weight: 800;
            box-shadow: 0 10px 20px rgba(15,23,42,0.10);
        }

        .shift-btn.start {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
        }

        .shift-btn.end {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        }

        .checkout-success-box {
            margin: 0 22px 18px;
            padding: 18px;
            border-radius: 18px;
            background: linear-gradient(180deg, #eefaf1 0%, #ffffff 100%);
            border: 1px solid #d8f0de;
        }

        .checkout-success-title {
            font-size: 18px;
            font-weight: 800;
            color: #166534;
            margin-bottom: 10px;
        }

        .checkout-success-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }

        .checkout-success-item {
            padding: 12px 14px;
            border-radius: 14px;
            background: white;
            border: 1px solid #e5efe8;
        }

        .checkout-success-label {
            font-size: 11px;
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .checkout-success-value {
            font-size: 16px;
            font-weight: 800;
            color: #111827;
            line-height: 1.5;
        }

        .search-wrap {
            padding: 18px 22px 18px;
        }

        .search-input {
            width: 100%;
            min-height: 56px;
            border: 1px solid #d7dce5;
            border-radius: 18px;
            background: white;
            padding: 0 18px;
            font-size: 16px;
            color: #111827;
            outline: none;
        }

        .search-input:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .order-type-wrap {
            padding: 0 22px 18px;
        }

        .order-type-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e7edf5;
            border-radius: 18px;
            padding: 14px;
        }

        .order-type-title {
            font-size: 13px;
            font-weight: 800;
            color: #374151;
            margin-bottom: 10px;
        }

        .order-type-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .order-type-btn {
            min-height: 54px;
            padding: 0 16px;
            border-radius: 14px;
            border: 1px solid #d7dce5;
            background: white;
            color: #374151;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.15s ease;
        }

        .order-type-btn.active {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            border-color: #111827;
            color: white;
        }

        .order-type-note {
            margin-top: 10px;
            font-size: 12px;
            color: #6b7280;
            line-height: 1.6;
        }


        .product-category-section {
            display: grid;
            gap: 14px;
            margin-bottom: 22px;
        }

        .product-category-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 16px 18px;
            border-radius: 22px;
            background: linear-gradient(135deg, #ffffff 0%, #fff8f4 100%);
            border: 1px solid #f1e3da;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.04);
        }

        .product-category-title {
            font-size: 18px;
            font-weight: 900;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .product-category-count {
            font-size: 12px;
            font-weight: 900;
            color: #c9552a;
            background: #fff1ea;
            border: 1px solid #f5d5c7;
            border-radius: 999px;
            padding: 7px 10px;
            white-space: nowrap;
        }


        .product-category-head {
            cursor: pointer;
            user-select: none;
        }

        .product-category-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid #f1e3da;
            color: #c9552a;
            font-size: 16px;
            font-weight: 900;
            line-height: 1;
        }

        .product-category-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .product-category-section.collapsed .products-grid {
            display: none;
        }

        .product-category-section.collapsed .product-category-empty {
            display: none !important;
        }

        .product-category-section.collapsed .product-category-toggle {
            transform: rotate(-90deg);
        }

        .product-category-empty {
            display: none;
            padding: 14px 16px;
            border-radius: 16px;
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fed7aa;
            font-size: 13px;
            font-weight: 800;
        }

        .products-grid {
            padding: 0 22px 22px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .product-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e7edf5;
            border-radius: 22px;
            padding: 14px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }

        .hidden { display: none !important; }

        .product-image {
            width: 100%;
            height: 92px;
            border-radius: 18px;
            background: linear-gradient(135deg, #fff8f4 0%, #fff2ea 100%);
            border: 1px solid #f4e2d8;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            opacity: 0.92;
        }

        .product-name {
            font-size: 16px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 6px;
            line-height: 1.35;
            min-height: 42px;
        }

        .product-meta {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 12px;
            line-height: 1.6;
            min-height: 38px;
        }

        .product-foot {
            margin-top: auto;
            display: grid;
            gap: 10px;
        }

        .product-variant-count {
            font-size: 12px;
            font-weight: 800;
            color: #6b7280;
        }

        .product-pick-btn {
            min-height: 44px;
            border-radius: 14px;
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            color: white;
            border: 0;
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            width: 100%;
        }

        .history-panel-wrap {
            padding: 0 22px 22px;
        }

        .history-panel-box {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e7edf5;
            border-radius: 24px;
            padding: 18px;
        }

        .receipt-history-list {
            display: grid;
            gap: 12px;
            margin-top: 12px;
        }

        .receipt-history-item {
            border: 1px solid #e8edf4;
            border-radius: 16px;
            padding: 14px;
            background: #ffffff;
        }

        .receipt-history-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 8px;
        }

        .receipt-history-number {
            font-size: 15px;
            font-weight: 800;
            color: #111827;
            line-height: 1.4;
        }

        .receipt-history-time {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5;
            margin-top: 2px;
        }

        .receipt-history-total {
            font-size: 16px;
            font-weight: 800;
            color: #166534;
            text-align: right;
            white-space: nowrap;
        }

        .receipt-history-meta {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
            margin-bottom: 10px;
        }

        .receipt-history-items {
            font-size: 12px;
            color: #4b5563;
            line-height: 1.7;
            margin-bottom: 12px;
        }

        .receipt-history-empty {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
            margin-top: 12px;
        }

        .receipt-action-btn {
            min-height: 40px;
            padding: 0 14px;
            border-radius: 12px;
            border: 0;
            cursor: pointer;
            color: white;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .receipt-action-btn.green {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
        }

        .receipt-action-btn.dark {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .receipt-action-btn.red {
            background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
        }

        .cart-panel {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .cart-box {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e7edf5;
            border-radius: 22px;
            padding: 18px;
        }

        .cart-title {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 10px;
        }

        .cart-empty {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
        }

        .cart-item {
            padding: 14px 0;
            border-bottom: 1px solid #edf1f6;
        }

        .cart-item:last-child {
            border-bottom: 0;
        }

        .cart-item-name {
            font-size: 15px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 6px;
        }

        .cart-item-meta {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .cart-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .qty-control {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 6px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #e8edf4;
        }

        .qty-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            border: 0;
            cursor: pointer;
            font-size: 22px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            color: white;
        }

        .qty-btn.minus {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        }

        .qty-btn.plus {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
        }

        .qty-pill {
            min-width: 52px;
            min-height: 42px;
            border-radius: 12px;
            background: white;
            border: 1px solid #e5e7eb;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
            color: #111827;
            padding: 0 10px;
        }

        .modifier-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .mini-btn {
            min-height: 40px;
            padding: 0 14px;
            border-radius: 12px;
            border: 0;
            cursor: pointer;
            color: white;
            font-size: 13px;
            font-weight: 800;
        }

        .mini-btn-dark { background: #111827; }
        .mini-btn-red { background: #dc2626; }

        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid #edf1f6;
            font-size: 17px;
            font-weight: 800;
            color: #111827;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .summary-card {
            border-radius: 18px;
            padding: 16px;
            border: 1px solid #e8edf4;
        }

        .summary-card.soft-orange {
            background: linear-gradient(180deg, #fff8f4 0%, #ffffff 100%);
            border-color: #f5ddd0;
        }

        .summary-card.soft-green {
            background: linear-gradient(180deg, #f2fbf5 0%, #ffffff 100%);
            border-color: #d8f0de;
        }

        .summary-card.soft-blue {
            background: linear-gradient(180deg, #f4f8ff 0%, #ffffff 100%);
            border-color: #dbe7ff;
            grid-column: 1 / -1;
        }

        .summary-label {
            font-size: 12px;
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
        }

        .summary-value {
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 6px;
        }

        .soft-orange .summary-value { color: var(--brand-dark); }
        .soft-green .summary-value { color: var(--green); }
        .soft-blue .summary-value { color: var(--blue); }

        .summary-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.5;
        }

        .payment-section {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e7edf5;
            border-radius: 24px;
            padding: 20px;
        }

        .payment-section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
        }

        .payment-section-title {
            font-size: 20px;
            font-weight: 800;
            color: #111827;
        }

        .payment-section-subtitle {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
            margin-top: 4px;
        }

        .payment-form {
            display: grid;
            gap: 14px;
        }

        .quick-amount-wrap {
            display: grid;
            gap: 10px;
        }

        .quick-amount-label {
            font-size: 13px;
            font-weight: 800;
            color: #374151;
        }

        .quick-amount-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
        }

        .quick-amount-btn {
            min-height: 52px;
            border-radius: 14px;
            border: 1px solid #d7dce5;
            background: white;
            color: #111827;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 6px 14px rgba(15,23,42,0.04);
        }

        .quick-amount-btn.primary {
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            color: white;
            border-color: #111827;
        }

        .quick-amount-btn.soft {
            background: #fff8f4;
            border-color: #f2d9cb;
            color: var(--brand-dark);
        }

        .quick-amount-btn.neutral {
            background: #f8fafc;
            border-color: #e5e7eb;
            color: #374151;
        }

        .payment-live-box {
            border: 1px solid #e8edf4;
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
        }

        .payment-live-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            padding: 15px 16px;
            border-bottom: 1px solid #edf1f6;
        }

        .payment-live-row:last-child {
            border-bottom: 0;
        }

        .payment-live-label {
            font-size: 13px;
            font-weight: 800;
            color: #6b7280;
        }

        .payment-live-value {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            text-align: right;
        }

        .payment-live-value.change-ok { color: var(--green); }
        .payment-live-value.change-minus { color: var(--red); }

        .payment-live-row.change-highlight {
            background: linear-gradient(180deg, #f5fcf7 0%, #ffffff 100%);
        }

        .payment-live-row.change-highlight.minus {
            background: linear-gradient(180deg, #fff5f5 0%, #ffffff 100%);
        }

        .payment-helper {
            padding: 14px 16px;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.6;
            border-top: 1px solid #edf1f6;
        }

        .payment-helper.ok {
            background: #eefaf1;
            color: #166534;
        }

        .payment-helper.warn {
            background: #fff1f1;
            color: #b42318;
        }

        .checkout-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-top: 4px;
        }

        .btn-wide {
            width: 100%;
            min-height: 62px;
            border-radius: 18px;
            border: 0;
            cursor: pointer;
            color: white;
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 0.01em;
        }

        .btn-checkout {
            background: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
            box-shadow: 0 16px 28px rgba(22,101,52,0.18);
        }

        .btn-clear {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        }

        .member-box {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e7edf5;
            border-radius: 22px;
            padding: 18px;
        }

        .member-form {
            display: grid;
            gap: 10px;
            margin-top: 12px;
        }

        .member-input {
            width: 100%;
            min-height: 50px;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
            padding: 0 14px;
            font-size: 15px;
            outline: none;
        }

        .member-active {
            padding: 12px 14px;
            border-radius: 14px;
            background: #eefaf1;
            border: 1px solid #d8f0de;
            color: #166534;
            font-size: 14px;
            line-height: 1.7;
            font-weight: 700;
            margin-top: 12px;
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(4px);
            display: none;
            z-index: 2147483000;
        }

        .modal-backdrop.active {
            display: block !important;
        }

        .variant-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2147483001;
            width: min(1080px, calc(100vw - 32px));
            max-height: calc(100vh - 32px);
            overflow: hidden;
            background: #ffffff;
            border-radius: 30px;
            border: 1px solid #e8edf4;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.24);
            display: flex;
            flex-direction: column;
        }

        .variant-modal-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            padding: 22px 24px 0;
        }

        .variant-modal-title {
            margin: 0 0 6px;
            font-size: 36px;
            font-weight: 800;
            color: #111827;
            line-height: 1.05;
        }

        .variant-modal-subtitle {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
        }

        .variant-modal-close {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            border: 0;
            background: #f3f4f6;
            color: #111827;
            font-size: 24px;
            font-weight: 700;
            cursor: pointer;
            flex-shrink: 0;
        }

        .variant-modal-toolbar {
            padding: 18px 24px 0;
        }

        .variant-modal-order-chip {
            display: inline-flex;
            align-items: center;
            padding: 10px 14px;
            border-radius: 999px;
            background: #fff8f4;
            border: 1px solid #f4ddd0;
            color: var(--brand-dark);
            font-size: 13px;
            font-weight: 800;
        }

        .variant-modal-body {
            padding: 18px 24px 24px;
            overflow-y: auto;
        }

        .variant-modal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .variant-option-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e7edf5;
            border-radius: 22px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            min-height: 220px;
        }

        .variant-option-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 12px;
        }

        .variant-option-name {
            font-size: 24px;
            font-weight: 800;
            color: #111827;
            line-height: 1.15;
        }

        .variant-option-code {
            display: inline-flex;
            align-items: center;
            padding: 7px 10px;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid #e8edf4;
            color: #6b7280;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 8px;
            width: fit-content;
        }

        .variant-price-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        .variant-price-box {
            padding: 12px 14px;
            border-radius: 16px;
            border: 1px solid #e8edf4;
            background: #f8fafc;
        }

        .variant-price-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .variant-price-value {
            font-size: 20px;
            font-weight: 800;
            color: #111827;
        }

        .variant-active-price {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-top: 14px;
            border-top: 1px solid #edf1f6;
        }

        .variant-active-price-text {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
        }

        .variant-active-price-text strong {
            color: var(--brand-dark);
            font-size: 18px;
        }

        .modal-add-btn {
            min-height: 48px;
            min-width: 120px;
            padding: 0 18px;
            border-radius: 14px;
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            color: white;
            font-size: 14px;
            font-weight: 800;
            border: 0;
            cursor: pointer;
            white-space: nowrap;
        }

        @media (max-width: 1480px) {
            .products-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (max-width: 1180px) {
            .layout {
                grid-template-columns: minmax(0, 1fr) 340px;
            }

            .cashier-side-column {
                position: sticky;
                top: 16px;
            }

            .cashier-sticky-wrap {
                max-height: calc(100vh - 32px);
                overflow-y: auto;
            }

            .hero {
                grid-template-columns: 1fr;
            }

            .products-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .shift-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 980px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .layout {
                grid-template-columns: 1fr;
            }

            .cashier-side-column {
                position: static;
            }

            .cashier-sticky-wrap {
                position: static;
                max-height: none;
                overflow: visible;
            }

            .products-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .variant-modal-grid {
                grid-template-columns: 1fr;
            }

            .variant-modal-title {
                font-size: 28px;
            }

            .variant-modal {
                width: calc(100vw - 24px);
                max-height: calc(100vh - 24px);
            }
        }

        @media (max-width: 760px) {
            .page { padding: 12px; }

            .topbar,
            .content {
                padding-left: 16px;
                padding-right: 16px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .hero-title { font-size: 30px; }

            .products-grid {
                grid-template-columns: 1fr;
                padding-left: 16px;
                padding-right: 16px;
            }

            .section-head,
            .search-wrap,
            .order-type-wrap,
            .tab-wrap,
            .history-panel-wrap {
                padding-left: 16px;
                padding-right: 16px;
            }

            .summary-grid,
            .checkout-success-meta,
            .quick-amount-grid,
            .shift-grid,
            .variant-price-grid {
                grid-template-columns: 1fr;
            }

            .summary-card.soft-blue {
                grid-column: auto;
            }

            .payment-live-row,
            .variant-active-price,
            .variant-option-top,
            .cart-item-row,
            .receipt-history-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .payment-live-value,
            .receipt-history-total {
                text-align: left;
            }

            .order-type-buttons {
                grid-template-columns: 1fr;
            }

            .checkout-success-actions .btn,
            .member-actions .btn,
            .shift-actions .shift-btn,
            .receipt-history-actions .receipt-action-btn,
            .modal-add-btn {
                width: 100%;
            }

            .variant-modal {
                border-radius: 24px;
            }

            .variant-modal-head,
            .variant-modal-toolbar,
            .variant-modal-body {
                padding-left: 16px;
                padding-right: 16px;
            }
        }

        .quick-promo-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 14px;
        }

        .quick-promo-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            border: 1px solid #e8edf4;
            background: linear-gradient(180deg, #ffffff 0%, #fff8f3 100%);
            border-radius: 16px;
            padding: 12px;
            text-align: left;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.06);
        }

        .quick-promo-card:hover {
            transform: translateY(-1px);
        }

        .quick-promo-name {
            min-height: 34px;
            display: flex;
            align-items: flex-start;
            font-size: 13px;
            font-weight: 900;
            color: #111827;
            margin-bottom: 5px;
        }

        .quick-promo-meta {
            font-size: 11px;
            color: #6b7280;
            font-weight: 800;
            line-height: 1.5;
        }

        .quick-promo-empty {
            grid-column: 1 / -1;
            padding: 12px;
            border-radius: 14px;
            background: #f9fafb;
            color: #6b7280;
            font-size: 12px;
            font-weight: 800;
        }

        .promo-reward-pill {
            display: inline-flex;
            align-items: center;
            margin-top: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #e8fff1;
            color: #166534;
            font-size: 10px;
            font-weight: 900;
        }

        .promo-item-label {
            display: inline-flex;
            margin-left: 6px;
            padding: 3px 7px;
            border-radius: 999px;
            background: #fff7ed;
            color: #c2410c;
            font-size: 10px;
            font-weight: 900;
        }

        .promo-discount-box {
            border: 1px solid #e8edf4;
            background: linear-gradient(180deg, #ffffff 0%, #fffaf7 100%);
            border-radius: 22px;
            padding: 16px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            display: grid;
            gap: 14px;
        }

        .promo-discount-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }

        .promo-discount-title {
            font-size: 16px;
            font-weight: 900;
            color: #111827;
            margin-bottom: 4px;
        }

        .promo-discount-subtitle {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.6;
        }

        .promo-discount-pill {
            flex: 0 0 auto;
            padding: 7px 10px;
            border-radius: 999px;
            background: #fff1ea;
            color: #c9552a;
            border: 1px solid #f4ddd0;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .promo-discount-grid {
            display: grid;
            gap: 12px;
        }

        .promo-discount-box .field {
            margin: 0;
        }

        .promo-discount-box select {
            background-color: #ffffff;
            border-color: #e5e7eb;
            font-weight: 800;
        }

        .promo-discount-box select:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .promo-total-box {
            border-radius: 18px;
            background: #f8fafc;
            border: 1px solid #e8edf4;
            overflow: hidden;
        }

        .promo-total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-bottom: 1px solid #e8edf4;
        }

        .promo-total-row:last-child {
            border-bottom: 0;
        }

        .promo-total-label {
            font-size: 12px;
            font-weight: 900;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .promo-total-value {
            font-size: 15px;
            font-weight: 900;
            color: #111827;
            white-space: nowrap;
        }

        .promo-total-value.discount {
            color: #dc2626;
        }

        .promo-total-row.grand {
            background: #111827;
        }

        .promo-total-row.grand .promo-total-label,
        .promo-total-row.grand .promo-total-value {
            color: #ffffff;
        }


        /* Clean payment popup final */
        .open-payment-modal-btn {
            width: 100%;
            border: 0;
            border-radius: 18px;
            background: linear-gradient(180deg, #17813f 0%, #137238 100%);
            color: #ffffff;
            padding: 16px 18px;
            font-size: 16px;
            font-weight: 900;
            cursor: pointer;
            margin-top: 12px;
            box-shadow: 0 16px 34px rgba(21, 128, 61, 0.20);
        }

        .payment-section.payment-modal-panel {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 90;
            margin: 0;
            padding: 22px;
            overflow: auto;
            background: rgba(15, 23, 42, 0.52);
            border: 0;
            border-radius: 0;
            box-shadow: none;
        }

        .payment-section.payment-modal-panel.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .payment-modal-card {
            width: min(520px, 100%);
            max-height: calc(100vh - 44px);
            overflow-y: auto;
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 28px;
            box-shadow: 0 28px 90px rgba(15, 23, 42, 0.30);
        }

        .payment-modal-card .payment-section-head {
            position: sticky;
            top: 0;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border-bottom: 1px solid #edf1f6;
            padding: 20px 22px;
            border-radius: 28px 28px 0 0;
        }

        .payment-modal-card .payment-section-title {
            margin: 0;
            font-size: 21px;
            line-height: 1.15;
            font-weight: 900;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .payment-modal-subtitle {
            margin-top: 5px;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 800;
            color: #6b7280;
        }

        .payment-modal-close-btn {
            flex: 0 0 auto;
            width: 40px;
            height: 40px;
            border: 0;
            border-radius: 14px;
            background: #f3f4f6;
            color: #111827;
            font-size: 22px;
            font-weight: 900;
            cursor: pointer;
            line-height: 1;
        }

        .payment-modal-content {
            padding: 18px 22px 22px;
        }

        .payment-modal-content .field {
            margin-bottom: 14px;
        }

        .payment-modal-content .field label {
            font-size: 12px;
            font-weight: 900;
            color: #374151;
            margin-bottom: 7px;
        }

        .payment-modal-content select,
        .payment-modal-content input[type="text"] {
            min-height: 48px;
            border-radius: 15px;
            font-size: 14px;
            font-weight: 800;
        }

        .payment-modal-content .quick-amount-wrap {
            margin-top: 12px;
        }

        .payment-modal-content .quick-amount-label {
            font-size: 15px;
            font-weight: 900;
            color: #111827;
            margin-bottom: 10px;
        }

        .payment-modal-content .quick-amount-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 9px;
        }

        .payment-modal-content .quick-amount-btn {
            min-height: 48px;
            border-radius: 14px;
            font-size: 12px;
            font-weight: 900;
        }

        .payment-modal-content .payment-live-box {
            margin-top: 16px;
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid #e5eaf2;
            background: #ffffff;
        }

        .payment-modal-content .payment-live-row {
            padding: 13px 14px;
        }

        .payment-modal-content .payment-live-label {
            font-size: 12px;
        }

        .payment-modal-content .payment-live-value {
            font-size: 17px;
        }

        .payment-modal-content .payment-helper {
            padding: 12px 14px;
            font-size: 12px;
        }

        .payment-modal-content .checkout-actions {
            position: sticky;
            bottom: -22px;
            background: linear-gradient(180deg, rgba(255,255,255,0.86) 0%, #ffffff 34%);
            padding-top: 14px;
            padding-bottom: 2px;
            margin-top: 14px;
        }

        .promo-total-box {
            display: none !important;
        }

        @media (max-width: 640px) {
            .payment-section.payment-modal-panel {
                align-items: flex-end;
                padding: 0;
            }

            .payment-section.payment-modal-panel.active {
                align-items: flex-end;
            }

            .payment-modal-card {
                width: 100%;
                max-height: 92vh;
                border-radius: 28px 28px 0 0;
            }
        }


        /* Make payment popup compact like variant modal */
        .payment-modal-card {
            width: min(500px, 100%) !important;
            max-height: min(86vh, 720px) !important;
            border-radius: 26px !important;
        }

        .payment-modal-card .payment-section-head {
            padding: 16px 18px !important;
        }

        .payment-modal-card .payment-section-title {
            font-size: 20px !important;
        }

        .payment-modal-subtitle {
            font-size: 11.5px !important;
            margin-top: 4px !important;
        }

        .payment-modal-close-btn {
            width: 38px !important;
            height: 38px !important;
            border-radius: 13px !important;
        }

        .payment-modal-content {
            padding: 14px 18px 16px !important;
        }

        .payment-modal-content .field {
            margin-bottom: 10px !important;
        }

        .payment-modal-content .field label {
            font-size: 11.5px !important;
            margin-bottom: 6px !important;
        }

        .payment-modal-content select,
        .payment-modal-content input[type="text"] {
            min-height: 44px !important;
            padding: 10px 12px !important;
            border-radius: 14px !important;
            font-size: 13.5px !important;
        }

        .payment-modal-content .quick-amount-wrap {
            margin-top: 8px !important;
        }

        .payment-modal-content .quick-amount-label {
            font-size: 14px !important;
            margin-bottom: 8px !important;
        }

        .payment-modal-content .quick-amount-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 8px !important;
        }

        .payment-modal-content .quick-amount-btn {
            min-height: 42px !important;
            padding: 9px 8px !important;
            border-radius: 13px !important;
            font-size: 11.5px !important;
        }

        .payment-modal-content .payment-live-box {
            margin-top: 12px !important;
            border-radius: 16px !important;
        }

        .payment-modal-content .payment-live-row {
            padding: 10px 12px !important;
        }

        .payment-modal-content .payment-live-label {
            font-size: 11px !important;
        }

        .payment-modal-content .payment-live-value {
            font-size: 15.5px !important;
        }

        .payment-modal-content .payment-live-row.change-highlight .payment-live-value {
            font-size: 17px !important;
        }

        .payment-modal-content .checkout-actions {
            position: sticky !important;
            bottom: -16px !important;
            margin-top: 10px !important;
            padding-top: 12px !important;
            background: linear-gradient(180deg, rgba(255,255,255,0.88) 0%, #ffffff 38%) !important;
        }

        .payment-modal-content .btn-checkout {
            min-height: 48px !important;
            border-radius: 16px !important;
            font-size: 15px !important;
        }

        @media (min-height: 760px) {
            .payment-modal-card {
                overflow-y: visible !important;
            }
        }


        /* Force payment popup to open neatly in viewport */
        .payment-section.payment-modal-panel.active {
            position: fixed !important;
            inset: 0 !important;
            z-index: 9999 !important;
            display: flex !important;
            align-items: flex-start !important;
            justify-content: center !important;
            padding: 56px 18px 18px !important;
            overflow-y: auto !important;
            background: rgba(15, 23, 42, 0.52) !important;
        }

        .payment-section.payment-modal-panel .payment-modal-card {
            width: min(500px, calc(100vw - 36px)) !important;
            max-height: calc(100vh - 92px) !important;
            overflow-y: auto !important;
            margin: 0 auto !important;
            transform: none !important;
        }

        .payment-section.payment-modal-panel .payment-modal-content {
            padding-bottom: 14px !important;
        }

        .payment-section.payment-modal-panel .checkout-actions {
            position: sticky !important;
            bottom: 0 !important;
            margin-top: 10px !important;
            padding-top: 10px !important;
            background: linear-gradient(180deg, rgba(255,255,255,0.86) 0%, #ffffff 40%) !important;
        }

        .payment-section.payment-modal-panel .btn-checkout {
            min-height: 46px !important;
        }


        /* Final centered wide payment modal */
        .payment-section.payment-modal-panel.active {
            position: fixed !important;
            inset: 0 !important;
            z-index: 99999 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 24px !important;
            overflow: hidden !important;
            background: rgba(15, 23, 42, 0.56) !important;
        }

        .payment-section.payment-modal-panel .payment-modal-card {
            width: min(900px, calc(100vw - 48px)) !important;
            max-height: calc(100vh - 48px) !important;
            overflow: hidden !important;
            margin: 0 auto !important;
            border-radius: 28px !important;
            background: #ffffff !important;
            box-shadow: 0 30px 90px rgba(15, 23, 42, 0.32) !important;
        }

        .payment-section.payment-modal-panel .payment-section-head {
            padding: 18px 22px !important;
            border-bottom: 1px solid #edf1f6 !important;
        }

        .payment-section.payment-modal-panel .payment-modal-content {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) minmax(310px, 0.9fr) !important;
            gap: 18px !important;
            padding: 18px 22px 22px !important;
            max-height: calc(100vh - 150px) !important;
            overflow-y: auto !important;
        }

        .payment-section.payment-modal-panel .field {
            margin-bottom: 12px !important;
        }

        .payment-section.payment-modal-panel select,
        .payment-section.payment-modal-panel input[type="text"] {
            min-height: 48px !important;
            border-radius: 15px !important;
        }

        .payment-section.payment-modal-panel .quick-amount-wrap {
            margin-top: 10px !important;
        }

        .payment-section.payment-modal-panel .quick-amount-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            gap: 9px !important;
        }

        .payment-section.payment-modal-panel .quick-amount-btn {
            min-height: 50px !important;
            border-radius: 15px !important;
            font-size: 12px !important;
        }

        .payment-section.payment-modal-panel .payment-live-box {
            margin-top: 0 !important;
            height: fit-content !important;
            border-radius: 20px !important;
        }

        .payment-section.payment-modal-panel .payment-live-row {
            padding: 13px 14px !important;
        }

        .payment-section.payment-modal-panel .payment-live-label {
            font-size: 12px !important;
        }

        .payment-section.payment-modal-panel .payment-live-value {
            font-size: 17px !important;
        }

        .payment-section.payment-modal-panel .checkout-actions {
            position: static !important;
            margin-top: 14px !important;
            padding: 0 !important;
            background: transparent !important;
        }

        .payment-section.payment-modal-panel .btn-checkout {
            min-height: 52px !important;
            border-radius: 17px !important;
            font-size: 16px !important;
        }

        .payment-left-column,
        .payment-right-column {
            min-width: 0;
        }

        @media (max-width: 860px) {
            .payment-section.payment-modal-panel {
                align-items: flex-end !important;
                padding: 0 !important;
            }

            .payment-section.payment-modal-panel .payment-modal-card {
                width: 100% !important;
                max-height: 92vh !important;
                border-radius: 28px 28px 0 0 !important;
            }

            .payment-section.payment-modal-panel .payment-modal-content {
                grid-template-columns: 1fr !important;
                max-height: calc(92vh - 86px) !important;
                overflow-y: auto !important;
            }
        }


        /* Force payment modal exactly centered on visible screen */
        .payment-section.payment-modal-panel.active {
            position: fixed !important;
            top: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            height: 100dvh !important;
            z-index: 999999 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 20px !important;
            overflow: hidden !important;
            background: rgba(15, 23, 42, 0.56) !important;
        }

        .payment-section.payment-modal-panel.active .payment-modal-card {
            margin: 0 auto !important;
            max-height: calc(100vh - 40px) !important;
            max-height: calc(100dvh - 40px) !important;
            transform: translateY(0) !important;
        }


        /* Payment modal true viewport center */
        body.payment-modal-open {
            overflow: hidden !important;
        }

        body > .payment-section.payment-modal-panel.active {
            position: fixed !important;
            inset: 0 !important;
            width: 100vw !important;
            height: 100vh !important;
            height: 100dvh !important;
            z-index: 999999 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 24px !important;
            overflow: hidden !important;
            background: rgba(15, 23, 42, 0.56) !important;
        }

        body > .payment-section.payment-modal-panel.active .payment-modal-card {
            width: min(900px, calc(100vw - 48px)) !important;
            max-height: calc(100vh - 48px) !important;
            max-height: calc(100dvh - 48px) !important;
            margin: 0 !important;
            overflow: hidden !important;
            transform: none !important;
        }

        body > .payment-section.payment-modal-panel.active .payment-modal-content {
            max-height: calc(100vh - 150px) !important;
            max-height: calc(100dvh - 150px) !important;
            overflow-y: auto !important;
        }

    </style>
</head>
<body>
@php
    $cartItemsJson = json_encode(
        collect($cart)->values()->map(function ($item) {
            return [
                'cart_key' => $item['cart_key'] ?? '',
                'variant_id' => $item['variant_id'] ?? null,
                'product_id' => $item['product_id'] ?? null,
                'product_name' => $item['product_name'] ?? 'Product',
                'variant_name' => $item['variant_name'] ?? null,
                'order_type' => $item['order_type'] ?? 'dine_in',
                'less_sugar' => (bool) ($item['less_sugar'] ?? false),
                'less_ice' => (bool) ($item['less_ice'] ?? false),
                'qty' => (float) ($item['qty'] ?? 0),
                'price' => (float) ($item['price'] ?? 0),
                'line_total' => (float) ($item['line_total'] ?? 0),
            ];
        })->toArray(),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    );

    $memberJson = json_encode($member, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $orderTypeJson = json_encode($orderType ?? 'dine_in', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $subtotalFormattedJson = json_encode('Rp ' . number_format((float) $subtotal, 0, ',', '.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $setOrderTypeUrlJson = json_encode(route('cashier.set-order-type'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $clearCartUrlJson = json_encode(route('cashier.cart.clear'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $startShiftUrlJson = json_encode(route('cashier.shift.start'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
    $endShiftUrlJson = json_encode(route('cashier.shift.end'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    $activeShiftJson = json_encode(
        $activeShift ? [
            'id' => $activeShift->id,
            'status' => $activeShift->status,
            'started_at' => optional($activeShift->started_at)->format('Y-m-d H:i:s'),
            'ended_at' => optional($activeShift->ended_at)->format('Y-m-d H:i:s'),
            'opening_cash' => (float) $activeShift->opening_cash,
            'closing_cash_actual' => $activeShift->closing_cash_actual !== null ? (float) $activeShift->closing_cash_actual : null,
            'closing_note' => $activeShift->closing_note,
        ] : null,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    );

    $shiftSummaryJson = json_encode([
        'total_transactions' => (int) ($shiftSummary['total_transactions'] ?? 0),
        'total_sales' => (float) ($shiftSummary['total_sales'] ?? 0),
        'cash_sales' => (float) ($shiftSummary['cash_sales'] ?? 0),
        'qris_sales' => (float) ($shiftSummary['qris_sales'] ?? 0),
        'transfer_sales' => (float) ($shiftSummary['transfer_sales'] ?? 0),
        'debit_sales' => (float) ($shiftSummary['debit_sales'] ?? 0),
        'credit_sales' => (float) ($shiftSummary['credit_sales'] ?? 0),
        'void_transactions' => (int) ($shiftSummary['void_transactions'] ?? 0),
        'expected_cash' => (float) ($shiftSummary['expected_cash'] ?? 0),
        'difference' => 0,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    $oldPaymentMethod = old('payment_method', 'cash');
    $oldAmountPaid = old('amount_paid', (float) $subtotal);
    $oldDiscountId = old('discount_id');
    $oldPromoId = old('promo_id', session('cashier_quick_promo_id'));
@endphp

<div class="page">
    <div class="shell">
        <div class="topbar">
            <div class="brand">
                <div class="brand-logo">
                    <img src="{{ asset('images/atg-icon.png') }}" alt="ATG Logo">
                </div>
                <div class="brand-text">
                    <div class="brand-name">ATG POS</div>
                    <div class="brand-sub">Cashier Workspace</div>
                </div>
            </div>

            <div class="top-actions">
                <div class="mini-info">
                    {{ $user->name ?? 'Cashier User' }} • {{ $user->outlet->name ?? 'Outlet' }}
                </div>
                <a href="{{ route('dashboard') }}" class="btn btn-dark">Mode Select</a>
            </div>
        </div>

        <div class="content">
            <div class="layout">
                <div class="cashier-main-column">
                    <div class="section-card">
                        <div class="section-head">
                            <h2 class="section-title">Cashier Workspace</h2>

                        </div>

                        <div id="cashier-alert-success" class="alert alert-success {{ session('success') ? '' : 'hidden' }}">
                            {{ session('success') ?? '' }}
                        </div>

                        <div id="cashier-alert-error" class="alert alert-error {{ (session('error') || $errors->any()) ? '' : 'hidden' }}">
                            @if(session('error'))
                                {{ session('error') }}
                            @elseif($errors->any())
                                @foreach($errors->all() as $error)
                                    <div>{{ $error }}</div>
                                @endforeach
                            @endif
                        </div>

                        <div id="cashier-alert-info" class="alert alert-info hidden"></div>

                        @if(session('last_checkout'))
                            <div class="checkout-success-box">
                                <div class="checkout-success-title">Checkout berhasil disimpan.</div>

                                <div class="checkout-success-meta">
                                    <div class="checkout-success-item">
                                        <div class="checkout-success-label">Transaction Number</div>
                                        @php
                                                $rawLastCheckoutNumber = session('last_checkout.transaction_number');
                                                $displayLastCheckoutNumber = '-';

                                                if (! empty($rawLastCheckoutNumber)) {
                                                    $parts = explode('-', $rawLastCheckoutNumber);
                                                    $lastPart = end($parts);

                                                    if (is_numeric($lastPart)) {
                                                        $displayLastCheckoutNumber = 'ATG ' . str_pad((string) ((int) $lastPart), 3, '0', STR_PAD_LEFT);
                                                    } else {
                                                        $displayLastCheckoutNumber = $rawLastCheckoutNumber;
                                                    }
                                                }
                                            @endphp
                                            <div class="checkout-success-value">{{ $displayLastCheckoutNumber }}</div>
                                    </div>

                                    <div class="checkout-success-item">
                                        <div class="checkout-success-label">Waktu</div>
                                        <div class="checkout-success-value">{{ session('last_checkout.created_at') }}</div>
                                    </div>

                                    <div class="checkout-success-item">
                                        <div class="checkout-success-label">Grand Total</div>
                                        <div class="checkout-success-value">
                                            Rp {{ number_format((float) session('last_checkout.grand_total'), 0, ',', '.') }}
                                        </div>
                                    </div>

                                    <div class="checkout-success-item">
                                        <div class="checkout-success-label">Payment</div>
                                        <div class="checkout-success-value">
                                            {{ session('last_checkout.payment_method') }}
                                            @if((float) session('last_checkout.change_amount') > 0)
                                                • Change Rp {{ number_format((float) session('last_checkout.change_amount'), 0, ',', '.') }}
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="checkout-success-actions">
                                    <a
                                        href="{{ route('cashier.transactions.receipt', ['transaction' => session('last_checkout.transaction_id')]) }}"
                                        target="_blank"
                                        class="btn btn-green"
                                    >
                                        Print Receipt
                                    </a>

                                    <a href="{{ route('cashier.new-transaction') }}" class="btn btn-dark">
                                        Transaksi Baru
                                    </a>
                                </div>
                            </div>
                        @endif

                        <div class="tab-wrap">
                            <div class="tab-nav">
                                <button type="button" class="tab-btn active" data-tab-btn="transaction">Transaksi</button>
                                <button type="button" class="tab-btn" data-tab-btn="history">History Transaksi</button>
                                <button type="button" class="tab-btn" data-tab-btn="shift">Shift</button>
                            </div>
                        </div>

                        <div id="tab-panel-transaction" class="tab-panel">
                            <div class="search-wrap">
                                <input type="text" id="cashier-search-input" class="search-input" placeholder="Cari menu secara visual di daftar bawah...">
                            </div>

                            <div class="order-type-wrap">
                                <div class="order-type-card">
                                    <div class="order-type-title">Order Type</div>
                                    <div class="order-type-buttons">
                                        <button type="button" class="order-type-btn {{ ($orderType ?? 'dine_in') === 'dine_in' ? 'active' : '' }}" data-order-type-btn data-order-type="dine_in">Dine In</button>
                                        <button type="button" class="order-type-btn {{ ($orderType ?? 'dine_in') === 'delivery' ? 'active' : '' }}" data-order-type-btn data-order-type="delivery">Delivery</button>
                                    </div>

                                </div>
                            </div>

                            <div id="products-grid">
                                @forelse(($productGroups ?? collect()) as $categoryName => $categoryProducts)
                                    <div class="product-category-section {{ $loop->first ? '' : 'collapsed' }}" data-product-category-section>
                                        <div class="product-category-head" data-product-category-toggle>
                                            <div class="product-category-title">{{ $categoryName }}</div>
                                            <div class="product-category-right">
                                                <div class="product-category-count">{{ $categoryProducts->count() }} product</div>
                                                <div class="product-category-toggle">⌄</div>
                                            </div>
                                        </div>

                                        <div class="products-grid">
                                            @foreach($categoryProducts as $product)
                                                @php
                                                    $activeVariants = $product->variants->where('is_active', true)->values();
                                                @endphp

                                                <div
                                                    class="product-card"
                                                    data-product-card
                                                    data-search="{{ strtolower(trim(($product->name ?? '') . ' ' . ($product->category->name ?? '') . ' ' . ($product->brand->name ?? ''))) }}"
                                                >
                                                    <div class="product-image">
                                                        <img src="{{ asset('images/atg-icon.png') }}" alt="Product">
                                                    </div>

                                                    <div class="product-name">{{ $product->name }}</div>
                                                    <div class="product-meta">
                                                        {{ $product->category->name ?? 'Uncategorized' }}
                                                        @if($product->brand)
                                                            • {{ $product->brand->name }}
                                                        @endif
                                                    </div>

                                                    <div class="product-foot">
                                                        <div class="product-variant-count">
                                                            {{ $activeVariants->count() }} variant aktif
                                                        </div>

                                                        <button
                                                            type="button"
                                                            class="product-pick-btn"
                                                            data-open-variant-modal
                                                            data-product-name="{{ $product->name }}"
                                                            data-product-meta="{{ trim(($product->category->name ?? 'Uncategorized') . ($product->brand ? ' • ' . $product->brand->name : '')) }}"
                                                        >
                                                            Pilih Variant
                                                        </button>

                                                        <div class="hidden" data-variant-modal-source>
                                                            @foreach($activeVariants as $variant)
                                                                @php
                                                                    $dineInPrice = (float) ($variant->price_dine_in ?? $variant->price);
                                                                    $deliveryPrice = (float) ($variant->price_delivery ?? $variant->price);
                                                                @endphp

                                                                <div
                                                                    data-variant-source-item
                                                                    data-name="{{ $variant->name }}"
                                                                    data-code="{{ $variant->code }}"
                                                                    data-dine-in="{{ $dineInPrice }}"
                                                                    data-delivery="{{ $deliveryPrice }}"
                                                                    data-url="{{ route('cashier.cart.add', $variant) }}"
                                                                ></div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="product-category-empty">Tidak ada product yang cocok di kategori ini.</div>
                                    </div>
                                @empty
                                    <div class="products-grid">
                                        <div class="product-card" style="grid-column: 1 / -1;">
                                            <div class="product-name">Belum ada product aktif</div>
                                            <div class="product-meta">Product aktif akan muncul di area ini.</div>
                                        </div>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div id="tab-panel-history" class="tab-panel hidden">
                            <div class="history-panel-wrap">
                                <div class="history-panel-box">
                                    <div class="section-title" style="font-size:22px; margin-bottom:6px;">History Transaksi Cashier</div>


                                    @if($recentReceipts->count())
                                        <div class="receipt-history-list">
                                            @foreach($recentReceipts as $receipt)
                                                @php
                                                    $displayReceiptNumber = 'ATG 001';

                                                    if (! empty($receipt->transaction_number)) {
                                                        $parts = explode('-', $receipt->transaction_number);
                                                        $lastPart = end($parts);

                                                        if (is_numeric($lastPart)) {
                                                            $displayReceiptNumber = 'ATG ' . str_pad((string) ((int) $lastPart), 3, '0', STR_PAD_LEFT);
                                                        }
                                                    }
                                                @endphp

                                                <div class="receipt-history-item">
                                                    <div class="receipt-history-top">
                                                        <div>
                                                            <div class="receipt-history-number">{{ $displayReceiptNumber }}</div>
                                                            <div class="receipt-history-time">
                                                                {{ $receipt->created_at?->format('Y-m-d H:i:s') ?? '-' }}
                                                            </div>
                                                        </div>

                                                        <div class="receipt-history-total">
                                                            Rp {{ number_format((float) $receipt->grand_total, 0, ',', '.') }}
                                                        </div>
                                                    </div>

                                                    <div class="receipt-history-meta">
                                                        Payment: {{ strtoupper((string) ($receipt->payment_method ?? '-')) }}
                                                        • Status: {{ ucfirst((string) ($receipt->status ?? '-')) }}
                                                        @if($receipt->outlet)
                                                            • {{ $receipt->outlet->name }}
                                                        @endif
                                                    </div>

                                                    <div class="receipt-history-items">
                                                        @forelse($receipt->items->take(4) as $item)
                                                            <div>
                                                                {{ $item->product_name ?? '-' }}
                                                                @if($item->variant_name)
                                                                    - {{ $item->variant_name }}
                                                                @endif
                                                                x {{ number_format((float) $item->qty, 0, ',', '.') }}
                                                            </div>
                                                        @empty
                                                            <div>Tidak ada item.</div>
                                                        @endforelse

                                                        @if($receipt->items->count() > 4)
                                                            <div>+ {{ $receipt->items->count() - 4 }} item lainnya</div>
                                                        @endif
                                                    </div>

                                                    <div class="receipt-history-actions">
                                                        <form method="GET" action="{{ route('cashier.transactions.receipt', ['transaction' => $receipt->id]) }}" target="_blank" class="cashier-reprint-form" data-print-count="{{ (int) ($receipt->receipt_print_count ?? 0) }}">
                                                            <input type="hidden" name="approval_pin" value="">
                                                            <button type="submit" class="receipt-action-btn green">Print Receipt</button>
                                                        </form>

                                                        @if($receipt->status === 'completed')
                                                            <form method="POST" action="{{ route('cashier.transactions.void', $receipt) }}" class="cashier-void-form">
                                                                @csrf
                                                                <input type="hidden" name="void_reason" value="">
                                                                <input type="hidden" name="approval_pin" value="">
                                                                <button type="submit" class="receipt-action-btn red">Void</button>
                                                            </form>
                                                        @endif

                                                        <a href="{{ route('cashier.new-transaction') }}" class="receipt-action-btn dark">
                                                            Transaksi Baru
                                                        </a>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="receipt-history-empty">
                                            Belum ada histori struk untuk cashier ini.
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div id="tab-panel-shift" class="tab-panel hidden">
                            <div id="shift-start-box" class="shift-box start {{ $activeShift ? 'hidden' : '' }}">
                                <div class="shift-title">Shift belum dibuka</div>


                                <form id="start-shift-form" class="shift-form">
                                    <div class="shift-field">
                                        <label for="opening_cash">Opening Cash</label>
                                         <input type="number" id="opening_cash" name="opening_cash" min="0" step="0.01" value="0">
                                    </div>

                                     <div class="shift-actions">
                                        <button type="submit" id="start-shift-button" class="shift-btn start">Start Shift</button>
                                     </div>
                                </form>
                            </div>

                            <div id="shift-active-box" class="shift-box {{ $activeShift ? '' : 'hidden' }}">
                                <div class="shift-title">Shift aktif</div>


                                <div class="shift-grid">
                                    <div class="shift-stat">
                                        <div class="shift-stat-label">Started At</div>
                                        <div class="shift-stat-value" id="shift-started-at">{{ $activeShift?->started_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                                    </div>

                                    <div class="shift-stat">
                                        <div class="shift-stat-label">Opening Cash</div>
                                        <div class="shift-stat-value" id="shift-opening-cash">Rp {{ number_format((float) ($activeShift?->opening_cash ?? 0), 0, ',', '.') }}</div>
                                    </div>

                                    <div class="shift-stat">
                                        <div class="shift-stat-label">Cash Sales</div>
                                        <div class="shift-stat-value" id="shift-cash-sales">Rp {{ number_format((float) ($shiftSummary['cash_sales'] ?? 0), 0, ',', '.') }}</div>
                                    </div>

                                    <div class="shift-stat">
                                        <div class="shift-stat-label">Expected Cash</div>
                                        <div class="shift-stat-value" id="shift-expected-cash">Rp {{ number_format((float) ($shiftSummary['expected_cash'] ?? 0), 0, ',', '.') }}</div>
                                    </div>

                                    <div class="shift-stat">
                                        <div class="shift-stat-label">Total Transactions</div>
                                        <div class="shift-stat-value" id="shift-total-transactions">{{ (int) ($shiftSummary['total_transactions'] ?? 0) }}</div>
                                    </div>

                                    <div class="shift-stat">
                                        <div class="shift-stat-label">Total Sales</div>
                                        <div class="shift-stat-value" id="shift-total-sales">Rp {{ number_format((float) ($shiftSummary['total_sales'] ?? 0), 0, ',', '.') }}</div>
                                    </div>

                                    <div class="shift-stat">
                                        <div class="shift-stat-label">Void Transactions</div>
                                        <div class="shift-stat-value" id="shift-void-transactions">{{ (int) ($shiftSummary['void_transactions'] ?? 0) }}</div>
                                    </div>

                                    <div class="shift-stat">
                                        <div class="shift-stat-label">Order Type</div>
                                        <div class="shift-stat-value" id="shift-order-type-preview">{{ strtoupper(str_replace('_', ' ', $orderType ?? 'dine_in')) }}</div>
                                    </div>
                                </div>

                               <form id="end-shift-form" class="shift-form">
                                    <div class="shift-field">
                                        <label for="closing_cash_actual_display">Closing Cash Actual</label>
                                        <input
                                            type="text"
                                            id="closing_cash_actual_display"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            value="Rp {{ number_format((float) ($shiftSummary['expected_cash'] ?? 0), 0, ',', '.') }}"
                                        >
                                        <input
                                            type="hidden"
                                            id="closing_cash_actual"
                                            name="closing_cash_actual"
                                            value="{{ number_format((float) ($shiftSummary['expected_cash'] ?? 0), 2, '.', '') }}"
                                        >
                                    </div>

                                     <div class="shift-field">
                                        <label for="closing_note">Closing Note</label>
                                        <textarea id="closing_note" name="closing_note" placeholder="Catatan shift penutup (opsional)"></textarea>
                                    </div>

                                    <div class="shift-actions">
                                        @if($activeShift)
                                            <a href="{{ route('cashier.shift.print', $activeShift) }}" target="_blank" class="btn btn-dark">
                                                Print Shift
                                            </a>
                                        @endif

                                        <button type="submit" id="end-shift-button" class="shift-btn end">End Shift</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cashier-side-column">
                    <div class="cashier-sticky-wrap" id="cashier-sticky-wrap">
                        <div class="section-card">
                            <div class="cart-panel">
                                <div class="summary-grid">
                                    <div class="summary-card soft-orange">
                                        <div class="summary-label">Total Items</div>
                                        <div class="summary-value" id="summary-cart-count">{{ count($cart) }}</div>
                                        <div class="summary-desc">Jumlah baris item aktif di cart.</div>
                                    </div>

                                    <div class="summary-card soft-green">
                                        <div class="summary-label">Subtotal</div>
                                        <div class="summary-value" id="summary-subtotal">Rp {{ number_format((float) $subtotal, 0, ',', '.') }}</div>
                                        <div class="summary-desc">Nilai transaksi sementara.</div>
                                    </div>

                                    <div class="summary-card soft-blue">
                                        <div class="summary-label">Member</div>
                                        <div class="summary-value" id="summary-member-name" style="font-size:20px;">
                                            {{ $member['name'] ?? $member['phone'] ?? 'Belum ada member' }}
                                        </div>
                                        <div class="summary-desc">Attach member opsional untuk transaksi ini.</div>
                                    </div>
                                </div>

                                <div class="cart-box">
                                    <div class="cart-title">Current Cart</div>
                                    <div id="cart-items-container">
                                        @forelse($cart as $cartKey => $item)
                                            <div class="cart-item">
                                                <div class="cart-item-name">
                                                    {{ $item['product_name'] ?? 'Product' }}
                                                    @if(!empty($item['variant_name']))
                                                        - {{ $item['variant_name'] }}
                                                    @endif
                                                </div>

                                                <div class="cart-item-meta">
                                                    Type: {{ strtoupper(str_replace('_', ' ', $item['order_type'] ?? 'dine_in')) }}
                                                    • Price: Rp {{ number_format((float) ($item['price'] ?? 0), 0, ',', '.') }}
                                                    • Line Total: Rp {{ number_format((float) ($item['line_total'] ?? 0), 0, ',', '.') }}

                                                    @if(!empty($item['is_promo_reward']))
                                                        <span class="promo-item-label">PROMO FREE ITEM</span>
                                                    @endif

                                                    @if(!empty($item['less_sugar']) || !empty($item['less_ice']))
                                                        <br>
                                                        Modifier:
                                                        @if(!empty($item['less_sugar']))
                                                            Less Sugar
                                                        @endif
                                                        @if(!empty($item['less_sugar']) && !empty($item['less_ice']))
                                                            •
                                                        @endif
                                                        @if(!empty($item['less_ice']))
                                                            Less Ice
                                                        @endif
                                                    @endif
                                                </div>

                                                <div class="cart-item-row">
                                                    <div class="qty-control">
                                                        <button type="button" class="qty-btn minus" data-cart-action="decrease" data-url="{{ route('cashier.cart.decrease', $cartKey) }}">−</button>
                                                        <div class="qty-pill">{{ number_format((float) ($item['qty'] ?? 0), 0, ',', '.') }}</div>
                                                        <button type="button" class="qty-btn plus" data-cart-action="increase" data-url="{{ route('cashier.cart.increase', $cartKey) }}">+</button>
                                                    </div>

                                                    <div class="modifier-buttons">
                                                        <button type="button" class="mini-btn mini-btn-dark" data-cart-modifier="less_sugar" data-url="{{ route('cashier.cart.toggle-modifier', $cartKey) }}">
                                                            {{ !empty($item['less_sugar']) ? '✓ Less Sugar' : 'Less Sugar' }}
                                                        </button>
                                                        <button type="button" class="mini-btn mini-btn-dark" data-cart-modifier="less_ice" data-url="{{ route('cashier.cart.toggle-modifier', $cartKey) }}">
                                                            {{ !empty($item['less_ice']) ? '✓ Less Ice' : 'Less Ice' }}
                                                        </button>
                                                        <button type="button" class="mini-btn mini-btn-red" data-cart-action="remove" data-url="{{ route('cashier.cart.remove', $cartKey) }}">Hapus</button>
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="cart-empty" id="cart-empty-state">
                                                Cart masih kosong. Tambahkan menu dari panel kiri untuk mulai transaksi.
                                            </div>
                                        @endforelse
                                    </div>

                                    <div class="cart-total">
                                        <span>Subtotal</span>
                                        <span id="cart-subtotal-bottom">Rp {{ number_format((float) $subtotal, 0, ',', '.') }}</span>
                                    </div>
                                </div>


                                <form method="POST" action="{{ route('cashier.checkout') }}" class="payment-form" id="checkout-form">
@csrf

                                        <input type="hidden" name="order_type" id="checkout-order-type" value="{{ $orderType ?? 'dine_in' }}">
                                        <input type="hidden" name="amount_paid" id="amount_paid_numeric" value="{{ (float) $oldAmountPaid }}">


                                        <div class="promo-discount-box">
                                            <div class="promo-discount-head">
                                                <div>
                                                    <div class="promo-discount-title">Discount & Promo</div>

                                                </div>
                                                <div class="promo-discount-pill">Optional</div>
                                            </div>


                                            <div class="quick-promo-grid">
                                                @forelse($promoOptions ?? [] as $promo)
                                                    @php
                                                        $requirementText = $promo->requirements->map(function ($requirement) {
                                                            return number_format((float) $requirement->qty, 0, ',', '.') . 'x ' . ($requirement->variant?->product?->name ?? 'Product') . ' - ' . ($requirement->variant?->name ?? 'Variant');
                                                        })->implode(' + ');

                                                        $freeRewardCount = $promo->rewards->where('reward_type', 'free_item')->count();
                                                    @endphp
                                                    <button
                                                        type="button"
                                                        class="quick-promo-card"
                                                        data-quick-promo
                                                        data-url="{{ route('cashier.promo.apply', $promo) }}"
                                                        data-promo-id="{{ $promo->id }}"
                                                    >
                                                        <div class="quick-promo-name">{{ $promo->name }}</div>
                                                        <div class="quick-promo-meta">
                                                            {{ $requirementText ?: 'Promo aktif' }}
                                                            @if(($promo->requirement_logic ?? 'and') === 'or')
                                                                <br>Logic: OR
                                                            @else
                                                                <br>Logic: AND
                                                            @endif
                                                        </div>
                                                        @if($freeRewardCount > 0)
                                                            <span class="promo-reward-pill">Free item otomatis</span>
                                                        @endif
                                                    </button>
                                                @empty
                                                    <div class="quick-promo-empty">Belum ada promo aktif untuk outlet ini.</div>
                                                @endforelse
                                            </div>

                                            <div class="promo-discount-grid">
                                                <div class="field">
                                                    <label for="discount_id">Discount</label>
                                                    <select name="discount_id" id="discount_id">
                                                        <option value="" data-type="" data-value="0">Tidak pakai discount</option>
                                                        @foreach($discountOptions ?? [] as $discount)
                                                            <option
                                                                value="{{ $discount->id }}"
                                                                data-type="{{ $discount->type }}"
                                                                data-value="{{ (float) $discount->value }}"
                                                                @selected((string) $oldDiscountId === (string) $discount->id)
                                                            >
                                                                {{ $discount->name }}
                                                                @if($discount->type === 'percent')
                                                                    - {{ number_format((float) $discount->value, 0, ',', '.') }}%
                                                                @else
                                                                    - Rp {{ number_format((float) $discount->value, 0, ',', '.') }}
                                                                @endif
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="field">
                                                    <label for="promo_id">Promo</label>
                                                    <select name="promo_id" id="promo_id">
                                                        <option value="" data-promo='{}'>Tidak pakai promo</option>
                                                        @foreach($promoOptions ?? [] as $promo)
                                                            @php
                                                                $requirementsPayload = $promo->requirements->map(function ($requirement) {
                                                                    return [
                                                                        'variant_id' => (int) $requirement->product_variant_id,
                                                                        'qty' => (float) $requirement->qty,
                                                                    ];
                                                                })->values();

                                                                $rewardsPayload = $promo->rewards->map(function ($reward) {
                                                                    return [
                                                                        'type' => $reward->reward_type,
                                                                        'value' => (float) $reward->reward_value,
                                                                        'variant_id' => $reward->product_variant_id ? (int) $reward->product_variant_id : null,
                                                                        'qty' => (float) $reward->qty,
                                                                    ];
                                                                })->values();
                                                            @endphp
                                                            <option
                                                                value="{{ $promo->id }}"
                                                                data-promo='@json(["requirements" => $requirementsPayload, "rewards" => $rewardsPayload, "logic" => $promo->requirement_logic ?? "and"])'
                                                                @selected((string) $oldPromoId === (string) $promo->id)
                                                            >
                                                                {{ $promo->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="promo-total-box">
                                                <div class="promo-total-row">
                                                    <div class="promo-total-label">Potongan</div>
                                                    <div class="promo-total-value discount" id="cart-discount-bottom">Rp 0</div>
                                                </div>

                                                <div class="promo-total-row grand">
                                                    <div class="promo-total-label">Grand Total</div>
                                                    <div class="promo-total-value" id="cart-grand-total-bottom">Rp {{ number_format((float) $subtotal, 0, ',', '.') }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        
                                    <button type="button" class="open-payment-modal-btn" id="open-payment-modal-btn">Bayar</button>

                                    <div class="payment-section payment-modal-panel" id="payment-modal-panel">
                                        <div class="payment-modal-card">
                                            <div class="payment-section-head">
                                                <div>
                                                    <div class="payment-section-title">Pembayaran</div>
                                                    <div class="payment-modal-subtitle">Pilih metode, isi nominal bayar, lalu checkout.</div>
                                                </div>
                                                <button type="button" class="payment-modal-close-btn" id="close-payment-modal-btn">×</button>
                                            </div>

                                            <div class="payment-modal-content">
<div class="field">
                                            <label for="payment_method">Payment Method</label>
                                            <select name="payment_method" id="payment_method" required>
                                                <option value="cash" {{ $oldPaymentMethod === 'cash' ? 'selected' : '' }}>Cash</option>
                                                <option value="qris" {{ $oldPaymentMethod === 'qris' ? 'selected' : '' }}>QRIS</option>
                                                <option value="transfer" {{ $oldPaymentMethod === 'transfer' ? 'selected' : '' }}>Transfer</option>
                                                <option value="debit" {{ $oldPaymentMethod === 'debit' ? 'selected' : '' }}>Debit</option>
                                                <option value="credit" {{ $oldPaymentMethod === 'credit' ? 'selected' : '' }}>Credit</option>
                                            </select>
                                        </div>

                                        <div class="field">
                                            <label for="amount_paid_display">Amount Paid</label>
                                            <input
                                                type="text"
                                                id="amount_paid_display"
                                                inputmode="numeric"
                                                autocomplete="off"
                                                value="Rp {{ number_format((float) $oldAmountPaid, 0, ',', '.') }}"
                                                required
                                            >
                                        </div>

                                        <div class="quick-amount-wrap">
                                            <div class="quick-amount-label">Quick Amount</div>
                                            <div class="quick-amount-grid">
                                                <button type="button" class="quick-amount-btn primary" data-quick-amount="exact">Uang Pas</button>
                                                <button type="button" class="quick-amount-btn soft" data-quick-amount="5000">+5.000</button>
                                                <button type="button" class="quick-amount-btn soft" data-quick-amount="10000">+10.000</button>
                                                <button type="button" class="quick-amount-btn soft" data-quick-amount="20000">+20.000</button>
                                                <button type="button" class="quick-amount-btn soft" data-quick-amount="50000">+50.000</button>
                                                <button type="button" class="quick-amount-btn soft" data-quick-amount="100000">+100.000</button>
                                                <button type="button" class="quick-amount-btn neutral" data-quick-amount="round_5000">Bulat 5rb</button>
                                                <button type="button" class="quick-amount-btn neutral" data-quick-amount="round_10000">Bulat 10rb</button>
                                                <button type="button" class="quick-amount-btn neutral" data-quick-amount="reset">Reset</button>
                                            </div>
                                        </div>

                                        <div class="payment-live-box">
                                            <div class="payment-live-row">
                                                <div class="payment-live-label">Subtotal Transaksi</div>
                                                <div class="payment-live-value" id="live-subtotal-value">Rp {{ number_format((float) $subtotal, 0, ',', '.') }}</div>
                                            </div>

                                            <div class="payment-live-row">
                                                <div class="payment-live-label">Discount / Promo</div>
                                                <div class="payment-live-value" id="live-discount-value">Rp 0</div>
                                            </div>

                                            <div class="payment-live-row">
                                                <div class="payment-live-label">Total Setelah Discount</div>
                                                <div class="payment-live-value" id="live-grand-total-value">Rp {{ number_format((float) $subtotal, 0, ',', '.') }}</div>
                                            </div>

                                            <div class="payment-live-row">
                                                <div class="payment-live-label">Amount Paid</div>
                                                <div class="payment-live-value" id="live-paid-value">Rp {{ number_format((float) $oldAmountPaid, 0, ',', '.') }}</div>
                                            </div>

                                            <div class="payment-live-row change-highlight" id="change-highlight-row">
                                                <div class="payment-live-label">Kembalian / Selisih</div>
                                                <div class="payment-live-value" id="live-change-value">Rp 0</div>
                                            </div>


                                        </div>

                                        <div class="checkout-actions">
                                            <button type="submit" class="btn-wide btn-checkout" id="checkout-button">Checkout</button>
                                        </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>

                                <div class="checkout-actions" style="margin-top:12px;">
                                    <button type="button" id="clear-cart-button" class="btn-wide btn-clear">Clear Cart</button>
                                </div>

                                <div class="member-box">
                                    <div class="cart-title">Member Access</div>

                                    @if($member)
                                        <div class="member-active">
                                            Member aktif:
                                            <strong>{{ $member['name'] ?? '-' }}</strong>
                                            @if(!empty($member['phone']))
                                                • {{ $member['phone'] }}
                                            @endif
                                            @if(isset($member['points']))
                                                <br>Poin: {{ $member['points'] }}
                                            @endif
                                        </div>

                                        <div class="member-actions">
                                            <form method="POST" action="{{ route('cashier.member.detach') }}">
                                                @csrf
                                                <button type="submit" class="btn btn-dark">Lepas Member</button>
                                            </form>
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('cashier.member.attach') }}" class="member-form">
                                            @csrf
                                            <input type="text" name="phone" class="member-input" placeholder="Nomor HP member">
                                            <div class="member-actions">
                                                <button type="submit" class="btn btn-dark">Attach Member</button>
                                            </div>
                                        </form>

                                        <form method="POST" action="{{ route('cashier.member.quick-register') }}" class="member-form">
                                            @csrf
                                            <input type="text" name="name" class="member-input" placeholder="Nama member baru">
                                            <input type="text" name="phone" class="member-input" placeholder="Nomor HP member baru">
                                            <div class="member-actions">
                                                <button type="submit" class="btn btn-brand">Quick Register</button>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal-backdrop" id="variant-modal-backdrop" aria-hidden="true">
    <div class="variant-modal" role="dialog" aria-modal="true" aria-labelledby="variant-modal-title">
        <div class="variant-modal-head">
            <div>
                <h3 class="variant-modal-title" id="variant-modal-title">Pilih Variant</h3>
                <div class="variant-modal-subtitle" id="variant-modal-subtitle">Pilih variant menu</div>

            </div>
            <button type="button" class="variant-modal-close" id="variant-modal-close">&times;</button>
        </div>

        <div class="variant-modal-toolbar">
            <div class="variant-modal-order-chip">
                Order type aktif: <span id="variant-modal-order-type" style="margin-left:6px;">DINE IN</span>
            </div>
        </div>

        <div class="variant-modal-body">
            <div class="variant-modal-grid" id="variant-modal-grid"></div>
        </div>
    </div>
</div>

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const setOrderTypeUrl = {!! $setOrderTypeUrlJson !!};
    const clearCartUrl = {!! $clearCartUrlJson !!};
    const startShiftUrl = {!! $startShiftUrlJson !!};
    const endShiftUrl = {!! $endShiftUrlJson !!};

    let cashierState = {
        orderType: {!! $orderTypeJson !!},
        activeShift: {!! $activeShiftJson !!},
        shiftSummary: {!! $shiftSummaryJson !!},
        cart: {
            order_type: {!! $orderTypeJson !!},
            cart_count: {{ count($cart) }},
            subtotal: {{ (float) $subtotal }},
            subtotal_formatted: {!! $subtotalFormattedJson !!},
            member: {!! $memberJson !!},
            items: {!! $cartItemsJson !!}
        }
    };

    const successAlert = document.getElementById('cashier-alert-success');
    const errorAlert = document.getElementById('cashier-alert-error');
    const infoAlert = document.getElementById('cashier-alert-info');
    const sessionOrderTypeText = document.getElementById('session-order-type-text');
    const checkoutOrderType = document.getElementById('checkout-order-type');
    const summaryCartCount = document.getElementById('summary-cart-count');
    const summarySubtotal = document.getElementById('summary-subtotal');
    const summaryMemberName = document.getElementById('summary-member-name');
    const cartItemsContainer = document.getElementById('cart-items-container');
    const cartSubtotalBottom = document.getElementById('cart-subtotal-bottom');
    const paymentMethod = document.getElementById('payment_method');
    const amountPaidDisplay = document.getElementById('amount_paid_display');
    const amountPaidNumeric = document.getElementById('amount_paid_numeric');
    const checkoutForm = document.getElementById('checkout-form');
    const searchInput = document.getElementById('cashier-search-input');
    const clearCartButton = document.getElementById('clear-cart-button');
    const liveSubtotalValue = document.getElementById('live-subtotal-value');
    const liveDiscountValue = document.getElementById('live-discount-value');
    const liveGrandTotalValue = document.getElementById('live-grand-total-value');
    const livePaidValue = document.getElementById('live-paid-value');
    const liveChangeValue = document.getElementById('live-change-value');
    const cartDiscountBottom = document.getElementById('cart-discount-bottom');
    const cartGrandTotalBottom = document.getElementById('cart-grand-total-bottom');
    const discountSelect = document.getElementById('discount_id');
    const promoSelect = document.getElementById('promo_id');
    const paymentHelperText = document.getElementById('payment-helper-text');
    const checkoutButton = document.getElementById('checkout-button');
    const changeHighlightRow = document.getElementById('change-highlight-row');

    const shiftStartBox = document.getElementById('shift-start-box');
    const shiftActiveBox = document.getElementById('shift-active-box');
    const startShiftForm = document.getElementById('start-shift-form');
    const endShiftForm = document.getElementById('end-shift-form');
    const openingCashInput = document.getElementById('opening_cash');
    const closingCashActualInput = document.getElementById('closing_cash_actual');
    const closingCashActualDisplay = document.getElementById('closing_cash_actual_display');
    const closingNoteInput = document.getElementById('closing_note');
    const startShiftButton = document.getElementById('start-shift-button');
    const endShiftButton = document.getElementById('end-shift-button');
    const shiftStartedAt = document.getElementById('shift-started-at');
    const shiftOpeningCash = document.getElementById('shift-opening-cash');
    const shiftCashSales = document.getElementById('shift-cash-sales');
    const shiftExpectedCash = document.getElementById('shift-expected-cash');
    const shiftTotalTransactions = document.getElementById('shift-total-transactions');
    const shiftTotalSales = document.getElementById('shift-total-sales');
    const shiftVoidTransactions = document.getElementById('shift-void-transactions');
    const shiftOrderTypePreview = document.getElementById('shift-order-type-preview');

    const variantModalBackdrop = document.getElementById('variant-modal-backdrop');
    const variantModalClose = document.getElementById('variant-modal-close');
    const variantModalTitle = document.getElementById('variant-modal-title');
    const variantModalSubtitle = document.getElementById('variant-modal-subtitle');
    const variantModalOrderType = document.getElementById('variant-modal-order-type');
    const variantModalGrid = document.getElementById('variant-modal-grid');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatCurrency(value) {
        const number = Number(value || 0);
        return 'Rp ' + new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(number);
    }

    function formatOrderType(value) {
        return String(value || 'dine_in').replace('_', ' ').toUpperCase();
    }

    function showAlert(type, message) {
        successAlert.classList.add('hidden');
        errorAlert.classList.add('hidden');
        infoAlert.classList.add('hidden');

        if (!message) return;

        if (type === 'success') {
            successAlert.textContent = message;
            successAlert.classList.remove('hidden');
            return;
        }

        if (type === 'error') {
            errorAlert.textContent = message;
            errorAlert.classList.remove('hidden');
            return;
        }

        infoAlert.textContent = message;
        infoAlert.classList.remove('hidden');
    }

    function isShiftOpen() {
        return !!cashierState.activeShift && cashierState.activeShift.status === 'open';
    }

    function parseRupiahInput(value) {
        const raw = String(value || '').replace(/[^\d]/g, '');
        return raw ? Number(raw) : 0;
    }

    function setAmountPaidValue(value) {
        const clean = Math.max(0, Number(value || 0));

        if (amountPaidNumeric) {
            amountPaidNumeric.value = clean.toFixed(2);
        }

        if (amountPaidDisplay) {
            amountPaidDisplay.value = formatCurrency(clean);
        }
    }

    function getAmountPaidValue() {
        return Number(amountPaidNumeric?.value || 0);
    }

    function setClosingCashActualValue(value) {
        const clean = Math.max(0, Number(value || 0));

        if (closingCashActualInput) {
            closingCashActualInput.value = clean.toFixed(2);
        }

        if (closingCashActualDisplay) {
            closingCashActualDisplay.value = formatCurrency(clean);
        }
    }

    function getClosingCashActualValue() {
        return Number(closingCashActualInput?.value || 0);
    }

    function updateShiftUI() {
        const open = isShiftOpen();

        shiftStartBox.classList.toggle('hidden', open);
        shiftActiveBox.classList.toggle('hidden', !open);

        if (open) {
            shiftStartedAt.textContent = cashierState.activeShift.started_at || '-';
            shiftOpeningCash.textContent = formatCurrency(cashierState.activeShift.opening_cash || 0);
            shiftCashSales.textContent = formatCurrency(cashierState.shiftSummary.cash_sales || 0);
            shiftExpectedCash.textContent = formatCurrency(cashierState.shiftSummary.expected_cash || 0);
            shiftTotalTransactions.textContent = String(cashierState.shiftSummary.total_transactions || 0);
            shiftTotalSales.textContent = formatCurrency(cashierState.shiftSummary.total_sales || 0);

            if (shiftVoidTransactions) {
                shiftVoidTransactions.textContent = String(cashierState.shiftSummary.void_transactions || 0);
            }

            if (shiftOrderTypePreview) {
                shiftOrderTypePreview.textContent = formatOrderType(cashierState.orderType);
            }

            setClosingCashActualValue(Number(cashierState.shiftSummary.expected_cash || 0));
        }
    }

    function updateOrderTypeButtons() {
        document.querySelectorAll('[data-order-type-btn]').forEach((button) => {
            button.classList.toggle('active', button.dataset.orderType === cashierState.orderType);
        });

        if (variantModalOrderType) {
            variantModalOrderType.textContent = formatOrderType(cashierState.orderType);
        }

        if (shiftOrderTypePreview) {
            shiftOrderTypePreview.textContent = formatOrderType(cashierState.orderType);
        }
    }

    function renderCartItems(items) {
        if (!items.length) {
            cartItemsContainer.innerHTML = `
                <div class="cart-empty" id="cart-empty-state">
                    Cart masih kosong. Tambahkan menu dari panel kiri untuk mulai transaksi.
                </div>
            `;
            return;
        }

        cartItemsContainer.innerHTML = items.map((item) => {
            const modifiers = [];
            if (item.less_sugar) modifiers.push('Less Sugar');
            if (item.less_ice) modifiers.push('Less Ice');

            return `
                <div class="cart-item">
                    <div class="cart-item-name">
                        ${escapeHtml(item.product_name || 'Product')}
                        ${item.variant_name ? ' - ' + escapeHtml(item.variant_name) : ''}
                    </div>

                    <div class="cart-item-meta">
                        Type: ${escapeHtml(formatOrderType(item.order_type))}
                        • Price: ${escapeHtml(formatCurrency(item.price))}
                        • Line Total: ${escapeHtml(formatCurrency(item.line_total))}
                        ${modifiers.length ? `<br>Modifier: ${escapeHtml(modifiers.join(' • '))}` : ''}
                    </div>

                    <div class="cart-item-row">
                        <div class="qty-control">
                            <button type="button" class="qty-btn minus" data-cart-action="decrease" data-url="/cashier/cart/decrease/${encodeURIComponent(item.cart_key)}">−</button>
                            <div class="qty-pill">${escapeHtml(item.qty)}</div>
                            <button type="button" class="qty-btn plus" data-cart-action="increase" data-url="/cashier/cart/increase/${encodeURIComponent(item.cart_key)}">+</button>
                        </div>

                        <div class="modifier-buttons">
                            <button type="button" class="mini-btn mini-btn-dark" data-cart-modifier="less_sugar" data-url="/cashier/cart/toggle-modifier/${encodeURIComponent(item.cart_key)}">${item.less_sugar ? '✓ Less Sugar' : 'Less Sugar'}</button>
                            <button type="button" class="mini-btn mini-btn-dark" data-cart-modifier="less_ice" data-url="/cashier/cart/toggle-modifier/${encodeURIComponent(item.cart_key)}">${item.less_ice ? '✓ Less Ice' : 'Less Ice'}</button>
                            <button type="button" class="mini-btn mini-btn-red" data-cart-action="remove" data-url="/cashier/cart/remove/${encodeURIComponent(item.cart_key)}">Hapus</button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }


    function getCartItemsForPreview() {
        const rawItems = cashierState.cart.items || [];

        if (Array.isArray(rawItems)) {
            return rawItems;
        }

        if (rawItems && typeof rawItems === 'object') {
            return Object.values(rawItems);
        }

        return [];
    }

    function getCartQtyByVariantId(variantId) {
        const id = Number(variantId || 0);

        return getCartItemsForPreview().reduce((total, item) => {
            const itemVariantId = Number(item.variant_id || item.product_variant_id || 0);

            if (itemVariantId !== id) {
                return total;
            }

            return total + Number(item.qty || 0);
        }, 0);
    }

    function parsePromoPayload() {
        if (!promoSelect || !promoSelect.selectedOptions || !promoSelect.selectedOptions.length) {
            return null;
        }

        const raw = promoSelect.selectedOptions[0].dataset.promo || '{}';

        try {
            return JSON.parse(raw);
        } catch (error) {
            return null;
        }
    }

    function promoRequirementMatched(promoPayload) {
        const requirements = Array.isArray(promoPayload?.requirements) ? promoPayload.requirements : [];

        if (!requirements.length) {
            return true;
        }

        const logic = String(promoPayload?.logic || 'and').toLowerCase();

        const matches = requirements.map((requirement) => {
            const neededQty = Number(requirement.qty || 1);
            const cartQty = getCartQtyByVariantId(requirement.variant_id);

            return cartQty >= neededQty;
        });

        return logic === 'or'
            ? matches.some(Boolean)
            : matches.every(Boolean);
    }


    function getPromoEligibleSubtotal(promoPayload) {
        const requirements = Array.isArray(promoPayload?.requirements) ? promoPayload.requirements : [];

        if (!requirements.length) {
            return 0;
        }

        const logic = String(promoPayload?.logic || 'and').toLowerCase();

        const eligibleVariantIds = requirements
            .filter((requirement) => {
                if (logic !== 'or') {
                    return true;
                }

                const neededQty = Number(requirement.qty || 1);
                const cartQty = getCartQtyByVariantId(requirement.variant_id);

                return cartQty >= neededQty;
            })
            .map((requirement) => Number(requirement.variant_id || 0))
            .filter(Boolean);

        if (!eligibleVariantIds.length) {
            return 0;
        }

        return Object.values(cashierState.cart.items || {})
            .filter((item) => {
                return eligibleVariantIds.includes(Number(item.variant_id || 0))
                    && !item.is_promo_reward;
            })
            .reduce((total, item) => total + Number(item.line_total || 0), 0);
    }

    function calculateDiscountPreview(subtotalValue) {
        let discountValue = 0;

        if (discountSelect && discountSelect.selectedOptions && discountSelect.selectedOptions.length) {
            const selected = discountSelect.selectedOptions[0];
            const type = selected.dataset.type || '';
            const value = Number(selected.dataset.value || 0);

            if (type === 'percent') {
                discountValue += subtotalValue * (value / 100);
            } else if (type) {
                discountValue += value;
            }
        }

        const promoPayload = parsePromoPayload();

        if (promoPayload && promoRequirementMatched(promoPayload)) {
            const rewards = Array.isArray(promoPayload.rewards) ? promoPayload.rewards : [];
            const promoEligibleSubtotal = getPromoEligibleSubtotal(promoPayload);
            let promoDiscountValue = 0;

            rewards.forEach((reward) => {
                const type = reward.type || '';
                const value = Number(reward.value || 0);

                if (type === 'discount_percent') {
                    promoDiscountValue += promoEligibleSubtotal * (value / 100);
                } else if (type === 'discount_amount') {
                    promoDiscountValue += value;
                }
            });

            discountValue += Math.min(promoEligibleSubtotal, Math.max(0, promoDiscountValue));
        }

        return Math.min(subtotalValue, Math.max(0, Math.round(discountValue)));
    }

    function calculateCheckoutPreview() {
        const subtotalValue = Number(cashierState.cart.subtotal || 0);
        const discountValue = calculateDiscountPreview(subtotalValue);
        const grandTotalValue = Math.max(0, subtotalValue - discountValue);

        return {
            subtotalValue,
            discountValue,
            grandTotalValue,
        };
    }

    function syncAmountPaid() {
        if (!paymentMethod) return;

        const grandTotalValue = calculateCheckoutPreview().grandTotalValue;

        if (['qris', 'transfer', 'debit', 'credit'].includes(paymentMethod.value)) {
            setAmountPaidValue(grandTotalValue);
            amountPaidDisplay.readOnly = true;
        } else {
            amountPaidDisplay.readOnly = false;

            if (getAmountPaidValue() <= 0 && grandTotalValue > 0) {
                setAmountPaidValue(grandTotalValue);
            } else {
                setAmountPaidValue(getAmountPaidValue());
            }
        }

        updateLivePaymentSummary();
    }

    function updateCheckoutAvailability(canCheckout) {
        checkoutButton.disabled = !canCheckout;
        clearCartButton.disabled = !isShiftOpen();
        amountPaidDisplay.disabled = !isShiftOpen();
        paymentMethod.disabled = !isShiftOpen();

        document.querySelectorAll('[data-open-variant-modal]').forEach((button) => {
            button.disabled = false;
            button.removeAttribute('disabled');
        });

        document.querySelectorAll('[data-cart-action]').forEach((button) => {
            button.disabled = !isShiftOpen();
        });

        document.querySelectorAll('[data-cart-modifier]').forEach((button) => {
            button.disabled = !isShiftOpen();
        });

        document.querySelectorAll('[data-quick-amount]').forEach((button) => {
            button.disabled = !isShiftOpen();
        });

        document.querySelectorAll('.modal-add-btn').forEach((button) => {
            button.disabled = !isShiftOpen();
        });
    }

    function updateLivePaymentSummary() {
        const { subtotalValue, discountValue, grandTotalValue } = calculateCheckoutPreview();
        const paidValue = getAmountPaidValue();
        const currentPaymentMethod = paymentMethod?.value || 'cash';

        if (liveSubtotalValue) liveSubtotalValue.textContent = formatCurrency(subtotalValue);
        if (liveDiscountValue) liveDiscountValue.textContent = discountValue > 0 ? '- ' + formatCurrency(discountValue) : formatCurrency(0);
        if (liveGrandTotalValue) liveGrandTotalValue.textContent = formatCurrency(grandTotalValue);
        if (livePaidValue) livePaidValue.textContent = formatCurrency(paidValue);

        if (cartDiscountBottom) cartDiscountBottom.textContent = discountValue > 0 ? '- ' + formatCurrency(discountValue) : formatCurrency(0);
        if (cartGrandTotalBottom) cartGrandTotalBottom.textContent = formatCurrency(grandTotalValue);

        let delta = 0;
        let helperText = 'Nominal pembayaran sudah aman untuk checkout.';
        let helperClass = 'ok';
        let valueClass = 'change-ok';
        let canCheckout = true;

        if (!isShiftOpen()) {
            helperText = 'Shift belum dibuka. Start shift dulu sebelum melakukan transaksi.';
            helperClass = 'warn';
            canCheckout = false;
        } else if (currentPaymentMethod === 'cash') {
            delta = paidValue - grandTotalValue;

            if (delta < 0) {
                helperText = 'Nominal cash masih kurang. Tambahkan pembayaran dulu sebelum checkout.';
                helperClass = 'warn';
                valueClass = 'change-minus';
                canCheckout = false;
            } else if (grandTotalValue <= 0) {
                helperText = 'Cart masih kosong. Tambahkan item dulu sebelum checkout.';
                helperClass = 'warn';
                canCheckout = false;
            }
        } else {
            delta = 0;

            if (grandTotalValue <= 0) {
                helperText = 'Cart masih kosong. Tambahkan item dulu sebelum checkout.';
                helperClass = 'warn';
                canCheckout = false;
            } else {
                helperText = 'Pembayaran non-cash akan mengikuti total setelah discount.';
            }
        }

        if (liveChangeValue) {
            liveChangeValue.textContent = formatCurrency(Math.abs(delta));
            liveChangeValue.classList.remove('change-ok', 'change-minus');
            liveChangeValue.classList.add(valueClass);
        }

        if (changeHighlightRow) {
            changeHighlightRow.classList.remove('minus');
        }

        if (currentPaymentMethod === 'cash' && delta < 0) {
            if (liveChangeValue) {
                liveChangeValue.textContent = '- ' + formatCurrency(Math.abs(delta));
            }

            if (changeHighlightRow) {
                changeHighlightRow.classList.add('minus');
            }
        }

        if (paymentHelperText) {
            paymentHelperText.textContent = helperText;
            paymentHelperText.classList.remove('ok', 'warn');
            paymentHelperText.classList.add(helperClass);
        }

        updateCheckoutAvailability(canCheckout);
    }

    function applyCartPayload(cartPayload) {
        cashierState.cart = cartPayload;
        cashierState.orderType = cartPayload.order_type || cashierState.orderType;

        if (summaryCartCount) {
            summaryCartCount.textContent = cartPayload.cart_count ?? 0;
        }

        if (summarySubtotal) {
            summarySubtotal.textContent = cartPayload.subtotal_formatted ?? formatCurrency(cartPayload.subtotal ?? 0);
        }

        if (cartSubtotalBottom) {
            cartSubtotalBottom.textContent = cartPayload.subtotal_formatted ?? formatCurrency(cartPayload.subtotal ?? 0);
        }

        if (summaryMemberName) {
            summaryMemberName.textContent = (cartPayload.member && (cartPayload.member.name || cartPayload.member.phone))
                ? (cartPayload.member.name || cartPayload.member.phone)
                : 'Belum ada member';
        }

        if (sessionOrderTypeText) {
            sessionOrderTypeText.textContent = formatOrderType(cashierState.orderType);
        }

        if (checkoutOrderType) {
            checkoutOrderType.value = cashierState.orderType;
        }

        updateOrderTypeButtons();
        renderCartItems(cartPayload.items || []);
        syncAmountPaid();
        rerenderOpenModalPrices();
    }

    async function postJson(url, body = {}) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.success === false) {
            throw new Error(data.message || 'Terjadi kendala saat memproses permintaan.');
        }

        return data;
    }

    async function handleOrderTypeChange(newOrderType, button) {
        if (newOrderType === cashierState.orderType) return;

        const hasCartItems = Number(cashierState.cart?.cart_count || 0) > 0;

        if (hasCartItems) {
            const confirmed = window.confirm('Ganti order type akan mengosongkan cart supaya harga tetap konsisten. Lanjut?');
            if (!confirmed) return;
        }

        const previousText = button.textContent;
        button.disabled = true;
        button.textContent = 'Loading...';

        try {
            const result = await postJson(setOrderTypeUrl, {
                order_type: newOrderType,
            });

            applyCartPayload(result.cart);
            showAlert('success', result.message || 'Order type berhasil diganti.');
        } catch (error) {
            showAlert('error', error.message);
        } finally {
            button.disabled = false;
            button.textContent = previousText;
        }
    }


    async function handleQuickPromoButton(button) {
        if (!button || !button.dataset.url) {
            return;
        }

        if (!isShiftOpen()) {
            showAlert('error', 'Shift belum dibuka. Start shift dulu sebelum melakukan transaksi.');
            return;
        }

        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.style.opacity = '0.65';

        try {
            const result = await postJson(button.dataset.url, {
                order_type: cashierState.orderType || 'dine_in',
            });

            if (result.cart) {
                applyCartPayload(result.cart);
            }

            const promoSelect = document.getElementById('promo_id');
            if (promoSelect && result.promo_id) {
                promoSelect.value = String(result.promo_id);
                promoSelect.dispatchEvent(new Event('change'));
            }

            showAlert('success', result.message || 'Promo berhasil dimasukkan ke cart.');
        } catch (error) {
            showAlert('error', error.message || 'Promo gagal diterapkan.');
        } finally {
            button.disabled = false;
            button.style.opacity = '';
            button.innerHTML = originalHtml;
        }
    }

    async function handleAddToCart(button) {
        const url = button.dataset.url;
        const originalText = button.textContent;

        button.disabled = true;
        button.textContent = 'Adding...';

        try {
            const result = await postJson(url, {
                order_type: cashierState.orderType,
            });

            applyCartPayload(result.cart);
            showAlert('success', result.message || 'Item berhasil masuk ke keranjang.');
            closeVariantModal();
        } catch (error) {
            showAlert('error', error.message);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    async function handleCartAction(button) {
        const url = button.dataset.url;
        const originalText = button.textContent;

        button.disabled = true;
        button.textContent = '...';

        try {
            const result = await postJson(url, {});
            applyCartPayload(result.cart);
            showAlert('success', result.message || 'Cart berhasil diupdate.');
        } catch (error) {
            showAlert('error', error.message);
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    async function handleClearCart() {
        if (!isShiftOpen()) {
            showAlert('error', 'Shift belum dibuka. Start shift dulu sebelum melakukan transaksi.');
            return;
        }

        if (Number(cashierState.cart?.cart_count || 0) <= 0) {
            showAlert('info', 'Cart sudah kosong.');
            return;
        }

        const confirmed = window.confirm('Yakin mau kosongkan seluruh cart?');
        if (!confirmed) return;

        const originalText = clearCartButton.textContent;
        clearCartButton.disabled = true;
        clearCartButton.textContent = 'Clearing...';

        try {
            const result = await postJson(clearCartUrl, {});
            applyCartPayload(result.cart);
            showAlert('success', result.message || 'Keranjang berhasil dikosongkan.');
        } catch (error) {
            showAlert('error', error.message);
        } finally {
            clearCartButton.disabled = false;
            clearCartButton.textContent = originalText;
        }
    }

    async function handleStartShift() {
        const openingCash = Number(openingCashInput.value || 0);
        const originalText = startShiftButton.textContent;

        startShiftButton.disabled = true;
        startShiftButton.textContent = 'Starting...';

        try {
            const result = await postJson(startShiftUrl, {
                opening_cash: openingCash,
            });

            cashierState.activeShift = result.shift.active_shift;
            cashierState.shiftSummary = result.shift.summary;
            updateShiftUI();
            updateLivePaymentSummary();
            showAlert('success', result.message || 'Shift berhasil dibuka.');
        } catch (error) {
            showAlert('error', error.message);
        } finally {
            startShiftButton.disabled = false;
            startShiftButton.textContent = originalText;
        }
    }

    async function handleEndShift() {
        const closingCashActual = getClosingCashActualValue();
        const closingNote = closingNoteInput.value || '';
        const originalText = endShiftButton.textContent;

        const confirmed = window.confirm('Yakin mau tutup shift sekarang?');
        if (!confirmed) return;

        endShiftButton.disabled = true;
        endShiftButton.textContent = 'Ending...';

                try {
            const result = await postJson(endShiftUrl, {
                closing_cash_actual: closingCashActual,
                closing_note: closingNote,
            });

            const printUrl = result?.shift?.print_url || null;

            cashierState.activeShift = null;
            cashierState.shiftSummary = result.shift.summary || {
                total_transactions: 0,
                total_sales: 0,
                cash_sales: 0,
                qris_sales: 0,
                transfer_sales: 0,
                debit_sales: 0,
                credit_sales: 0,
                void_transactions: 0,
                expected_cash: 0,
                difference: 0,
            };

            updateShiftUI();
            updateLivePaymentSummary();
            showAlert('success', result.message || 'Shift berhasil ditutup.');

            if (printUrl) {
                window.open(printUrl, '_blank');
            }
        } catch (error) {
            showAlert('error', error.message);
        } finally {
            endShiftButton.disabled = false;
            endShiftButton.textContent = originalText;
        }
    }

    function applySearchFilter(keyword) {
        const normalized = String(keyword || '').trim().toLowerCase();

        document.querySelectorAll('[data-product-card]').forEach((card) => {
            const haystack = card.dataset.search || '';
            const isMatch = normalized === '' || haystack.includes(normalized);
            card.classList.toggle('hidden', !isMatch);
        });

        document.querySelectorAll('[data-product-category-section]').forEach((section) => {
            const visibleCards = Array.from(section.querySelectorAll('[data-product-card]'))
                .filter((card) => !card.classList.contains('hidden'));

            section.classList.toggle('hidden', visibleCards.length === 0);

            if (normalized !== '') {
                section.classList.toggle('collapsed', visibleCards.length === 0);
            }

            const emptyState = section.querySelector('.product-category-empty');
            if (emptyState) {
                emptyState.style.display = normalized !== '' && visibleCards.length === 0 ? 'block' : 'none';
            }
        });
    }

    function roundUp(value, base) {
        if (value <= 0) return base;
        return Math.ceil(value / base) * base;
    }

    function applyQuickAmount(mode) {
        if (!isShiftOpen()) {
            showAlert('error', 'Shift belum dibuka. Start shift dulu sebelum melakukan transaksi.');
            return;
        }

        if (!amountPaidDisplay) return;
        if (['qris', 'transfer', 'debit', 'credit'].includes(paymentMethod.value)) {
            showAlert('info', 'Quick amount dipakai untuk pembayaran cash.');
            return;
        }

        const subtotalValue = Number(cashierState.cart.subtotal || 0);
        let currentValue = getAmountPaidValue();

        if (mode === 'exact') {
            currentValue = subtotalValue;
        } else if (mode === 'reset') {
            currentValue = 0;
        } else if (mode === 'round_5000') {
            currentValue = roundUp(subtotalValue, 5000);
        } else if (mode === 'round_10000') {
            currentValue = roundUp(subtotalValue, 10000);
        } else {
            currentValue += Number(mode || 0);
        }

        setAmountPaidValue(Math.max(0, currentValue));
        updateLivePaymentSummary();
    }

    function switchTab(tabName) {
    
    document.querySelectorAll('[data-tab-btn]').forEach((button) => {
            button.classList.toggle('active', button.dataset.tabBtn === tabName);
        });

        document.getElementById('tab-panel-transaction').classList.toggle('hidden', tabName !== 'transaction');
        document.getElementById('tab-panel-history').classList.toggle('hidden', tabName !== 'history');
        document.getElementById('tab-panel-shift').classList.toggle('hidden', tabName !== 'shift');
    }

    function openVariantModal(button) {
    const productCard = button.closest('[data-product-card]');
    if (!productCard) return;

    const productName = button.dataset.productName || 'Product';
    const productMeta = button.dataset.productMeta || '-';
    const sourceContainer = productCard.querySelector('[data-variant-modal-source]');

    if (!sourceContainer) return;

    const sourceItems = Array.from(sourceContainer.querySelectorAll('[data-variant-source-item]'));
    const activeOrderType = cashierState.orderType === 'delivery' ? 'delivery' : 'dine_in';
    const activeOrderTypeLabel = activeOrderType === 'delivery' ? 'Delivery' : 'Dine In';

    if (variantModalTitle) variantModalTitle.textContent = productName;
    if (variantModalSubtitle) variantModalSubtitle.textContent = productMeta;
    if (variantModalOrderType) variantModalOrderType.textContent = formatOrderType(activeOrderType);

    if (!sourceItems.length) {
        variantModalGrid.innerHTML = `
            <div class="variant-option-card" style="grid-column:1/-1; min-height:unset;">
                <div class="variant-option-name">Belum ada variant aktif</div>
                <div class="variant-active-price-text" style="margin-top:12px;">Product ini belum bisa dijual.</div>
            </div>
        `;
    } else {
        variantModalGrid.innerHTML = sourceItems.map((item) => {
            const name = item.dataset.name || 'Variant';
            const code = item.dataset.code || '-';
            const dineIn = Number(item.dataset.dineIn || 0);
            const delivery = Number(item.dataset.delivery || 0);
            const activePrice = activeOrderType === 'delivery' ? delivery : dineIn;
            const url = item.dataset.url || '#';

            return `
                <div class="variant-option-card">
                    <div class="variant-option-top">
                        <div>
                            <div class="variant-option-name">${escapeHtml(name)}</div>
                            <div class="variant-option-code">${escapeHtml(code)}</div>
                        </div>
                    </div>

                    <div class="variant-price-grid">
                        <div class="variant-price-box">
                            <div class="variant-price-label">${escapeHtml(activeOrderTypeLabel)}</div>
                            <div class="variant-price-value">${escapeHtml(formatCurrency(activePrice))}</div>
                        </div>
                    </div>

                    <div class="variant-active-price">
                        <div class="variant-active-price-text">
                            Harga aktif sekarang:<br>
                            <strong>${escapeHtml(formatCurrency(activePrice))}</strong>
                        </div>

                        <button
                            type="button"
                            class="modal-add-btn"
                            data-add-to-cart
                            data-url="${escapeHtml(url)}"
                        >
                            Tambah
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    variantModalBackdrop.classList.add('active');
    variantModalBackdrop.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    updateCheckoutAvailability(checkoutButton ? !checkoutButton.disabled : true);
}

    function rerenderOpenModalPrices() {
        if (!variantModalBackdrop.classList.contains('active')) return;

        const title = variantModalTitle.textContent || '';
        const opener = Array.from(document.querySelectorAll('[data-open-variant-modal]')).find((button) => {
            return (button.dataset.productName || '') === title;
        });

        if (opener) {
            openVariantModal(opener);
        }
    }

    function closeVariantModal() {
        variantModalBackdrop.classList.remove('active');
        variantModalBackdrop.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    document.addEventListener('click', async (event) => {
        const orderTypeButton = event.target.closest('[data-order-type-btn]');
        if (orderTypeButton) {
            await handleOrderTypeChange(orderTypeButton.dataset.orderType, orderTypeButton);
            return;
        }

        const quickPromoButton = event.target.closest('[data-quick-promo]');
        if (quickPromoButton) {
            event.preventDefault();
            await handleQuickPromoButton(quickPromoButton);
            return;
        }

        const openVariantButton = event.target.closest('[data-open-variant-modal]');
        if (openVariantButton) {
            event.preventDefault();
            openVariantModal(openVariantButton);

            if (!isShiftOpen()) {
                showAlert('error', 'Shift belum dibuka. Kamu bisa lihat variant, tapi Start Shift dulu untuk tambah item.');
            }

            return;
        }

        const addButton = event.target.closest('[data-add-to-cart]');
        if (addButton) {
            await handleAddToCart(addButton);
            return;
        }

        const cartActionButton = event.target.closest('[data-cart-action]');
        if (cartActionButton) {
            await handleCartAction(cartActionButton);
            return;
        }

        const modifierButton = event.target.closest('[data-cart-modifier]');
        if (modifierButton) {
            const url = modifierButton.dataset.url;
            const modifier = modifierButton.dataset.cartModifier;
            const originalText = modifierButton.textContent;

            modifierButton.disabled = true;
            modifierButton.textContent = '...';

            try {
                const result = await postJson(url, {
                    modifier: modifier,
                });

                applyCartPayload(result.cart);
                showAlert('success', result.message || 'Modifier berhasil diupdate.');
            } catch (error) {
                showAlert('error', error.message);
            } finally {
                modifierButton.disabled = false;
                modifierButton.textContent = originalText;
            }

            return;
        }

        const quickAmountButton = event.target.closest('[data-quick-amount]');
        if (quickAmountButton) {
            applyQuickAmount(quickAmountButton.dataset.quickAmount);
            return;
        }

        const tabButton = event.target.closest('[data-tab-btn]');
        if (tabButton) {
            switchTab(tabButton.dataset.tabBtn);
            return;
        }

        if (event.target === variantModalBackdrop) {
            closeVariantModal();
        }
    });

    variantModalClose?.addEventListener('click', closeVariantModal);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && variantModalBackdrop.classList.contains('active')) {
            closeVariantModal();
        }
    });

    document.querySelectorAll('.cashier-void-form').forEach((form) => {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            const reason = window.prompt('Masukkan alasan void transaksi:');

            if (reason === null) {
                return;
            }

            if (!String(reason).trim()) {
                showAlert('error', 'Alasan void wajib diisi.');
                return;
            }

            const pin = window.prompt('Masukkan PIN approval dari back office. Kalau belum ada PIN, kosongkan lalu OK untuk request PIN:');

            if (pin === null) {
                return;
            }

            const confirmed = window.confirm('Yakin void transaksi ini? Stock akan dikembalikan.');
            if (!confirmed) {
                return;
            }

            const hiddenReasonInput = form.querySelector('input[name="void_reason"]');
            const hiddenPinInput = form.querySelector('input[name="approval_pin"]');

            if (hiddenReasonInput) {
                hiddenReasonInput.value = String(reason).trim();
            }

            if (hiddenPinInput) {
                hiddenPinInput.value = String(pin).trim();
            }

            form.submit();
        });
    });


    const paymentModalPanel = document.getElementById('payment-modal-panel');
    const openPaymentModalBtn = document.getElementById('open-payment-modal-btn');
    const closePaymentModalBtn = document.getElementById('close-payment-modal-btn');

    function openPaymentModal() {
        if (!paymentModalPanel) {
            return;
        }

        if (paymentModalPanel.parentElement !== document.body) {
            document.body.appendChild(paymentModalPanel);
        }

        paymentModalPanel.classList.add('active');
        document.body.classList.add('payment-modal-open');

        const paymentModalCard = paymentModalPanel.querySelector('.payment-modal-card');
        const paymentModalContent = paymentModalPanel.querySelector('.payment-modal-content');

        if (paymentModalCard) {
            paymentModalCard.scrollTop = 0;
        }

        if (paymentModalContent) {
            paymentModalContent.scrollTop = 0;
        }

        if (typeof updateLivePaymentSummary === 'function') {
            updateLivePaymentSummary();
        }
    }

    function closePaymentModal() {
        paymentModalPanel?.classList.remove('active');
        document.body.classList.remove('payment-modal-open');
    }

    openPaymentModalBtn?.addEventListener('click', openPaymentModal);
    closePaymentModalBtn?.addEventListener('click', closePaymentModal);

    paymentModalPanel?.addEventListener('click', function (event) {
        if (event.target === paymentModalPanel) {
            closePaymentModal();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePaymentModal();
        }
    });


    startShiftForm?.addEventListener('submit', async function (event) {
        event.preventDefault();
        await handleStartShift();
    });

    endShiftForm?.addEventListener('submit', async function (event) {
        event.preventDefault();
        await handleEndShift();
    });

    clearCartButton?.addEventListener('click', handleClearCart);

    searchInput?.addEventListener('input', function () {
        applySearchFilter(this.value);
    });

    paymentMethod?.addEventListener('change', syncAmountPaid);

    discountSelect?.addEventListener('change', function () {
        syncAmountPaid();
        updateLivePaymentSummary();
    });

    promoSelect?.addEventListener('change', function () {
        syncAmountPaid();
        updateLivePaymentSummary();
    });

    amountPaidDisplay?.addEventListener('input', function () {
        if (this.readOnly) return;
        const parsed = parseRupiahInput(this.value);
        setAmountPaidValue(parsed);
        updateLivePaymentSummary();
    });

    amountPaidDisplay?.addEventListener('blur', function () {
        setAmountPaidValue(getAmountPaidValue());
        updateLivePaymentSummary();
    });

    closingCashActualDisplay?.addEventListener('input', function () {
        const parsed = parseRupiahInput(this.value);
        setClosingCashActualValue(parsed);
    });

    closingCashActualDisplay?.addEventListener('blur', function () {
        setClosingCashActualValue(getClosingCashActualValue());
    });

    checkoutForm?.addEventListener('submit', function () {
        amountPaidNumeric.value = Number(getAmountPaidValue()).toFixed(2);
    });

    window.addEventListener('DOMContentLoaded', () => {
        updateShiftUI();
        applyCartPayload(cashierState.cart);
        setAmountPaidValue({{ (float) $oldAmountPaid }});
        setClosingCashActualValue({{ (float) ($shiftSummary['expected_cash'] ?? 0) }});
        syncAmountPaid();
        updateLivePaymentSummary();
        switchTab('transaction');
    });
</script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/service-worker.js').catch(function () {
                    // Silent fail supaya tidak ganggu POS flow.
                });
            });
        }

    document.querySelectorAll('[data-product-category-toggle]').forEach((header) => {
        header.addEventListener('click', function () {
            const section = header.closest('[data-product-category-section]');

            if (!section) {
                return;
            }

            section.classList.toggle('collapsed');
        });
    });


    document.querySelectorAll('.cashier-reprint-form').forEach((form) => {
        form.addEventListener('submit', function (event) {
            const printCount = Number(form.dataset.printCount || 0);

            if (printCount <= 0) {
                return;
            }

            const approvalPin = window.prompt('Reprint kedua dan seterusnya butuh PIN approval dari Back Office:');

            if (!approvalPin || !approvalPin.trim()) {
                event.preventDefault();
                showAlert('error', 'PIN approval wajib diisi untuk reprint.');
                return;
            }

            const pinInput = form.querySelector('input[name="approval_pin"]');

            if (pinInput) {
                pinInput.value = approvalPin.trim();
            }
        });
    });

    </script>

</body>
</html>