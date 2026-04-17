<!DOCTYPE html>
<html lang="en">
<head>
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
            padding: 20px;
        }

        .shell {
            max-width: 1480px;
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

        .btn:hover { transform: translateY(-1px); opacity: 0.96; }

        .btn:disabled,
        .btn-add:disabled,
        .mini-btn:disabled,
        .btn-wide:disabled,
        .order-type-btn:disabled,
        .quick-amount-btn:disabled,
        .shift-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-dark { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); }
        .btn-brand { background: linear-gradient(135deg, var(--brand) 0%, #f08a57 100%); }
        .btn-green { background: linear-gradient(135deg, #166534 0%, #1f7a44 100%); }
        .btn-red { background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); }

        .content { padding: 14px 28px 28px; }

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
            grid-template-columns: minmax(0, 1.12fr) minmax(420px, 0.88fr);
            gap: 22px;
            align-items: start;
        }

        .section-card {
            background: rgba(255,255,255,0.90);
            border: 1px solid #eceff5;
            border-radius: 28px;
            box-shadow: var(--shadow-soft);
            overflow: visible;
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

        .shift-box {
            margin: 0 22px 18px;
            padding: 18px;
            border-radius: 20px;
            border: 1px solid #e5e7eb;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
        }

        .shift-box.start {
            border-color: #f4ddd1;
            background: linear-gradient(180deg, #fff8f4 0%, #ffffff 100%);
        }

        .shift-box.active {
            border-color: #d8f0de;
            background: linear-gradient(180deg, #f3fff7 0%, #ffffff 100%);
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
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 14px;
        }

        .shift-stat {
            padding: 12px 14px;
            border-radius: 14px;
            background: white;
            border: 1px solid #e5e7eb;
        }

        .shift-stat-label {
            font-size: 11px;
            font-weight: 800;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .shift-stat-value {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            line-height: 1.5;
        }

        .shift-form {
            display: grid;
            gap: 12px;
        }

        .shift-field label {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #374151;
            margin-bottom: 7px;
        }

        .shift-field input,
        .shift-field textarea {
            width: 100%;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
            padding: 14px 16px;
            font-size: 15px;
            outline: none;
            font-family: Arial, sans-serif;
        }

        .shift-field input {
            min-height: 52px;
        }

        .shift-field textarea {
            min-height: 88px;
            resize: vertical;
        }

        .shift-field input:focus,
        .shift-field textarea:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .shift-actions {
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

        .checkout-success-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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

        .products-grid {
            padding: 0 22px 22px;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .product-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e7edf5;
            border-radius: 22px;
            padding: 16px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .hidden { display: none !important; }

        .product-image {
            width: 100%;
            height: 140px;
            border-radius: 18px;
            background: linear-gradient(135deg, #fff8f4 0%, #fff2ea 100%);
            border: 1px solid #f4e2d8;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image img {
            width: 66px;
            height: 66px;
            object-fit: contain;
            opacity: 0.92;
        }

        .product-name {
            font-size: 18px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 6px;
            line-height: 1.3;
        }

        .product-meta {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 14px;
            line-height: 1.6;
            min-height: 42px;
        }

        .variant-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .variant-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #e8edf4;
        }

        .variant-info { min-width: 0; }

        .variant-name {
            font-size: 15px;
            font-weight: 800;
            color: #111827;
            margin-bottom: 4px;
        }

        .variant-price {
            font-size: 14px;
            color: var(--brand-dark);
            font-weight: 800;
        }

        .btn-add {
            min-height: 46px;
            min-width: 86px;
            padding: 0 16px;
            border-radius: 14px;
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            color: white;
            font-size: 14px;
            font-weight: 800;
            border: 0;
            cursor: pointer;
            white-space: nowrap;
        }

        .cart-panel-wrap {
            position: relative;
            align-self: start;
        }

        .cart-panel {
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 22px;
            position: sticky;
            top: 18px;
            align-self: start;
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
            margin-bottom: 10px;
        }

        .cart-actions {
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
        .mini-btn-brand { background: var(--brand); }
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

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 800;
            color: #374151;
            margin-bottom: 8px;
        }

        .field input,
        .field select {
            width: 100%;
            min-height: 56px;
            border: 1px solid #d7dce5;
            border-radius: 16px;
            background: white;
            padding: 0 16px;
            font-size: 16px;
            outline: none;
        }

        .field input:focus,
        .field select:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
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

        .member-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
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

        .receipt-history-box {
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border: 1px solid #e7edf5;
            border-radius: 22px;
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

        .receipt-history-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .receipt-history-empty {
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
            margin-top: 12px;
        }

        @media (max-width: 1200px) {
            .hero,
            .layout {
                grid-template-columns: 1fr;
            }

            .cart-panel {
                position: static;
                top: auto;
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
            .cart-panel {
                padding-left: 16px;
                padding-right: 16px;
            }

            .summary-grid,
            .checkout-success-meta,
            .quick-amount-grid,
            .shift-grid {
                grid-template-columns: 1fr;
            }

            .summary-card.soft-blue {
                grid-column: auto;
            }

            .payment-live-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .payment-live-value {
                text-align: left;
            }

            .order-type-buttons {
                grid-template-columns: 1fr;
            }

            .checkout-success-actions,
            .member-actions,
            .cart-actions,
            .shift-actions,
            .receipt-history-actions {
                flex-direction: column;
            }

            .checkout-success-actions .btn,
            .member-actions .btn,
            .cart-actions .mini-btn,
            .shift-actions .shift-btn,
            .receipt-history-actions .btn {
                width: 100%;
            }
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
        'void_transactions' => (int) ($shiftSummary['void_transactions'] ?? 0),
        'expected_cash' => (float) ($shiftSummary['expected_cash'] ?? 0),
        'difference' => 0,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    $oldPaymentMethod = old('payment_method', 'cash');
    $oldAmountPaid = old('amount_paid', (float) $subtotal);
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
                <div class="hero">
                    <div class="hero-main">
                        <h1 class="hero-title">Fast selling flow with a cleaner frontliner experience.</h1>
                        <p class="hero-subtitle">
                            Fokus ke transaksi yang cepat, nyaman, dan lebih presentable dengan tampilan cashier yang selaras dengan sistem ATG POS.
                        </p>

                        <div class="hero-pills">
                            <div class="hero-pill">Quick checkout</div>
                            <div class="hero-pill">Tablet ready</div>
                            <div class="hero-pill">Shift ready</div>
                            <div class="hero-pill">Order type ready</div>
                        </div>
                    </div>

                    <div class="session-card">
                        <div class="session-title">Cashier Session</div>
                        <div class="session-line"><span class="label">User:</span>{{ $user->name ?? '-' }}</div>
                        <div class="session-line"><span class="label">Role:</span>{{ $user->role->name ?? '-' }}</div>
                        <div class="session-line"><span class="label">Outlet:</span>{{ $user->outlet->name ?? '-' }}</div>
                        <div class="session-line"><span class="label">Order Type:</span><span id="session-order-type-text">{{ strtoupper(str_replace('_', ' ', $orderType ?? 'dine_in')) }}</span></div>
                        <div class="toolbar">
                            <div class="toolbar-pill">{{ $activeShift ? 'Shift active' : 'Shift not started' }}</div>
                            <div class="toolbar-pill">POS ready</div>
                        </div>
                    </div>
                </div>

                <div class="layout">
                    <div class="section-card">
                        <div class="section-head">
                            <h2 class="section-title">Menu Products</h2>
                            <p class="section-subtitle">Pilih product dan variant untuk ditambahkan ke cart transaksi.</p>
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

                        <div id="shift-start-box" class="shift-box start {{ $activeShift ? 'hidden' : '' }}">
                            <div class="shift-title">Shift belum dibuka</div>
                            <div class="shift-subtitle">
                                Kasir harus mulai shift dulu sebelum bisa tambah item, clear cart, dan checkout.
                            </div>

                            <form id="start-shift-form" class="shift-form">
                                <div class="shift-field">
                                    <label for="opening_cash">Opening Cash</label>
                                    <input type="text" id="opening_cash" name="opening_cash" inputmode="numeric" autocomplete="off" value="Rp 0">
                                </div>

                                <div class="shift-actions">
                                    <button type="submit" id="start-shift-button" class="shift-btn start">Start Shift</button>
                                </div>
                            </form>
                        </div>

                        <div id="shift-active-box" class="shift-box active {{ $activeShift ? '' : 'hidden' }}">
                            <div class="shift-title">Shift aktif</div>
                            <div class="shift-subtitle">
                                Ringkasan transaksi aktif selama shift berjalan.
                            </div>

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
                            </div>

                            <form id="end-shift-form" class="shift-form">
                                <div class="shift-field">
                                    <label for="closing_cash_actual">Closing Cash Actual</label>
                                    <input type="text" id="closing_cash_actual" name="closing_cash_actual" inputmode="numeric" autocomplete="off" value="Rp {{ number_format((float) ($shiftSummary['expected_cash'] ?? 0), 0, ',', '.') }}">
                                </div>

                                <div class="shift-field">
                                    <label for="closing_note">Closing Note</label>
                                    <textarea id="closing_note" name="closing_note" placeholder="Catatan shift penutup (opsional)"></textarea>
                                </div>

                                <div class="shift-actions">
                                    <button type="submit" id="end-shift-button" class="shift-btn end">End Shift</button>
                                </div>
                            </form>
                        </div>

                        @if(session('last_checkout'))
                            <div class="checkout-success-box">
                                <div class="checkout-success-title">Checkout berhasil disimpan.</div>

                                <div class="checkout-success-meta">
                                    <div class="checkout-success-item">
                                        <div class="checkout-success-label">Transaction Number</div>
                                        <div class="checkout-success-value">{{ session('last_checkout.transaction_number') }}</div>
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
                                        href="{{ route('backoffice.transactions.receipt', ['transaction' => session('last_checkout.transaction_id'), 'source' => 'cashier']) }}"
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
                                <div class="order-type-note">
                                    Kalau order type diganti saat cart masih ada isi, cart akan otomatis dikosongkan supaya harga tetap konsisten.
                                </div>
                            </div>
                        </div>

                        <div class="products-grid" id="products-grid">
                            @forelse($products as $product)
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

                                    <div class="variant-list">
                                        @forelse($product->variants->where('is_active', true) as $variant)
                                            @php
                                                $dineInPrice = (float) ($variant->price_dine_in ?? $variant->price);
                                                $deliveryPrice = (float) ($variant->price_delivery ?? $variant->price);
                                                $variantPrice = ($orderType ?? 'dine_in') === 'delivery' ? $deliveryPrice : $dineInPrice;
                                            @endphp

                                            <div class="variant-item">
                                                <div class="variant-info">
                                                    <div class="variant-name">{{ $variant->name }}</div>
                                                    <div
                                                        class="variant-price"
                                                        data-variant-price
                                                        data-dine-in="{{ $dineInPrice }}"
                                                        data-delivery="{{ $deliveryPrice }}"
                                                    >
                                                        Rp {{ number_format($variantPrice, 0, ',', '.') }}
                                                    </div>
                                                </div>

                                                <button
                                                    type="button"
                                                    class="btn-add"
                                                    data-add-to-cart
                                                    data-url="{{ route('cashier.cart.add', $variant) }}"
                                                >
                                                    Tambah
                                                </button>
                                            </div>
                                        @empty
                                            <div class="variant-item">
                                                <div class="variant-info">
                                                    <div class="variant-name">Belum ada variant aktif</div>
                                                    <div class="variant-price">Product ini belum bisa dijual</div>
                                                </div>
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            @empty
                                <div class="product-card" style="grid-column: 1 / -1;">
                                    <div class="product-name">Belum ada product aktif</div>
                                    <div class="product-meta">Product aktif akan muncul di area ini.</div>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="cart-panel-wrap">
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
                                                    • Qty: {{ $item['qty'] ?? 0 }}
                                                    • Price: Rp {{ number_format((float) ($item['price'] ?? 0), 0, ',', '.') }}
                                                    • Line Total: Rp {{ number_format((float) ($item['line_total'] ?? 0), 0, ',', '.') }}

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

                                                <div class="cart-actions">
                                                    <button type="button" class="mini-btn mini-btn-dark" data-cart-action="increase" data-url="{{ route('cashier.cart.increase', $cartKey) }}">+ Tambah</button>
                                                    <button type="button" class="mini-btn mini-btn-brand" data-cart-action="decrease" data-url="{{ route('cashier.cart.decrease', $cartKey) }}">- Kurangi</button>
                                                    <button type="button" class="mini-btn mini-btn-dark" data-cart-modifier="less_sugar" data-url="{{ route('cashier.cart.toggle-modifier', $cartKey) }}">
                                                        {{ !empty($item['less_sugar']) ? '✓ Less Sugar' : 'Less Sugar' }}
                                                    </button>
                                                    <button type="button" class="mini-btn mini-btn-dark" data-cart-modifier="less_ice" data-url="{{ route('cashier.cart.toggle-modifier', $cartKey) }}">
                                                        {{ !empty($item['less_ice']) ? '✓ Less Ice' : 'Less Ice' }}
                                                    </button>
                                                    <button type="button" class="mini-btn mini-btn-red" data-cart-action="remove" data-url="{{ route('cashier.cart.remove', $cartKey) }}">Hapus</button>
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

                                <div class="payment-section">
                                    <div class="payment-section-head">
                                        <div>
                                            <div class="payment-section-title">Payment & Checkout</div>
                                            <div class="payment-section-subtitle">
                                                Dibuat lebih nyaman untuk tap flow di tablet.
                                            </div>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('cashier.checkout') }}" class="payment-form" id="checkout-form">
                                        @csrf

                                        <input type="hidden" name="order_type" id="checkout-order-type" value="{{ $orderType ?? 'dine_in' }}">
                                        <input type="hidden" name="amount_paid" id="amount_paid_numeric" value="{{ (float) $oldAmountPaid }}">

                                        <div class="field">
                                            <label for="payment_method">Payment Method</label>
                                            <select name="payment_method" id="payment_method" required>
                                                <option value="cash" {{ $oldPaymentMethod === 'cash' ? 'selected' : '' }}>Cash</option>
                                                <option value="qris" {{ $oldPaymentMethod === 'qris' ? 'selected' : '' }}>QRIS</option>
                                                <option value="transfer" {{ $oldPaymentMethod === 'transfer' ? 'selected' : '' }}>Transfer</option>
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
                                                <div class="payment-live-label">Amount Paid</div>
                                                <div class="payment-live-value" id="live-paid-value">Rp {{ number_format((float) $oldAmountPaid, 0, ',', '.') }}</div>
                                            </div>

                                            <div class="payment-live-row change-highlight" id="change-highlight-row">
                                                <div class="payment-live-label">Kembalian / Selisih</div>
                                                <div class="payment-live-value" id="live-change-value">Rp 0</div>
                                            </div>

                                            <div class="payment-helper ok" id="payment-helper-text">
                                                Nominal pembayaran sudah aman untuk checkout.
                                            </div>
                                        </div>

                                        <div class="checkout-actions">
                                            <button type="submit" class="btn-wide btn-checkout" id="checkout-button">Checkout</button>
                                        </div>
                                    </form>

                                    <div class="checkout-actions" style="margin-top:12px;">
                                        <button type="button" id="clear-cart-button" class="btn-wide btn-clear">Clear Cart</button>
                                    </div>
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

                                @isset($recentReceipts)
                                <div class="receipt-history-box">
                                    <div class="cart-title">Histori Struk Cashier</div>

                                    @if($recentReceipts->count())
                                        <div class="receipt-history-list">
                                            @foreach($recentReceipts as $receipt)
                                                @php
                                                    $displayReceiptNumber = 'ATG-0001';

                                                    if (! empty($receipt->transaction_number)) {
                                                        $parts = explode('-', $receipt->transaction_number);
                                                        $lastPart = end($parts);

                                                        if (is_numeric($lastPart)) {
                                                            $displayReceiptNumber = 'ATG-' . str_pad((string) ((int) $lastPart), 4, '0', STR_PAD_LEFT);
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
                                                        @forelse($receipt->items->take(3) as $item)
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

                                                        @if($receipt->items->count() > 3)
                                                            <div>+ {{ $receipt->items->count() - 3 }} item lainnya</div>
                                                        @endif
                                                    </div>

                                                    <div class="receipt-history-actions">
                                                        <a
                                                            href="{{ route('backoffice.transactions.receipt', ['transaction' => $receipt->id, 'source' => 'cashier']) }}"
                                                            target="_blank"
                                                            class="btn btn-green"
                                                        >
                                                            Buka Receipt
                                                        </a>

                                                        <a
                                                            href="{{ route('cashier.new-transaction') }}"
                                                            class="btn btn-dark"
                                                        >
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
                                @endisset
                            </div>
                        </div>
                    </div>
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
            const livePaidValue = document.getElementById('live-paid-value');
            const liveChangeValue = document.getElementById('live-change-value');
            const paymentHelperText = document.getElementById('payment-helper-text');
            const checkoutButton = document.getElementById('checkout-button');
            const changeHighlightRow = document.getElementById('change-highlight-row');

            const shiftStartBox = document.getElementById('shift-start-box');
            const shiftActiveBox = document.getElementById('shift-active-box');
            const startShiftForm = document.getElementById('start-shift-form');
            const endShiftForm = document.getElementById('end-shift-form');
            const openingCashInput = document.getElementById('opening_cash');
            const closingCashActualInput = document.getElementById('closing_cash_actual');
            const closingNoteInput = document.getElementById('closing_note');
            const startShiftButton = document.getElementById('start-shift-button');
            const endShiftButton = document.getElementById('end-shift-button');
            const shiftStartedAt = document.getElementById('shift-started-at');
            const shiftOpeningCash = document.getElementById('shift-opening-cash');
            const shiftCashSales = document.getElementById('shift-cash-sales');
            const shiftExpectedCash = document.getElementById('shift-expected-cash');
            const shiftTotalTransactions = document.getElementById('shift-total-transactions');
            const shiftTotalSales = document.getElementById('shift-total-sales');

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

            function parseRupiahInput(value) {
                const raw = String(value || '').replace(/[^\d]/g, '');
                return raw ? Number(raw) : 0;
            }

            function setRupiahFieldValue(element, value) {
                if (!element) return;
                const clean = Math.max(0, Number(value || 0));
                element.value = formatCurrency(clean);
            }

            function getRupiahFieldValue(element) {
                return parseRupiahInput(element?.value || '');
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
                    setRupiahFieldValue(closingCashActualInput, cashierState.shiftSummary.expected_cash || 0);
                }
            }

            function updateOrderTypeButtons() {
                document.querySelectorAll('[data-order-type-btn]').forEach((button) => {
                    button.classList.toggle('active', button.dataset.orderType === cashierState.orderType);
                });
            }

            function updateVariantPrices() {
                document.querySelectorAll('[data-variant-price]').forEach((priceElement) => {
                    const dineIn = Number(priceElement.dataset.dineIn || 0);
                    const delivery = Number(priceElement.dataset.delivery || 0);
                    const price = cashierState.orderType === 'delivery' ? delivery : dineIn;
                    priceElement.textContent = formatCurrency(price);
                });
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
                                • Qty: ${escapeHtml(item.qty)}
                                • Price: ${escapeHtml(formatCurrency(item.price))}
                                • Line Total: ${escapeHtml(formatCurrency(item.line_total))}
                                ${modifiers.length ? `<br>Modifier: ${escapeHtml(modifiers.join(' • '))}` : ''}
                            </div>

                            <div class="cart-actions">
                                <button type="button" class="mini-btn mini-btn-dark" data-cart-action="increase" data-url="/cashier/cart/increase/${encodeURIComponent(item.cart_key)}">+ Tambah</button>
                                <button type="button" class="mini-btn mini-btn-brand" data-cart-action="decrease" data-url="/cashier/cart/decrease/${encodeURIComponent(item.cart_key)}">- Kurangi</button>
                                <button type="button" class="mini-btn mini-btn-dark" data-cart-modifier="less_sugar" data-url="/cashier/cart/toggle-modifier/${encodeURIComponent(item.cart_key)}">${item.less_sugar ? '✓ Less Sugar' : 'Less Sugar'}</button>
                                <button type="button" class="mini-btn mini-btn-dark" data-cart-modifier="less_ice" data-url="/cashier/cart/toggle-modifier/${encodeURIComponent(item.cart_key)}">${item.less_ice ? '✓ Less Ice' : 'Less Ice'}</button>
                                <button type="button" class="mini-btn mini-btn-red" data-cart-action="remove" data-url="/cashier/cart/remove/${encodeURIComponent(item.cart_key)}">Hapus</button>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            function syncAmountPaid() {
                if (!paymentMethod) return;

                const subtotalValue = Number(cashierState.cart.subtotal || 0);

                if (paymentMethod.value === 'qris' || paymentMethod.value === 'transfer') {
                    setAmountPaidValue(subtotalValue);
                    amountPaidDisplay.readOnly = true;
                } else {
                    amountPaidDisplay.readOnly = false;

                    if (getAmountPaidValue() <= 0 && subtotalValue > 0) {
                        setAmountPaidValue(subtotalValue);
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

                document.querySelectorAll('[data-add-to-cart]').forEach((button) => {
                    button.disabled = !isShiftOpen();
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
            }

            function updateLivePaymentSummary() {
                const subtotalValue = Number(cashierState.cart.subtotal || 0);
                const paidValue = getAmountPaidValue();
                const currentPaymentMethod = paymentMethod?.value || 'cash';

                liveSubtotalValue.textContent = formatCurrency(subtotalValue);
                livePaidValue.textContent = formatCurrency(paidValue);

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
                    delta = paidValue - subtotalValue;

                    if (delta < 0) {
                        helperText = 'Nominal cash masih kurang. Tambahkan pembayaran dulu sebelum checkout.';
                        helperClass = 'warn';
                        valueClass = 'change-minus';
                        canCheckout = false;
                    } else if (subtotalValue <= 0) {
                        helperText = 'Cart masih kosong. Tambahkan item dulu sebelum checkout.';
                        helperClass = 'warn';
                        canCheckout = false;
                    }
                } else {
                    delta = 0;

                    if (subtotalValue <= 0) {
                        helperText = 'Cart masih kosong. Tambahkan item dulu sebelum checkout.';
                        helperClass = 'warn';
                        canCheckout = false;
                    } else {
                        helperText = 'Pembayaran non-cash akan mengikuti subtotal transaksi.';
                    }
                }

                liveChangeValue.textContent = formatCurrency(Math.abs(delta));
                liveChangeValue.classList.remove('change-ok', 'change-minus');
                liveChangeValue.classList.add(valueClass);

                changeHighlightRow.classList.remove('minus');

                if (currentPaymentMethod === 'cash' && delta < 0) {
                    liveChangeValue.textContent = '- ' + formatCurrency(Math.abs(delta));
                    changeHighlightRow.classList.add('minus');
                }

                paymentHelperText.textContent = helperText;
                paymentHelperText.classList.remove('ok', 'warn');
                paymentHelperText.classList.add(helperClass);

                updateCheckoutAvailability(canCheckout);
            }

            function applyCartPayload(cartPayload) {
                cashierState.cart = cartPayload;
                cashierState.orderType = cartPayload.order_type || cashierState.orderType;

                summaryCartCount.textContent = cartPayload.cart_count ?? 0;
                summarySubtotal.textContent = cartPayload.subtotal_formatted ?? formatCurrency(cartPayload.subtotal ?? 0);
                cartSubtotalBottom.textContent = cartPayload.subtotal_formatted ?? formatCurrency(cartPayload.subtotal ?? 0);
                summaryMemberName.textContent = (cartPayload.member && (cartPayload.member.name || cartPayload.member.phone))
                    ? (cartPayload.member.name || cartPayload.member.phone)
                    : 'Belum ada member';

                sessionOrderTypeText.textContent = formatOrderType(cashierState.orderType);
                checkoutOrderType.value = cashierState.orderType;

                updateOrderTypeButtons();
                updateVariantPrices();
                renderCartItems(cartPayload.items || []);
                syncAmountPaid();
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
                const openingCash = getRupiahFieldValue(openingCashInput);
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
                const closingCashActual = getRupiahFieldValue(closingCashActualInput);
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

                    cashierState.activeShift = null;
                    cashierState.shiftSummary = result.shift.summary || {
                        total_transactions: 0,
                        total_sales: 0,
                        cash_sales: 0,
                        qris_sales: 0,
                        transfer_sales: 0,
                        void_transactions: 0,
                        expected_cash: 0,
                        difference: 0,
                    };

                    updateShiftUI();
                    updateLivePaymentSummary();
                    showAlert('success', result.message || 'Shift berhasil ditutup.');
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
                if (paymentMethod.value === 'qris' || paymentMethod.value === 'transfer') {
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

            document.addEventListener('click', async (event) => {
                const orderTypeButton = event.target.closest('[data-order-type-btn]');
                if (orderTypeButton) {
                    await handleOrderTypeChange(orderTypeButton.dataset.orderType, orderTypeButton);
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

            openingCashInput?.addEventListener('input', function () {
                const parsed = parseRupiahInput(this.value);
                setRupiahFieldValue(this, parsed);
            });

            openingCashInput?.addEventListener('blur', function () {
                const parsed = parseRupiahInput(this.value);
                setRupiahFieldValue(this, parsed);
            });

            closingCashActualInput?.addEventListener('input', function () {
                const parsed = parseRupiahInput(this.value);
                setRupiahFieldValue(this, parsed);
            });

            closingCashActualInput?.addEventListener('blur', function () {
                const parsed = parseRupiahInput(this.value);
                setRupiahFieldValue(this, parsed);
            });

            checkoutForm?.addEventListener('submit', function () {
                amountPaidNumeric.value = Number(getAmountPaidValue()).toFixed(2);
            });

            window.addEventListener('DOMContentLoaded', () => {
                updateShiftUI();
                applyCartPayload(cashierState.cart);
                setAmountPaidValue({{ (float) $oldAmountPaid }});
                setRupiahFieldValue(openingCashInput, parseRupiahInput(openingCashInput?.value || 0));
                setRupiahFieldValue(closingCashActualInput, parseRupiahInput(closingCashActualInput?.value || 0));
                syncAmountPaid();
                updateLivePaymentSummary();
            });
        </script>
</body>
</html>