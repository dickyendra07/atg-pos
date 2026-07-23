@extends('backoffice.layouts.app')

@php
    $pageTitle = 'Back Office - ATG POS';

    $masterDataCount =
        (int) ($stats['outlet_count'] ?? 0) +
        (int) ($stats['warehouse_count'] ?? 0) +
        (int) ($stats['product_count'] ?? 0) +
        (int) ($stats['variant_count'] ?? 0) +
        (int) ($stats['ingredient_count'] ?? 0) +
        (int) ($stats['recipe_count'] ?? 0);

    $selectedOutletName = 'Semua Outlet';

    if (! empty($filters['outlet_id'])) {
        $selectedOutlet = $outletOptions->firstWhere('id', (int) $filters['outlet_id']);
        $selectedOutletName = $selectedOutlet->name ?? 'Outlet';
    }

    $maxDailySales = max(1, (float) collect($dailyTransactionSummary ?? [])->max('total_sales'));
@endphp

@section('content')
    <style>
        .dashboard-shell {
            display: grid;
            gap: 22px;
        }

        .dashboard-topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            flex-wrap: wrap;
        }

        .dashboard-kicker {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.88);
            border: 1px solid #f1e3da;
            color: #c9552a;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .dashboard-title {
            margin: 0 0 10px;
            font-size: 40px;
            line-height: 1;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: #111827;
            max-width: 980px;
        }

        .dashboard-subtitle {
            margin: 0;
            max-width: 960px;
            color: #6b7280;
            font-size: 15px;
            line-height: 1.9;
        }

        .dashboard-user-pill {
            font-size: 13px;
            color: #6b7280;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.86);
            border: 1px solid #e5e7eb;
            font-weight: 700;
            white-space: nowrap;
        }

        .filter-card,
        .summary-section,
        .premium-strip {
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            border-radius: 28px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
        }

        .filter-card {
            padding: 22px;
        }

        .filter-title {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 800;
            color: #111827;
        }

        .filter-subtitle {
            margin: 0 0 18px;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1fr auto;
            gap: 14px;
            align-items: end;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #6b7280;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .field input,
        .field select {
            width: 100%;
            min-height: 52px;
            border: 1px solid #d7dce5;
            border-radius: 14px;
            background: white;
            padding: 0 14px;
            font-size: 14px;
            color: #111827;
            outline: none;
            box-sizing: border-box;
        }

        .field input:focus,
        .field select:focus {
            border-color: rgba(232,106,58,0.75);
            box-shadow: 0 0 0 4px rgba(232,106,58,0.10);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        }

        .btn-soft {
            background: #f3f4f6;
            color: #374151;
            box-shadow: none;
        }

        .premium-strip {
            padding: 22px;
            background: linear-gradient(135deg, #ffffff 0%, #fff8f4 60%, #fff1ea 100%);
            border-color: #f0e1d8;
        }

        .premium-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .premium-card {
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            padding: 22px;
            min-height: 152px;
            color: #111827;
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
            box-shadow: 0 14px 24px rgba(15,23,42,0.06);
        }

        .premium-card::after {
            content: "";
            position: absolute;
            right: -24px;
            top: -24px;
            width: 110px;
            height: 110px;
            border-radius: 999px;
            opacity: 0.22;
        }

        .premium-card.orange {
            background: linear-gradient(180deg, #fff9f6 0%, #ffffff 100%);
            border-color: #f4ddd0;
        }

        .premium-card.orange::after {
            background: radial-gradient(circle, rgba(232,106,58,0.28) 0%, rgba(232,106,58,0) 72%);
        }

        .premium-card.green {
            background: linear-gradient(180deg, #f5fcf7 0%, #ffffff 100%);
            border-color: #d8f0de;
        }

        .premium-card.green::after {
            background: radial-gradient(circle, rgba(22,101,52,0.22) 0%, rgba(22,101,52,0) 72%);
        }

        .premium-card.blue {
            background: linear-gradient(180deg, #f7faff 0%, #ffffff 100%);
            border-color: #dbe7ff;
        }

        .premium-card.blue::after {
            background: radial-gradient(circle, rgba(29,78,216,0.20) 0%, rgba(29,78,216,0) 72%);
        }

        .premium-card.violet {
            background: linear-gradient(180deg, #f8f7ff 0%, #ffffff 100%);
            border-color: #e3deff;
        }

        .premium-card.violet::after {
            background: radial-gradient(circle, rgba(91,75,209,0.20) 0%, rgba(91,75,209,0) 72%);
        }

        .premium-label {
            position: relative;
            z-index: 1;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .premium-value {
            position: relative;
            z-index: 1;
            font-size: 34px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 12px;
            letter-spacing: -0.03em;
        }

        .premium-card.orange .premium-value { color: #c9552a; }
        .premium-card.green .premium-value { color: #166534; }
        .premium-card.blue .premium-value { color: #1d4ed8; }
        .premium-card.violet .premium-value { color: #5b4bd1; }

        .premium-desc {
            position: relative;
            z-index: 1;
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
        }

        .summary-head {
            padding: 24px 24px 0;
        }

        .summary-title {
            margin: 0 0 6px;
            font-size: 28px;
            font-weight: 800;
            color: #111827;
            letter-spacing: -0.02em;
        }

        .summary-subtitle {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.8;
        }

        .stats-grid {
            padding: 20px 24px 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .stats-card {
            border-radius: 22px;
            padding: 20px;
            border: 1px solid #e8edf4;
            background: rgba(255,255,255,0.92);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            min-height: 138px;
        }

        .stats-card.orange {
            background: linear-gradient(180deg, #fff9f6 0%, #ffffff 100%);
            border-color: #f4ddd0;
        }

        .stats-card.green {
            background: linear-gradient(180deg, #f5fcf7 0%, #ffffff 100%);
            border-color: #d8f0de;
        }

        .stats-card.blue {
            background: linear-gradient(180deg, #f7faff 0%, #ffffff 100%);
            border-color: #dbe7ff;
        }

        .stats-card.violet {
            background: linear-gradient(180deg, #f8f7ff 0%, #ffffff 100%);
            border-color: #e3deff;
        }

        .stats-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin-bottom: 16px;
        }

        .stats-value {
            font-size: 34px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 12px;
        }

        .orange .stats-value { color: #c9552a; }
        .green .stats-value { color: #166534; }
        .blue .stats-value { color: #1d4ed8; }
        .violet .stats-value { color: #5b4bd1; }

        .stats-desc {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
        }

        .dual-grid {
            padding: 18px 24px 24px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .info-panel {
            border: 1px solid #e8edf4;
            background: #ffffff;
            border-radius: 22px;
            padding: 18px;
        }

        .info-panel-title {
            margin: 0 0 14px;
            font-size: 16px;
            font-weight: 800;
            color: #111827;
        }

        .info-list {
            display: grid;
            gap: 12px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            font-size: 14px;
            line-height: 1.6;
            color: #374151;
        }

        .info-row strong {
            color: #111827;
        }

        .chart-card {
            margin: 0 24px 24px;
            border: 1px solid #e8edf4;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
            border-radius: 22px;
            padding: 18px;
        }

        .chart-title {
            margin: 0 0 6px;
            font-size: 16px;
            font-weight: 800;
            color: #111827;
        }

        .chart-subtitle {
            margin: 0 0 16px;
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
        }

        .chart-area {
            position: relative;
            height: 280px;
            border-radius: 18px;
            background:
                linear-gradient(to top, rgba(29,78,216,0.02), rgba(29,78,216,0.00)),
                repeating-linear-gradient(
                    to top,
                    #eef2f7 0,
                    #eef2f7 1px,
                    transparent 1px,
                    transparent 56px
                );
            border: 1px solid #edf1f6;
            padding: 16px 18px 18px;
            overflow: hidden;
        }

        .chart-svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .chart-footer {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-top: 14px;
        }

        .chart-legend {
            padding: 10px 12px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #e8edf4;
            font-size: 12px;
            color: #4b5563;
            line-height: 1.6;
        }

        .chart-legend strong {
            display: block;
            font-size: 13px;
            color: #111827;
            margin-bottom: 2px;
        }

        .table-card {
            margin: 0 24px 24px;
            border: 1px solid #e8edf4;
            background: #ffffff;
            border-radius: 22px;
            overflow: hidden;
        }

        .table-head {
            padding: 18px 18px 0;
        }

        .table-title {
            margin: 0 0 6px;
            font-size: 16px;
            font-weight: 800;
            color: #111827;
        }

        .table-subtitle {
            margin: 0 0 16px;
            font-size: 13px;
            color: #6b7280;
            line-height: 1.7;
        }

        .table-wrap {
            overflow-x: auto;
            padding: 0 18px 18px;
        }

        table {
            width: 100%;
            min-width: 840px;
            border-collapse: collapse;
        }

        thead th {
            text-align: left;
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 14px 12px;
            background: #f8fafc;
            border-bottom: 1px solid #e8edf4;
            white-space: nowrap;
        }

        tbody td {
            padding: 14px 12px;
            border-bottom: 1px solid #edf1f6;
            vertical-align: top;
            font-size: 14px;
            color: #111827;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .td-strong {
            font-weight: 800;
            color: #111827;
        }

        .bottom-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            padding: 0 24px 24px;
        }

        .bottom-bar {
            padding: 15px 16px;
            border-radius: 18px;
            background: #eef2ff;
            color: #3730a3;
            border: 1px solid #dbe3ff;
            font-weight: 700;
            font-size: 14px;
        }

        .empty-state {
            padding: 18px;
            border-radius: 16px;
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fed7aa;
            font-size: 14px;
            font-weight: 700;
            margin-top: 8px;
        }


        .notification-card {
            background: linear-gradient(135deg, #ffffff 0%, #fff7f2 100%);
            border: 1px solid #f0d8cb;
            border-radius: 28px;
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08);
            padding: 22px;
        }

        .notification-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .notification-title {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 800;
            color: #111827;
        }

        .notification-subtitle {
            margin: 0;
            color: #6b7280;
            font-size: 14px;
            line-height: 1.7;
        }

        .notification-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 14px;
            border-radius: 999px;
            background: #fff1ea;
            color: #c9552a;
            border: 1px solid #f4c7b6;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .notification-list {
            display: grid;
            gap: 12px;
        }

        .notification-item {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            align-items: center;
            padding: 16px;
            border-radius: 20px;
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
        }

        .notification-badge {
            display: inline-flex;
            width: fit-content;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #111827;
            color: white;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .notification-badge.void {
            background: #b91c1c;
        }

        .notification-badge.reprint {
            background: #1d4ed8;
        }

        .notification-message {
            margin: 0 0 6px;
            font-size: 14px;
            color: #111827;
            line-height: 1.7;
            font-weight: 700;
        }

        .notification-meta {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.6;
        }

        .notification-empty {
            padding: 18px;
            border-radius: 18px;
            background: #f8fafc;
            border: 1px solid #e8edf4;
            color: #6b7280;
            font-size: 14px;
            font-weight: 700;
        }

        @media (max-width: 1280px) {
            .filter-grid,
            .premium-grid,
            .stats-grid,
            .dual-grid {
                grid-template-columns: 1fr 1fr;
            }

            .filter-actions {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 780px) {
            .dashboard-title {
                font-size: 32px;
            }

            .filter-grid,
            .premium-grid,
            .stats-grid,
            .dual-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                width: 100%;
            }

            .filter-actions .btn,
            .bottom-actions .btn {
                width: 100%;
            }

            .chart-card,
            .table-card {
                margin-left: 18px;
                margin-right: 18px;
            }

            .bottom-actions {
                padding-left: 18px;
                padding-right: 18px;
            }
        }

        .approval-pin-card {
            border-radius: 22px;
            background: rgba(255,255,255,0.94);
            border: 1px solid #e8edf4;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
            padding: 18px;
            margin-bottom: 18px;
        }

        .approval-pin-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .approval-pin-title {
            font-size: 18px;
            font-weight: 900;
            color: #111827;
        }

        .approval-pin-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: end;
        }

        .approval-pin-result {
            margin-top: 14px;
            padding: 14px 16px;
            border-radius: 16px;
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #166534;
            font-weight: 900;
            display: grid;
            gap: 4px;
        }

        .approval-pin-code {
            font-size: 30px;
            letter-spacing: 0.18em;
            color: #111827;
        }

    
        .dashboard-topbar {
            position: relative;
        }

        .dashboard-topbar-actions {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .notification-bell-btn {
            position: relative;
            width: 46px;
            height: 46px;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            background: rgba(255,255,255,0.92);
            color: #111827;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.07);
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .notification-bell-btn:hover {
            border-color: #f26b3a;
            box-shadow: 0 12px 28px rgba(242, 107, 58, 0.16);
            transform: translateY(-1px);
        }

        .notification-bell-icon {
            width: 20px;
            height: 20px;
            color: #111827;
        }

        .notification-bell-badge {
            position: absolute;
            top: -7px;
            right: -7px;
            min-width: 22px;
            height: 22px;
            padding: 0 6px;
            border-radius: 999px;
            background: #ef4444;
            color: #ffffff;
            border: 3px solid #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 900;
        }

        .notification-drawer {
            position: fixed !important;
            top: 96px;
            right: 28px;
            z-index: 9999;
            width: min(760px, calc(100vw - 56px));
            max-height: calc(100vh - 132px);
            overflow: auto;
            display: none;
            margin: 0 !important;
        }

        .notification-drawer.active {
            display: block;
        }

        .notification-drawer-backdrop {
            position: fixed;
            inset: 0;
            z-index: 9998;
            background: rgba(15, 23, 42, 0.18);
            display: none;
        }

        .notification-drawer-backdrop.active {
            display: block;
        }

        @media (max-width: 780px) {
            .notification-drawer {
                top: 84px;
                left: 16px;
                right: 16px;
                width: auto;
                max-height: calc(100vh - 110px);
            }
        }

    
        .topbar-mini-btn {
            height: 46px;
            padding: 0 16px;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            background: #ffffff;
            color: #111827;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 900;
            text-decoration: none;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.07);
        }

        .topbar-mini-btn:hover {
            border-color: #f26b3a;
            color: #c9552a;
        }

        .topbar-logout-form {
            display: inline-flex;
            margin: 0;
        }

        .approval-history-drawer {
            position: fixed !important;
            top: 96px;
            right: 28px;
            z-index: 9999;
            width: min(820px, calc(100vw - 56px));
            max-height: calc(100vh - 132px);
            overflow: auto;
            display: none;
            margin: 0 !important;
            background: linear-gradient(135deg, #ffffff 0%, #fff7f2 100%);
            border: 1px solid #f0d8cb;
            border-radius: 28px;
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.24);
            padding: 22px;
        }

        .approval-history-drawer.active {
            display: block !important;
        }

        .approval-history-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 16px;
        }

        .approval-history-title {
            margin: 0;
            color: #111827;
            font-size: 22px;
            font-weight: 900;
        }

        .approval-history-subtitle {
            margin-top: 5px;
            color: #6b7280;
            font-size: 13px;
            font-weight: 700;
        }

        .approval-history-close {
            width: 38px;
            height: 38px;
            border: 0;
            border-radius: 14px;
            background: #f3f4f6;
            color: #111827;
            font-size: 22px;
            font-weight: 900;
            cursor: pointer;
        }

        .approval-history-list {
            display: grid;
            gap: 12px;
        }

        .approval-history-item {
            padding: 14px;
            border-radius: 18px;
            background: rgba(255,255,255,0.92);
            border: 1px solid #e8edf4;
        }

        .approval-history-empty {
            padding: 18px;
            border-radius: 18px;
            background: #f8fafc;
            border: 1px solid #e8edf4;
            color: #6b7280;
            font-size: 14px;
            font-weight: 700;
        }

        .mobile-quick-actions {
            display: none;
        }

        .mobile-quick-actions-title {
            margin: 0;
            font-size: 15px;
            font-weight: 900;
            color: #111827;
        }

        .mobile-quick-actions-subtitle {
            margin: 4px 0 14px;
            font-size: 12px;
            line-height: 1.6;
            color: #6b7280;
            font-weight: 700;
        }

        .mobile-quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .mobile-quick-action-btn {
            min-height: 54px;
            width: 100%;
            border: 0;
            border-radius: 16px;
            padding: 10px 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 900;
            line-height: 1.2;
            cursor: pointer;
            color: #ffffff;
            background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
            box-shadow: 0 12px 22px rgba(15,23,42,0.12);
        }

        .mobile-quick-action-btn.brand {
            background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        }

        .mobile-quick-action-btn.soft {
            background: #f3f4f6;
            color: #111827;
            box-shadow: none;
            border: 1px solid #e5e7eb;
        }

        .mobile-quick-action-btn.green {
            background: linear-gradient(135deg, #166534 0%, #15803d 100%);
        }

        .mobile-quick-action-btn:disabled {
            cursor: not-allowed;
            opacity: 0.78;
        }

        .quick-pin-form {
            margin: 0;
            display: block;
        }

        @media (max-width: 780px) {
            .approval-history-drawer {
                top: 84px;
                left: 16px;
                right: 16px;
                width: auto;
                max-height: calc(100vh - 110px);
            }

            .dashboard-topbar-actions {
                flex-wrap: wrap;
                justify-content: flex-end;
            }
        }

    
        .approval-history-filter {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            padding: 12px;
            border-radius: 18px;
            background: rgba(255,255,255,0.78);
            border: 1px solid #e8edf4;
            margin-bottom: 14px;
        }

        .approval-history-filter .field {
            display: grid;
            gap: 6px;
        }

        .approval-history-filter label {
            font-size: 11px;
            font-weight: 900;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .approval-history-filter input,
        .approval-history-filter select {
            width: 100%;
            min-height: 38px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            padding: 0 10px;
            font-size: 13px;
            font-weight: 800;
            color: #111827;
            background: #ffffff;
        }

        .approval-history-filter-actions {
            display: flex;
            align-items: end;
            gap: 8px;
        }

        .approval-history-filter-actions .btn {
            min-height: 38px;
            padding: 0 12px;
            white-space: nowrap;
        }

        @media (max-width: 780px) {
            .approval-history-filter {
                grid-template-columns: 1fr;
            }

            .approval-history-filter-actions {
                align-items: stretch;
                flex-direction: column;
            }
        }


        /* MOBILE_DASHBOARD_POLISH_V1 */
        @media (max-width: 780px) {
            .dashboard-shell {
                gap: 14px;
            }

            .dashboard-topbar {
                gap: 10px;
            }

            .dashboard-title {
                font-size: 26px;
                line-height: 1.1;
            }

            .dashboard-topbar-actions {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                justify-content: stretch;
            }

            .dashboard-topbar-actions .notification-bell-btn,
            .dashboard-topbar-actions .topbar-mini-btn,
            .dashboard-topbar-actions .topbar-logout-form,
            .dashboard-topbar-actions .topbar-logout-form button,
            .dashboard-topbar-actions .dashboard-user-pill {
                width: 100%;
            }

            .dashboard-user-pill {
                grid-column: 1 / -1;
                white-space: normal;
                text-align: center;
            }

            .mobile-quick-actions {
                display: block;
                padding: 16px;
                border-radius: 22px;
                background: linear-gradient(135deg, #ffffff 0%, #fff8f4 100%);
                border: 1px solid #f0e1d8;
                box-shadow: 0 14px 28px rgba(15,23,42,0.08);
            }

            .filter-card,
            .summary-section,
            .premium-strip {
                border-radius: 22px;
            }

            .filter-card,
            .premium-strip {
                padding: 16px;
            }

            .filter-grid,
            .premium-grid,
            .stats-grid,
            .dual-grid {
                grid-template-columns: 1fr !important;
            }

            .filter-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                width: 100%;
            }

            .filter-actions .btn {
                width: 100%;
                min-height: 46px;
            }

            .premium-card,
            .stats-card {
                min-height: 0;
                padding: 16px;
            }

            .premium-value,
            .stats-value {
                font-size: 24px;
                overflow-wrap: anywhere;
            }

            .summary-head,
            .stats-grid,
            .dual-grid,
            .bottom-actions {
                padding-left: 16px;
                padding-right: 16px;
            }

            .summary-title {
                font-size: 22px;
            }

            .chart-card,
            .table-card {
                margin-left: 16px;
                margin-right: 16px;
                border-radius: 20px;
            }

            .chart-card {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .chart-area {
                min-width: 620px;
                height: 260px;
            }

            .chart-footer {
                min-width: 620px;
                grid-template-columns: repeat(3, minmax(160px, 1fr));
            }

            .table-wrap {
                padding-left: 12px;
                padding-right: 12px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 760px;
            }

            .approval-history-drawer {
                top: 72px;
                left: 10px;
                right: 10px;
                width: auto;
                max-height: calc(100vh - 90px);
                border-radius: 22px;
                padding: 16px;
            }

            .approval-history-title {
                font-size: 18px;
            }

            .approval-history-subtitle {
                font-size: 12px;
                line-height: 1.5;
            }

            .approval-history-filter {
                grid-template-columns: 1fr !important;
            }

            .approval-history-filter-actions {
                align-items: stretch;
                flex-direction: column;
            }
        }

        @media (max-width: 420px) {
            .mobile-quick-actions-grid,
            .dashboard-topbar-actions,
            .filter-actions {
                grid-template-columns: 1fr;
            }
        }


        /* BACKOFFICE_DASHBOARD_MOBILE_FIX_V2 */
        @media (max-width: 1180px) {
            .dashboard-shell,
            .dashboard-topbar,
            .filter-card,
            .premium-strip,
            .summary-section,
            .mobile-quick-actions {
                width: 100%;
                max-width: 100%;
                overflow-x: hidden;
            }

            .dashboard-shell {
                gap: 16px;
            }

            .dashboard-topbar {
                display: grid;
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .dashboard-title {
                font-size: 34px;
                line-height: 1.08;
                letter-spacing: -0.04em;
                margin-bottom: 0;
                overflow-wrap: anywhere;
            }

            .dashboard-topbar-actions {
                width: 100%;
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                align-items: stretch;
                justify-content: stretch;
                overflow: hidden;
            }

            .dashboard-topbar-actions > *,
            .dashboard-topbar-actions .notification-bell-btn,
            .dashboard-topbar-actions .topbar-mini-btn,
            .dashboard-topbar-actions .topbar-logout-form,
            .dashboard-topbar-actions .topbar-logout-form button,
            .dashboard-topbar-actions .dashboard-user-pill {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                margin: 0 !important;
            }

            .dashboard-topbar-actions .notification-bell-btn,
            .dashboard-topbar-actions .topbar-mini-btn,
            .dashboard-topbar-actions .topbar-logout-form button {
                min-height: 52px;
                border-radius: 18px;
                font-size: 15px;
                justify-content: center;
                text-align: center;
            }

            .dashboard-user-pill {
                grid-column: 1 / -1;
                min-height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
                white-space: normal;
                text-align: center;
                overflow-wrap: anywhere;
            }

            .mobile-quick-actions {
                display: block !important;
                padding: 18px;
                border-radius: 24px;
                background: linear-gradient(135deg, #ffffff 0%, #fff8f4 100%);
                border: 1px solid #f0e1d8;
                box-shadow: 0 14px 28px rgba(15,23,42,0.08);
            }

            .mobile-quick-actions-title {
                font-size: 20px;
                line-height: 1.2;
            }

            .mobile-quick-actions-subtitle {
                max-width: 100%;
                overflow-wrap: anywhere;
                word-break: normal;
            }

            .mobile-quick-actions-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
                width: 100%;
                max-width: 100%;
                overflow: hidden;
            }

            .mobile-quick-action-btn,
            .quick-pin-form {
                width: 100%;
                max-width: 100%;
                min-width: 0;
            }

            .mobile-quick-action-btn {
                min-height: 58px;
                border-radius: 18px;
                font-size: 15px;
                text-align: center;
                overflow-wrap: anywhere;
            }

            .filter-card,
            .premium-strip {
                padding: 18px;
                border-radius: 24px;
            }

            .filter-title,
            .summary-title {
                font-size: 28px;
                line-height: 1.1;
            }

            .filter-grid,
            .premium-grid,
            .stats-grid,
            .dual-grid {
                grid-template-columns: 1fr !important;
            }

            .field input,
            .field select {
                min-height: 56px;
                font-size: 16px;
                border-radius: 18px;
            }

            .filter-actions {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                width: 100%;
            }

            .filter-actions .btn {
                width: 100%;
                min-height: 52px;
                font-size: 15px;
                border-radius: 18px;
                margin: 0 !important;
            }

            .premium-card,
            .stats-card,
            .info-panel {
                min-height: 0;
                padding: 18px;
                border-radius: 22px;
            }

            .premium-value,
            .stats-value {
                font-size: 28px;
                line-height: 1.1;
                overflow-wrap: anywhere;
            }

            .summary-head,
            .stats-grid,
            .dual-grid,
            .bottom-actions {
                padding-left: 18px;
                padding-right: 18px;
            }

            .chart-card,
            .table-card {
                margin-left: 18px;
                margin-right: 18px;
                border-radius: 22px;
                max-width: calc(100% - 36px);
            }

            .chart-card {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .chart-area {
                min-width: 680px;
                height: 280px;
            }

            .chart-footer {
                min-width: 680px;
                grid-template-columns: repeat(3, minmax(180px, 1fr));
            }

            .table-wrap {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            table {
                min-width: 760px;
            }

            .approval-history-drawer {
                top: 88px !important;
                left: 14px !important;
                right: 14px !important;
                width: auto !important;
                max-width: none !important;
                max-height: calc(100vh - 108px) !important;
                border-radius: 24px;
                padding: 18px;
            }
        }

        @media (max-width: 560px) {
            .dashboard-title {
                font-size: 30px;
            }

            .dashboard-topbar-actions,
            .mobile-quick-actions-grid,
            .filter-actions {
                grid-template-columns: 1fr !important;
            }

            .filter-title,
            .summary-title {
                font-size: 26px;
            }

            .chart-card,
            .table-card {
                margin-left: 12px;
                margin-right: 12px;
                max-width: calc(100% - 24px);
            }

            .summary-head,
            .stats-grid,
            .dual-grid,
            .bottom-actions {
                padding-left: 12px;
                padding-right: 12px;
            }
        }


        /* MOBILE_QUICK_ACTIONS_ELEGANT_V3 */
        @media (max-width: 1180px) {
            .dashboard-topbar-actions {
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                padding: 8px;
                border-radius: 28px;
                background: rgba(255,255,255,0.70);
                border: 1px solid rgba(229,231,235,0.90);
                box-shadow: inset 0 1px 0 rgba(255,255,255,0.70);
            }

            .dashboard-topbar-actions .notification-bell-btn,
            .dashboard-topbar-actions .topbar-mini-btn,
            .dashboard-topbar-actions .topbar-logout-form button,
            .dashboard-topbar-actions .dashboard-user-pill {
                min-height: 48px !important;
                border-radius: 22px !important;
                box-shadow: none !important;
                font-size: 14px !important;
                background: #ffffff !important;
                color: #111827 !important;
                border: 1px solid #e8edf4 !important;
            }

            .dashboard-topbar-actions .notification-bell-btn {
                font-size: 18px !important;
            }

            .dashboard-topbar-actions .dashboard-user-pill {
                grid-column: 1 / -1;
                background: linear-gradient(135deg, #fff7ed 0%, #ffffff 100%) !important;
                color: #374151 !important;
            }

            .mobile-quick-actions {
                position: relative;
                overflow: hidden !important;
                padding: 22px !important;
                border-radius: 30px !important;
                background:
                    radial-gradient(circle at top right, rgba(232,106,58,0.16), transparent 34%),
                    linear-gradient(135deg, #ffffff 0%, #fffaf7 52%, #ffffff 100%) !important;
                border: 1px solid #f1d8cb !important;
                box-shadow: 0 20px 46px rgba(15,23,42,0.10) !important;
            }

            .mobile-quick-actions::after {
                content: "";
                position: absolute;
                right: -46px;
                top: -52px;
                width: 150px;
                height: 150px;
                border-radius: 999px;
                background: rgba(232,106,58,0.10);
                pointer-events: none;
            }

            .mobile-quick-actions-head {
                position: relative;
                z-index: 1;
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 14px;
                margin-bottom: 18px;
            }

            .mobile-quick-eyebrow {
                display: inline-flex;
                align-items: center;
                width: fit-content;
                margin-bottom: 8px;
                padding: 7px 10px;
                border-radius: 999px;
                background: #fff3eb;
                border: 1px solid #f1d8cb;
                color: #c9552a;
                font-size: 11px;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: 0.07em;
            }

            .mobile-quick-actions-title {
                font-size: 28px !important;
                line-height: 1.05 !important;
                letter-spacing: -0.04em;
                margin: 0 !important;
            }

            .mobile-quick-actions-subtitle {
                margin: 8px 0 0 !important;
                font-size: 14px !important;
                line-height: 1.65 !important;
                max-width: 520px;
                color: #6b7280;
            }

            .mobile-quick-badge {
                position: relative;
                z-index: 1;
                flex-shrink: 0;
                padding: 9px 12px;
                border-radius: 999px;
                background: #111827;
                color: #ffffff;
                font-size: 12px;
                font-weight: 900;
                white-space: nowrap;
                box-shadow: 0 12px 24px rgba(15,23,42,0.16);
            }

            .mobile-quick-actions-grid {
                position: relative;
                z-index: 1;
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 12px !important;
            }

            .mobile-quick-action-card {
                min-height: 86px;
                width: 100%;
                max-width: 100%;
                border: 1px solid #e8edf4;
                border-radius: 24px;
                padding: 14px;
                display: flex;
                align-items: center;
                justify-content: flex-start;
                gap: 12px;
                text-decoration: none;
                cursor: pointer;
                background: rgba(255,255,255,0.92);
                color: #111827;
                box-shadow: 0 12px 24px rgba(15,23,42,0.06);
                text-align: left;
                font-family: Arial, sans-serif;
            }

            .mobile-quick-action-card:disabled {
                cursor: not-allowed;
                opacity: 0.88;
            }

            .mobile-quick-action-card.brand {
                background: linear-gradient(135deg, #fff3eb 0%, #ffffff 100%);
                border-color: #f1d8cb;
            }

            .mobile-quick-action-card.dark {
                background: linear-gradient(135deg, #111827 0%, #1f2937 100%);
                border-color: #111827;
                color: #ffffff;
            }

            .mobile-quick-action-card.green {
                background: linear-gradient(135deg, #ecfdf3 0%, #ffffff 100%);
                border-color: #ccebd5;
            }

            .mobile-quick-action-card.soft,
            .mobile-quick-action-card.muted {
                background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            }

            .mobile-quick-icon {
                width: 44px;
                height: 44px;
                flex-shrink: 0;
                border-radius: 16px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                font-weight: 900;
                background: rgba(17,24,39,0.06);
                color: #111827;
            }

            .mobile-quick-action-card.dark .mobile-quick-icon {
                background: rgba(255,255,255,0.14);
                color: #ffffff;
            }

            .mobile-quick-action-card.brand .mobile-quick-icon {
                background: #e86a3a;
                color: #ffffff;
            }

            .mobile-quick-action-card.green .mobile-quick-icon {
                background: #166534;
                color: #ffffff;
            }

            .mobile-quick-copy {
                display: grid;
                gap: 4px;
                min-width: 0;
            }

            .mobile-quick-copy strong {
                font-size: 16px;
                line-height: 1.1;
                font-weight: 900;
                letter-spacing: -0.01em;
            }

            .mobile-quick-copy small {
                font-size: 12px;
                line-height: 1.35;
                color: #6b7280;
                font-weight: 800;
            }

            .mobile-quick-action-card.dark .mobile-quick-copy small {
                color: rgba(255,255,255,0.72);
            }

            .quick-pin-form {
                width: 100%;
                max-width: 100%;
                margin: 0;
            }
        }


            .desktop-approval-pin-card {
                margin: 0 0 18px;
                display: block;
            }

            @media (max-width: 900px) {
                .desktop-approval-pin-card {
                    display: none;
                }
            }

        @media (max-width: 620px) {
            .dashboard-topbar-actions {
                grid-template-columns: 1fr 1fr !important;
            }

            .mobile-quick-actions-head {
                display: grid;
                grid-template-columns: 1fr;
            }

            .mobile-quick-badge {
                width: fit-content;
            }

            .mobile-quick-actions-grid {
                grid-template-columns: 1fr !important;
            }
        }

    </style>

    <div class="notification-drawer-backdrop" id="notification-drawer-backdrop"></div>

    <div class="dashboard-shell">
        <div class="dashboard-topbar">
            <div>
                <h1 class="dashboard-title">Back Office Dashboard</h1>

            </div>

            <div class="dashboard-topbar-actions">


                <button type="button" class="notification-bell-btn" id="notification-bell-btn" aria-label="Open notifications">
                    🔔
                    @if((int) ($unreadNotificationCount ?? 0) > 0)
                        <span class="notification-bell-badge">{{ number_format((int) ($unreadNotificationCount ?? 0), 0, ',', '.') }}</span>
                    @endif
                </button>
                <button type="button" class="topbar-mini-btn" id="approval-history-btn">
                    History
                </button>

                <form method="POST" action="{{ route('logout') }}" class="topbar-logout-form">
                    @csrf
                    <button type="submit" class="topbar-mini-btn">
                        Logout
                    </button>
                </form>

                <div class="dashboard-user-pill">
                {{ $user->name }} • {{ $user->role->name ?? '-' }}
            </div>


            </div>
        </div>



        @if(session('approval_pin'))
            <div class="approval-pin-card desktop-approval-pin-card">
                <div class="approval-pin-title">PIN Approval</div>
                <div class="approval-pin-code">{{ session('approval_pin.pin_code') }}</div>
                <div style="color:#6b7280; font-size:13px; font-weight:800;">
                    {{ strtoupper(session('approval_pin.purpose')) }}
                    • {{ session('approval_pin.transaction_number') }}
                    • {{ session('approval_pin.outlet_name') }}
                    • Expired: {{ session('approval_pin.expires_at') }}
                </div>
            </div>
        @endif

        <div class="mobile-quick-actions">
            <div class="mobile-quick-actions-head">
                <div>
                    @if(session('approval_pin'))
                                <div class="approval-pin-card">
                                    <div class="approval-pin-title">PIN Approval</div>
                                    <div class="approval-pin-code">{{ session('approval_pin.pin_code') }}</div>
                                    <div style="color:#6b7280; font-size:13px; font-weight:800;">
                                        {{ strtoupper(session('approval_pin.purpose')) }}
                                        • {{ session('approval_pin.transaction_number') }}
                                        • {{ session('approval_pin.outlet_name') }}
                                        • Expired: {{ session('approval_pin.expires_at') }}
                                    </div>
                                </div>
                            @endif
                    
                                        <div class="mobile-quick-eyebrow">Mobile Workspace</div>
                    <h2 class="mobile-quick-actions-title">Aksi Cepat</h2>
                    <p class="mobile-quick-actions-subtitle">
                        Fitur utama untuk approval, transaksi, dan stok dari HP.
                    </p>
                </div>
                <div class="mobile-quick-badge">
                    {{ number_format((int) (($pendingApprovalNotifications ?? collect())->count()), 0, ',', '.') }} request
                </div>
            </div>

            <div class="mobile-quick-actions-grid">
                @if(($pendingApprovalNotifications ?? collect())->count())
                    @php
                        $firstPendingApproval = ($pendingApprovalNotifications ?? collect())->first();
                    @endphp

                    <form method="POST" action="{{ route('backoffice.approval-pins.generate') }}" class="quick-pin-form">
                        @csrf
                        <input type="hidden" name="notification_id" value="{{ $firstPendingApproval->id }}">
                        <button type="submit" class="mobile-quick-action-card brand">
                            <span class="mobile-quick-icon">#</span>
                            <span class="mobile-quick-copy">
                                <strong>Generate PIN</strong>
                                <small>Buat approval kasir</small>
                            </span>
                        </button>
                    </form>
                @else
                    <button type="button" class="mobile-quick-action-card muted" disabled>
                        <span class="mobile-quick-icon">✓</span>
                        <span class="mobile-quick-copy">
                            <strong>Tidak Ada PIN</strong>
                            <small>Belum ada request</small>
                        </span>
                    </button>
                @endif

                <button type="button" class="mobile-quick-action-card dark" id="mobile-approval-history-btn">
                    <span class="mobile-quick-icon">⌕</span>
                    <span class="mobile-quick-copy">
                        <strong>History PIN</strong>
                        <small>Void & reprint</small>
                    </span>
                </button>

                <a href="{{ route('backoffice.transactions.index') }}" class="mobile-quick-action-card green">
                    <span class="mobile-quick-icon">↗</span>
                    <span class="mobile-quick-copy">
                        <strong>Transactions</strong>
                        <small>Lihat transaksi</small>
                    </span>
                </a>

                <a href="{{ route('backoffice.stock-balances.index') }}" class="mobile-quick-action-card soft">
                    <span class="mobile-quick-icon">□</span>
                    <span class="mobile-quick-copy">
                        <strong>Inventory</strong>
                        <small>Cek stok outlet</small>
                    </span>
                </a>
            </div>
        </div>

        <div class="approval-history-drawer" id="approval-history-drawer">
            <div class="approval-history-head">
                <div>
                    <h2 class="approval-history-title">History Void & Reprint</h2>
                    <div class="approval-history-subtitle">
                        Riwayat request PIN void dan reprint beserta status pemakaian PIN untuk KPI outlet.
                    </div>
                </div>
                <button type="button" class="approval-history-close" id="approval-history-close">×</button>
            </div>

            <form method="GET" action="{{ route('backoffice.index') }}" class="approval-history-filter">
                <input type="hidden" name="outlet_id" value="{{ $filters['outlet_id'] ?? '' }}">
                <input type="hidden" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
                <input type="hidden" name="date_to" value="{{ $filters['date_to'] ?? '' }}">

                <div class="field">
                    <label for="history_date_from">Dari Tanggal</label>
                    <input
                        type="date"
                        id="history_date_from"
                        name="history_date_from"
                        value="{{ $approvalHistoryFilters['date_from'] ?? '' }}"
                    >
                </div>

                <div class="field">
                    <label for="history_date_to">Sampai Tanggal</label>
                    <input
                        type="date"
                        id="history_date_to"
                        name="history_date_to"
                        value="{{ $approvalHistoryFilters['date_to'] ?? '' }}"
                    >
                </div>

                <div class="field">
                    <label for="history_status">Status PIN</label>
                    <select id="history_status" name="history_status">
                        <option value="all" @selected(($approvalHistoryFilters['status'] ?? 'all') === 'all')>Semua Status</option>
                        <option value="used" @selected(($approvalHistoryFilters['status'] ?? 'all') === 'used')>Berhasil Digunakan</option>
                        <option value="waiting" @selected(($approvalHistoryFilters['status'] ?? 'all') === 'waiting')>Belum Digunakan</option>
                        <option value="expired" @selected(($approvalHistoryFilters['status'] ?? 'all') === 'expired')>Expired</option>
                        <option value="not_generated" @selected(($approvalHistoryFilters['status'] ?? 'all') === 'not_generated')>Belum Dibuat</option>
                    </select>
                </div>

                <div class="approval-history-filter-actions">
                    <button type="submit" class="btn btn-brand">Filter</button>
                    <a href="{{ route('backoffice.index', array_filter([
                        'outlet_id' => $filters['outlet_id'] ?? null,
                        'date_from' => $filters['date_from'] ?? null,
                        'date_to' => $filters['date_to'] ?? null,
                    ])) }}" class="btn btn-soft">Reset</a>
                </div>
            </form>

            @if(($approvalActivityHistory ?? collect())->isEmpty())
                <div class="approval-history-empty">
                    Belum ada history request PIN void / reprint.
                </div>
            @else
                <div class="approval-history-list">
                    @foreach($approvalActivityHistory as $history)
                        @php
                            $badgeClass = str_contains((string) $history->type, 'void')
                                ? 'void'
                                : 'reprint';

                            $pinStatus = $history->approval_pin_status ?? 'not_generated';
                            $pinStatusLabel = $history->approval_pin_status_label ?? 'PIN BELUM DIBUAT';

                            $pinStatusStyle = 'background:#4b5563;';
                            if ($pinStatus === 'used') {
                                $pinStatusStyle = 'background:#166534;';
                            } elseif ($pinStatus === 'waiting') {
                                $pinStatusStyle = 'background:#c9552a;';
                            } elseif ($pinStatus === 'expired') {
                                $pinStatusStyle = 'background:#991b1b;';
                            }
                        @endphp

                        <div class="approval-history-item">
                            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:8px;">
                                <div class="notification-badge {{ $badgeClass }}" style="margin-bottom:0;">
                                    {{ str_replace('_', ' ', $history->type) }}
                                </div>
                                <div class="notification-badge" style="margin-bottom:0; {{ $pinStatusStyle }}">
                                    {{ $pinStatusLabel }}
                                </div>
                            </div>

                            <p class="notification-message">
                                {{ $history->message ?? $history->title }}
                            </p>

                            <div class="notification-meta">
                                {{ $history->created_at?->format('d M Y H:i') ?? '-' }}
                                @if($history->outlet)
                                    • {{ $history->outlet->name }}
                                @endif
                                @if($history->createdBy)
                                    • Oleh {{ $history->createdBy->name }}
                                @endif
                                @if($history->approval_pin_generated_at)
                                    • PIN dibuat {{ $history->approval_pin_generated_at?->format('d M Y H:i') }}
                                @endif
                                @if($history->approval_pin_used_at)
                                    • Dipakai {{ $history->approval_pin_used_at?->format('d M Y H:i') }}
                                @endif
                                @if($history->approval_pin_used_by)
                                    • Dipakai oleh {{ $history->approval_pin_used_by }}
                                @endif
                                @if(($history->approval_pin_status ?? null) === 'expired' && $history->approval_pin_expires_at)
                                    • Expired {{ $history->approval_pin_expires_at?->format('d M Y H:i') }}
                                @endif
                            </div>

                            @if($history->sales_transaction_id)
                                <div style="margin-top:10px;">
                                    <a href="{{ route('backoffice.transactions.show', $history->sales_transaction_id) }}" class="btn btn-dark">
                                        Lihat Transaksi
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>


        <div class="filter-card">
            <h2 class="filter-title">Filter Dashboard</h2>


            <form method="GET" action="{{ route('backoffice.index') }}" class="filter-grid">
                <div class="field">
                    <label for="outlet_id">Outlet</label>
                    <select name="outlet_id" id="outlet_id">
                        <option value="">Semua Outlet</option>
                        @foreach($outletOptions as $outlet)
                            <option value="{{ $outlet->id }}" @selected((string) $filters['outlet_id'] === (string) $outlet->id)>
                                {{ $outlet->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="date_from">Date From</label>
                    <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] }}">
                </div>

                <div class="field">
                    <label for="date_to">Date To</label>
                    <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] }}">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-brand">Apply Filter</button>
                    <a href="{{ route('backoffice.index') }}" class="btn btn-soft">Reset</a>
                </div>
            </form>
        </div>





        <div class="notification-card notification-drawer" id="backoffice-notification-drawer">
            <div class="notification-head">
                <div>
                    <h2 class="notification-title">Need Action / Notifications</h2>

                </div>
                <div class="notification-count">
                    {{ number_format((int) ($unreadNotificationCount ?? 0), 0, ',', '.') }} unread
                </div>
            </div>

            @if(($backofficeNotifications ?? collect())->isEmpty())
                <div class="notification-empty">
                    Belum ada notifikasi backoffice.
                </div>
            @else
                <div class="notification-list">
                    @foreach($backofficeNotifications as $notification)
                        @php
                            $badgeClass = $notification->type === 'transaction_void'
                                ? 'void'
                                : ($notification->type === 'receipt_reprint' ? 'reprint' : '');
                        @endphp

                        <div class="notification-item">
                            <div>
                                <div class="notification-badge {{ $badgeClass }}">
                                    {{ str_replace('_', ' ', $notification->type) }}
                                </div>
                                <p class="notification-message">
                                    {{ $notification->message ?? $notification->title }}
                                </p>
                                <div class="notification-meta">
                                    {{ $notification->created_at?->format('d M Y H:i') ?? '-' }}
                                    @if($notification->outlet)
                                        • {{ $notification->outlet->name }}
                                    @endif
                                    @if($notification->createdBy)
                                        • Oleh {{ $notification->createdBy->name }}
                                    @endif
                                </div>
                            </div>

                            @if($notification->sales_transaction_id)
                                @php
                                    $pinPurpose = str_contains((string) $notification->type, 'void') ? 'void' : 'reprint';
                                @endphp
                                @if(($user ?? auth()->user())?->isFullAccessUser())
                                    @php
                                        $pinPurpose = str_contains((string) $notification->type, 'void') ? 'void' : 'reprint';
                                    @endphp

                                    <form method="POST" action="{{ route('backoffice.approval-pins.generate') }}" style="display:inline-flex; margin-right:8px;">
                                        @csrf
                                        <input type="hidden" name="notification_id" value="{{ $notification->id }}">
                                        <input type="hidden" name="sales_transaction_id" value="{{ $notification->sales_transaction_id }}">
                                        <input type="hidden" name="purpose" value="{{ $pinPurpose }}">
                                        <button type="submit" class="btn btn-brand">Generate PIN</button>
                                    </form>
                                @endif

                                <a href="{{ route('backoffice.transactions.show', $notification->sales_transaction_id) }}" class="btn btn-dark">
                                    Lihat Transaksi
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="premium-strip">
            <div class="premium-grid">
                <div class="premium-card orange">
                    <div class="premium-label">Total Sales</div>
                    <div class="premium-value">Rp {{ number_format((float) ($stats['total_sales'] ?? 0), 0, ',', '.') }}</div>
                    <div class="premium-desc">Akumulasi sales completed pada outlet dan periode yang sedang dipilih.</div>
                </div>

                <div class="premium-card green">
                    <div class="premium-label">Completed Transactions</div>
                    <div class="premium-value">{{ number_format((int) ($stats['completed_transaction_count'] ?? 0), 0, ',', '.') }}</div>
                    <div class="premium-desc">Jumlah transaksi completed yang valid untuk dibaca sebagai performa penjualan.</div>
                </div>

                <div class="premium-card blue">
                    <div class="premium-label">Items Sold</div>
                    <div class="premium-value">{{ number_format((float) ($stats['items_sold'] ?? 0), 0, ',', '.') }}</div>
                    <div class="premium-desc">Total qty item terjual dari semua transaksi completed pada filter aktif.</div>
                </div>

                <div class="premium-card violet">
                    <div class="premium-label">Average Order</div>
                    <div class="premium-value">Rp {{ number_format((float) ($stats['average_order'] ?? 0), 0, ',', '.') }}</div>
                    <div class="premium-desc">Rata-rata nilai order untuk membaca kualitas ticket size.</div>
                </div>
            </div>
        </div>

        <div class="summary-section">
            <div class="summary-head">
                <h2 class="summary-title">Transaction Summary</h2>

            </div>

            <div class="stats-grid">
                <div class="stats-card orange">
                    <div class="stats-label">Total Transactions</div>
                    <div class="stats-value">{{ number_format((int) ($stats['transaction_count'] ?? 0), 0, ',', '.') }}</div>
                    <div class="stats-desc">Semua transaksi yang masuk dalam filter aktif.</div>
                </div>

                <div class="stats-card green">
                    <div class="stats-label">Cash Sales</div>
                    <div class="stats-value">Rp {{ number_format((float) ($stats['payment_summary']['cash']['total'] ?? 0), 0, ',', '.') }}</div>
                    <div class="stats-desc">Nilai penjualan dari payment method cash.</div>
                </div>

                <div class="stats-card blue">
                    <div class="stats-label">QRIS Sales</div>
                    <div class="stats-value">Rp {{ number_format((float) ($stats['payment_summary']['qris']['total'] ?? 0), 0, ',', '.') }}</div>
                    <div class="stats-desc">Nilai penjualan dari payment method QRIS.</div>
                </div>

                <div class="stats-card violet">
                    <div class="stats-label">Transfer Sales</div>
                    <div class="stats-value">Rp {{ number_format((float) ($stats['payment_summary']['transfer']['total'] ?? 0), 0, ',', '.') }}</div>
                    <div class="stats-desc">Nilai penjualan dari payment method transfer.</div>
                </div>
            </div>

            <div class="dual-grid">
                <div class="info-panel">
                    <h3 class="info-panel-title">Status Breakdown</h3>
                    <div class="info-list">
                        <div class="info-row">
                            <span>Completed Transactions</span>
                            <strong>{{ number_format((int) ($stats['completed_transaction_count'] ?? 0), 0, ',', '.') }}</strong>
                        </div>
                        <div class="info-row">
                            <span>Void Transactions</span>
                            <strong>{{ number_format((int) ($stats['void_transaction_count'] ?? 0), 0, ',', '.') }}</strong>
                        </div>
                        <div class="info-row">
                            <span>Selected Outlet</span>
                            <strong>{{ $selectedOutletName }}</strong>
                        </div>
                        <div class="info-row">
                            <span>Selected Date Range</span>
                            <strong>{{ $filters['date_from'] }} s/d {{ $filters['date_to'] }}</strong>
                        </div>
                    </div>
                </div>

                <div class="info-panel">
                    <h3 class="info-panel-title">Payment Summary</h3>
                    <div class="info-list">
                        <div class="info-row">
                            <span>Cash</span>
                            <strong>
                                {{ number_format((int) ($stats['payment_summary']['cash']['count'] ?? 0), 0, ',', '.') }}
                                trx • Rp {{ number_format((float) ($stats['payment_summary']['cash']['total'] ?? 0), 0, ',', '.') }}
                            </strong>
                        </div>
                        <div class="info-row">
                            <span>QRIS</span>
                            <strong>
                                {{ number_format((int) ($stats['payment_summary']['qris']['count'] ?? 0), 0, ',', '.') }}
                                trx • Rp {{ number_format((float) ($stats['payment_summary']['qris']['total'] ?? 0), 0, ',', '.') }}
                            </strong>
                        </div>
                        <div class="info-row">
                            <span>Transfer</span>
                            <strong>
                                {{ number_format((int) ($stats['payment_summary']['transfer']['count'] ?? 0), 0, ',', '.') }}
                                trx • Rp {{ number_format((float) ($stats['payment_summary']['transfer']['total'] ?? 0), 0, ',', '.') }}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chart-card">
                <h3 class="chart-title">Daily Sales Area Chart</h3>


                @if(collect($dailyTransactionSummary)->count())
                    @php
                        $dailyRows = collect($dailyTransactionSummary)->values();
                        $countRows = max(1, $dailyRows->count());
                        $points = [];
                        $fillPoints = [];
                        $baseHeight = 230;
                        $leftPadding = 18;
                        $rightPadding = 18;
                        $usableWidth = 1000 - $leftPadding - $rightPadding;

                        foreach ($dailyRows as $index => $row) {
                            $x = $leftPadding + ($countRows === 1 ? ($usableWidth / 2) : ($index * ($usableWidth / ($countRows - 1))));
                            $y = 240 - (($row['total_sales'] / $maxDailySales) * 180);
                            $points[] = round($x, 2) . ',' . round($y, 2);
                            $fillPoints[] = round($x, 2) . ',' . round($y, 2);
                        }

                        $firstX = $countRows === 1 ? ($leftPadding + ($usableWidth / 2)) : $leftPadding;
                        $lastX = $countRows === 1 ? ($leftPadding + ($usableWidth / 2)) : ($leftPadding + $usableWidth);

                        $areaPolygon = $firstX . ',240 ' . implode(' ', $fillPoints) . ' ' . $lastX . ',240';
                    @endphp

                    <div class="chart-area">
                        <svg class="chart-svg" viewBox="0 0 1000 250" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="salesAreaGradient" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="rgba(29,78,216,0.30)" />
                                    <stop offset="100%" stop-color="rgba(29,78,216,0.02)" />
                                </linearGradient>
                            </defs>

                            <polygon points="{{ $areaPolygon }}" fill="url(#salesAreaGradient)"></polygon>
                            <polyline
                                points="{{ implode(' ', $points) }}"
                                fill="none"
                                stroke="#1d4ed8"
                                stroke-width="4"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            ></polyline>

                            @foreach($dailyRows as $index => $row)
                                @php
                                    $x = $countRows === 1 ? 500 : ($leftPadding + ($index * ($usableWidth / ($countRows - 1))));
                                    $y = 240 - (($row['total_sales'] / $maxDailySales) * 180);
                                @endphp
                                <circle cx="{{ $x }}" cy="{{ $y }}" r="4.5" fill="#1d4ed8"></circle>
                            @endforeach
                        </svg>
                    </div>

                    <div class="chart-footer">
                        @foreach($dailyRows->take(6) as $row)
                            <div class="chart-legend">
                                <strong>{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</strong>
                                Sales Rp {{ number_format((float) $row['total_sales'], 0, ',', '.') }}<br>
                                {{ number_format((int) $row['total_transactions'], 0, ',', '.') }} transaksi
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state">Belum ada data transaksi harian untuk filter ini.</div>
                @endif
            </div>

            <div class="table-card">
                <div class="table-head">
                    <h3 class="table-title">Daily Transaction Summary</h3>

                </div>

                <div class="table-wrap">
                    @if(collect($dailyTransactionSummary)->count())
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Total Transactions</th>
                                    <th>Completed</th>
                                    <th>Void</th>
                                    <th>Total Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dailyTransactionSummary as $row)
                                    <tr>
                                        <td class="td-strong">{{ \Carbon\Carbon::parse($row['date'])->format('d M Y') }}</td>
                                        <td>{{ number_format((int) $row['total_transactions'], 0, ',', '.') }}</td>
                                        <td>{{ number_format((int) $row['completed_transactions'], 0, ',', '.') }}</td>
                                        <td>{{ number_format((int) $row['void_transactions'], 0, ',', '.') }}</td>
                                        <td class="td-strong">Rp {{ number_format((float) $row['total_sales'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="empty-state">Belum ada data daily transaction summary.</div>
                    @endif
                </div>
            </div>

            <div class="table-card">
                <div class="table-head">
                    <h3 class="table-title">Outlet Transaction Summary</h3>

                </div>

                <div class="table-wrap">
                    @if(collect($outletTransactionSummary)->count())
                        <table>
                            <thead>
                                <tr>
                                    <th>Outlet</th>
                                    <th>Total Transactions</th>
                                    <th>Completed</th>
                                    <th>Void</th>
                                    <th>Total Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($outletTransactionSummary as $row)
                                    <tr>
                                        <td class="td-strong">{{ $row['outlet_name'] }}</td>
                                        <td>{{ number_format((int) $row['total_transactions'], 0, ',', '.') }}</td>
                                        <td>{{ number_format((int) $row['completed_transactions'], 0, ',', '.') }}</td>
                                        <td>{{ number_format((int) $row['void_transactions'], 0, ',', '.') }}</td>
                                        <td class="td-strong">Rp {{ number_format((float) $row['total_sales'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="empty-state">Belum ada data outlet transaction summary.</div>
                    @endif
                </div>
            </div>

            <div class="table-card">
                <div class="table-head">
                    <h3 class="table-title">Top Products</h3>

                </div>

                <div class="table-wrap">
                    @if(collect($topProducts)->count())
                        <table>
                            <thead>
                                <tr>
                                    <th>Product / Variant</th>
                                    <th>Qty Sold</th>
                                    <th>Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topProducts as $row)
                                    <tr>
                                        <td class="td-strong">{{ $row['name'] }}</td>
                                        <td>{{ number_format((float) $row['qty'], 0, ',', '.') }}</td>
                                        <td class="td-strong">Rp {{ number_format((float) $row['sales'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="empty-state">Belum ada data top products.</div>
                    @endif
                </div>
            </div>

            <div class="bottom-actions">
                <a href="{{ route('backoffice.print-summary', request()->query()) }}" target="_blank" class="btn btn-dark">
                    Print Summary
                </a>
                <a href="{{ route('backoffice.transactions.index', request()->query()) }}" class="btn btn-brand">
                    Buka Transactions
                </a>
            </div>
        </div>

        <div class="summary-section">
            <div class="summary-head">
                <h2 class="summary-title">Inventory Summary</h2>

            </div>

            <div class="stats-grid">
                <div class="stats-card blue">
                    <div class="stats-label">Current Stock Rows</div>
                    <div class="stats-value">{{ number_format((int) ($stats['current_stock_rows'] ?? 0), 0, ',', '.') }}</div>
                    <div class="stats-desc">Jumlah baris stock balance aktif pada scope lokasi yang sedang dilihat.</div>
                </div>

                <div class="stats-card green">
                    <div class="stats-label">Total Qty On Hand</div>
                    <div class="stats-value">{{ number_format((float) ($stats['total_qty_on_hand'] ?? 0), 0, ',', '.') }}</div>
                    <div class="stats-desc">Total qty stok current semua ingredient pada scope terpilih.</div>
                </div>

                <div class="stats-card orange">
                    <div class="stats-label">Low Stock</div>
                    <div class="stats-value">{{ number_format((int) ($stats['low_stock_count'] ?? 0), 0, ',', '.') }}</div>
                    <div class="stats-desc">Item yang stoknya masih ada, tapi sudah menyentuh minimum stock.</div>
                </div>

                <div class="stats-card violet">
                    <div class="stats-label">Out of Stock</div>
                    <div class="stats-value">{{ number_format((int) ($stats['out_of_stock_count'] ?? 0), 0, ',', '.') }}</div>
                    <div class="stats-desc">Item yang qty current-nya sudah habis pada scope terpilih.</div>
                </div>
            </div>

            <div class="dual-grid">
                <div class="info-panel">
                    <h3 class="info-panel-title">Current Stock Snapshot</h3>
                    <div class="info-list">
                        <div class="info-row">
                            <span>Active Ingredients in Scope</span>
                            <strong>{{ number_format((int) ($stats['active_ingredients_in_scope'] ?? 0), 0, ',', '.') }}</strong>
                        </div>
                        <div class="info-row">
                            <span>Selected Outlet</span>
                            <strong>{{ $selectedOutletName }}</strong>
                        </div>
                        <div class="info-row">
                            <span>Current Stock Logic</span>
                            <strong>{{ !empty($filters['outlet_id']) ? 'Outlet terpilih' : 'Semua lokasi aktif' }}</strong>
                        </div>
                        <div class="info-row">
                            <span>Inventory Page</span>
                            <a href="{{ route('backoffice.stock-balances.index') }}" style="font-weight:800; color:#c9552a; text-decoration:none;">
                                Buka Inventory Control
                            </a>
                        </div>
                    </div>
                </div>

                <div class="info-panel">
                    <h3 class="info-panel-title">Movement Summary</h3>
                    <div class="info-list">
                        <div class="info-row">
                            <span>Movement Logs</span>
                            <strong>{{ number_format((int) ($stats['stock_movement_count'] ?? 0), 0, ',', '.') }}</strong>
                        </div>
                        <div class="info-row">
                            <span>Total Qty In</span>
                            <strong>{{ number_format((float) ($stats['total_qty_in'] ?? 0), 0, ',', '.') }}</strong>
                        </div>
                        <div class="info-row">
                            <span>Total Qty Out</span>
                            <strong>{{ number_format((float) ($stats['total_qty_out'] ?? 0), 0, ',', '.') }}</strong>
                        </div>
                        <div class="info-row">
                            <span>Stock In / Adjustment / Transfer / Production</span>
                            <strong>
                                {{ (int) ($stats['movement_type_summary']['stock_in'] ?? 0) }} /
                                {{ (int) ($stats['movement_type_summary']['stock_adjustment'] ?? 0) }} /
                                {{ (int) ($stats['movement_type_summary']['transfer'] ?? 0) }} /
                                {{ (int) ($stats['movement_type_summary']['production'] ?? 0) }}
                            </strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="summary-section">
            <div class="summary-head">
                <h2 class="summary-title">Operational Snapshot</h2>

            </div>

            <div class="stats-grid" style="padding-bottom:24px;">
                <div class="stats-card orange">
                    <div class="stats-label">Master Data</div>
                    <div class="stats-value">{{ number_format($masterDataCount, 0, ',', '.') }}</div>
                    <div class="stats-desc">Outlets, warehouses, products, variants, ingredients, dan recipes aktif.</div>
                </div>

                <div class="stats-card blue">
                    <div class="stats-label">Total Shifts</div>
                    <div class="stats-value">{{ number_format((int) ($stats['shift_count'] ?? 0), 0, ',', '.') }}</div>
                    <div class="stats-desc">Total shift pada periode filter aktif.</div>
                </div>

                <div class="stats-card green">
                    <div class="stats-label">Open Shifts</div>
                    <div class="stats-value">{{ number_format((int) ($stats['open_shift_count'] ?? 0), 0, ',', '.') }}</div>
                    <div class="stats-desc">Shift yang masih berjalan.</div>
                </div>

                <div class="stats-card violet">
                    <div class="stats-label">Closed Shifts</div>
                    <div class="stats-value">{{ number_format((int) ($stats['closed_shift_count'] ?? 0), 0, ',', '.') }}</div>
                    <div class="stats-desc">Shift yang sudah ditutup.</div>
                </div>
            </div>
        </div>

        <div class="bottom-bar">
            
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const bellButton = document.getElementById('notification-bell-btn');
        const drawer = document.getElementById('backoffice-notification-drawer');
        const backdrop = document.getElementById('notification-drawer-backdrop');

        function closeNotificationDrawer() {
            if (drawer) {
                drawer.classList.remove('active');
            }

            if (backdrop) {
                backdrop.classList.remove('active');
            }
        }

        if (bellButton && drawer && backdrop) {
            bellButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                drawer.classList.toggle('active');
                backdrop.classList.toggle('active');
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', closeNotificationDrawer);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeNotificationDrawer();
            }
        });
    });
</script>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        const historyButton = document.getElementById('approval-history-btn');
        const mobileHistoryButton = document.getElementById('mobile-approval-history-btn');
        const historyDrawer = document.getElementById('approval-history-drawer');
        const historyClose = document.getElementById('approval-history-close');
        const notificationBackdrop = document.getElementById('notification-drawer-backdrop');

        function closeApprovalHistory() {
            if (historyDrawer) {
                historyDrawer.classList.remove('active');
            }

            if (notificationBackdrop) {
                notificationBackdrop.classList.remove('active');
            }
        }

        if (historyButton && historyDrawer) {
            historyButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                historyDrawer.classList.toggle('active');

                if (notificationBackdrop) {
                    notificationBackdrop.classList.toggle('active', historyDrawer.classList.contains('active'));
                }

        if (mobileHistoryButton && historyDrawer) {
            mobileHistoryButton.addEventListener('click', function (event) {
                event.preventDefault();

                historyDrawer.classList.toggle('active');

                if (notificationBackdrop) {
                    notificationBackdrop.classList.toggle('active', historyDrawer.classList.contains('active'));
                }
            });
        }
            });
        }

        if (historyClose) {
            historyClose.addEventListener('click', function (event) {
                event.preventDefault();
                closeApprovalHistory();
            });
        }

        if (notificationBackdrop) {
            notificationBackdrop.addEventListener('click', closeApprovalHistory);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeApprovalHistory();
            }
        });
    });
</script>






<script>
    document.addEventListener('DOMContentLoaded', function () {
        let isLoadingHistoryFilter = false;
        let historyFilterTimer = null;

        function getHistoryDrawer() {
            return document.getElementById('approval-history-drawer');
        }

        function getHistoryForm() {
            return document.querySelector('#approval-history-drawer .approval-history-filter');
        }

        async function applyApprovalHistoryFilter() {
            const drawer = getHistoryDrawer();
            const form = getHistoryForm();

            if (!drawer || !form || isLoadingHistoryFilter) {
                return;
            }

            isLoadingHistoryFilter = true;
            drawer.style.opacity = '0.65';

            try {
                const formData = new FormData(form);
                const url = new URL(form.action || window.location.href, window.location.origin);

                formData.forEach(function (value, key) {
                    if (value !== null && String(value).trim() !== '') {
                        url.searchParams.set(key, value);
                    } else {
                        url.searchParams.delete(key);
                    }
                });

                url.searchParams.set('_approval_history_filter', Date.now().toString());

                const response = await fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                    },
                    cache: 'no-store',
                });

                if (!response.ok) {
                    return;
                }

                const html = await response.text();
                const parser = new DOMParser();
                const freshDocument = parser.parseFromString(html, 'text/html');
                const freshDrawer = freshDocument.getElementById('approval-history-drawer');

                if (freshDrawer) {
                    drawer.innerHTML = freshDrawer.innerHTML;
                    drawer.classList.add('active');

                    // Update URL supaya filter state tetap kebaca kalau reload manual.
                    url.searchParams.delete('_approval_history_filter');
                    window.history.replaceState({}, '', url.toString());
                }
            } catch (error) {
                // Silent supaya dashboard tidak terganggu.
            } finally {
                drawer.style.opacity = '';
                isLoadingHistoryFilter = false;
            }
        }

        function scheduleApprovalHistoryFilter() {
            clearTimeout(historyFilterTimer);
            historyFilterTimer = setTimeout(applyApprovalHistoryFilter, 250);
        }

        document.addEventListener('change', function (event) {
            if (event.target.closest('#approval-history-drawer .approval-history-filter')) {
                scheduleApprovalHistoryFilter();
            }
        });

        document.addEventListener('input', function (event) {
            if (event.target.closest('#approval-history-drawer .approval-history-filter input[type="date"]')) {
                scheduleApprovalHistoryFilter();
            }
        });

        document.addEventListener('submit', function (event) {
            if (event.target.matches('#approval-history-drawer .approval-history-filter')) {
                event.preventDefault();
                applyApprovalHistoryFilter();
            }
        });

        document.addEventListener('click', function (event) {
            const resetLink = event.target.closest('#approval-history-drawer .approval-history-filter-actions a');

            if (!resetLink) {
                return;
            }

            event.preventDefault();

            fetch(resetLink.href, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                },
                cache: 'no-store',
            })
                .then(response => response.text())
                .then(html => {
                    const drawer = getHistoryDrawer();
                    const parser = new DOMParser();
                    const freshDocument = parser.parseFromString(html, 'text/html');
                    const freshDrawer = freshDocument.getElementById('approval-history-drawer');

                    if (drawer && freshDrawer) {
                        drawer.innerHTML = freshDrawer.innerHTML;
                        drawer.classList.add('active');
                        window.history.replaceState({}, '', resetLink.href);
                    }
                })
                .catch(() => {});
        });
    });
</script>

@endsection