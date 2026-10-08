{{--
    Back Office button design system (single source of truth).

    Included once by layouts/app.blade.php AFTER the page content, so it wins over the older per-page
    `.btn` rules at equal importance (the `body` prefix adds the little specificity needed to beat
    page rules such as `.btn-small { background: ... }`).

    Markup: <a|button class="btn btn-{variant} [btn-sm]">
      btn-primary    Tambah, Buat, Simpan, Update, Filter/Apply, Import  (ATG brand orange)
      btn-secondary  Edit, Detail, Lihat, Reset, Batal, Kembali, Dashboard, Export, Download  (neutral)
      btn-success    Aktifkan                                              (green)
      btn-warning    Nonaktifkan                                           (amber)
      btn-danger     Hapus, Hapus Permanen, Void                           (red)
    Also holds the shared dropdown outline (native <select> and the custom outlet dropdowns).
    States: :hover, :active, :focus-visible, :disabled / [aria-disabled="true"], and .is-loading / [aria-busy="true"].
--}}
<style>
    body .btn {
        --btn-bg: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%);
        --btn-bg-hover: linear-gradient(135deg, #d65a2c 0%, #e97a47 100%);
        --btn-fg: #ffffff;
        --btn-border: transparent;

        box-sizing: border-box;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 40px;
        padding: 0 16px;
        border: 1px solid var(--btn-border);
        border-radius: 12px;
        background: var(--btn-bg);
        color: var(--btn-fg);
        font-family: inherit;
        font-size: 13px;
        font-weight: 800;
        line-height: 1.2;
        text-align: center;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
        box-shadow: none;
        opacity: 1;
        transform: none;
        -webkit-appearance: none;
        appearance: none;
        transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    body .btn:hover { background: var(--btn-bg-hover); opacity: 1; transform: none; box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12); }
    body .btn:active { transform: translateY(1px); box-shadow: none; }
    body .btn:focus-visible { outline: 3px solid rgba(37, 99, 235, 0.55); outline-offset: 2px; }

    body .btn svg,
    body .btn img { flex: none; width: 1.1em; height: 1.1em; }

    /* Variants */
    body .btn.btn-secondary {
        --btn-bg: #ffffff;
        --btn-bg-hover: #f3f4f6;
        --btn-fg: #1f2937;
        --btn-border: #d1d5db;
    }

    body .btn.btn-secondary:hover { border-color: #9ca3af; }

    body .btn.btn-success {
        --btn-bg: linear-gradient(135deg, #166534 0%, #1f7a44 100%);
        --btn-bg-hover: linear-gradient(135deg, #14532d 0%, #166534 100%);
    }

    body .btn.btn-warning {
        --btn-bg: #fbbf24;
        --btn-bg-hover: #f59e0b;
        --btn-fg: #422006;
    }

    body .btn.btn-danger {
        --btn-bg: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
        --btn-bg-hover: linear-gradient(135deg, #991b1b 0%, #b91c1c 100%);
    }

    /* Size */
    body .btn.btn-sm {
        min-height: 34px;
        padding: 0 12px;
        border-radius: 10px;
        font-size: 12px;
    }

    /* Disabled */
    body .btn:disabled,
    body .btn[disabled],
    body .btn[aria-disabled="true"],
    body .btn.is-disabled {
        opacity: 0.55;
        cursor: not-allowed;
        pointer-events: none;
        transform: none;
        box-shadow: none;
    }

    /* Loading: label hidden, spinner shown; stays the same size so the layout does not jump. */
    body .btn.is-loading,
    body .btn[aria-busy="true"] {
        position: relative;
        color: transparent;
        pointer-events: none;
    }

    body .btn.is-loading::after,
    body .btn[aria-busy="true"]::after {
        content: "";
        position: absolute;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid var(--btn-fg);
        border-right-color: transparent;
        animation: bo-btn-spin .7s linear infinite;
    }

    @keyframes bo-btn-spin { to { transform: rotate(360deg); } }

    @media (prefers-reduced-motion: reduce) {
        body .btn { transition: none; }
        body .btn.is-loading::after,
        body .btn[aria-busy="true"]::after { animation-duration: 1.6s; }
    }


    /* Dropdowns: a visible outline at rest, a brand-colored one on hover/focus. */
    body select:not([multiple]):not([size]) {
        -webkit-appearance: none;
        appearance: none;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        background-color: #ffffff;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1.5l5 5 5-5' fill='none' stroke='%236b7280' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 14px center;
        padding-right: 38px;
        text-overflow: ellipsis;
        cursor: pointer;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    body select[multiple],
    body select[size] { border: 1px solid #cbd5e1; border-radius: 12px; }

    body select:hover:not(:disabled):not(:focus) { border-color: #94a3b8; }

    body select:focus,
    body select:focus-visible,
    body .outlet-dropdown-button:focus-visible,
    body .promo-outlet-button:focus-visible,
    body .variant-outlet-button:focus-visible {
        outline: none;
        border-color: #e86a3a;
        box-shadow: 0 0 0 3px rgba(232, 106, 58, 0.18);
    }

    body select:disabled { background-color: #f3f4f6; cursor: not-allowed; opacity: .7; }

    body .outlet-dropdown-button,
    body .promo-outlet-button,
    body .variant-outlet-button {
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        background-color: #ffffff;
    }

    body .outlet-dropdown-button:hover,
    body .promo-outlet-button:hover,
    body .variant-outlet-button:hover { border-color: #94a3b8; }

    body .outlet-dropdown-panel,
    body .promo-outlet-panel,
    body .variant-outlet-menu {
        border: 1px solid #cbd5e1;
        border-radius: 14px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
    }

    /* Mobile: comfortable touch targets, and a wrapped label must never overflow its button. */
    @media (max-width: 768px) {
        body .btn { min-height: 44px; white-space: normal; }
        body .btn.btn-sm { min-height: 40px; }
    }
</style>
