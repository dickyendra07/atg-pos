<style>
    /* Product Workspace. Same tokens as the existing Backoffice pages (products/index, products/edit):
       white cards with 24-30px radius and soft shadow, 14px-radius buttons, pill badges, #111827 text. */
    .pw { display: grid; gap: 18px; min-width: 0; --pw-header-h: 0px; }

    .btn {
        border: 0; cursor: pointer; min-height: 42px; padding: 0 16px; border-radius: 14px; color: white;
        font-size: 13px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center;
        justify-content: center; gap: 8px; box-shadow: 0 10px 20px rgba(15,23,42,0.10);
        transition: transform 0.15s ease, opacity 0.15s ease; white-space: nowrap; font-family: inherit;
    }
    .btn:hover { transform: translateY(-1px); opacity: 0.96; }
    .btn:disabled { opacity: .55; cursor: not-allowed; transform: none; }
    .btn-sm { min-height: 34px; padding: 0 12px; border-radius: 12px; font-size: 12px; }
    .btn-dark { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); }
    .btn-green { background: linear-gradient(135deg, #166534 0%, #1f7a44 100%); }
    .btn-blue { background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%); }
    .btn-orange { background: linear-gradient(135deg, #e86a3a 0%, #f08a57 100%); }
    .btn-light { background: #f3f4f6; color: #111827; border: 1px solid #e5e7eb; box-shadow: none; }

    .status-badge { display: inline-flex; align-items: center; justify-content: center; padding: 6px 11px; border-radius: 999px; font-size: 12px; font-weight: 800; white-space: nowrap; }
    .status-active { background: #e8fff1; color: #17663a; }
    .status-inactive { background: #fff1f1; color: #b42318; }
    .status-warn { background: #fff7ed; color: #9a3412; }
    .status-muted { background: #f3f4f6; color: #4b5563; }
    .pw-badge-sm { padding: 3px 8px; font-size: 11px; margin-left: 6px; }

    /* Header */
    .pw-header {
        position: sticky; top: 12px; z-index: 30;
        display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;
        padding: 18px 22px; background: rgba(255,255,255,0.96); border: 1px solid #e8edf4; border-radius: 24px;
        box-shadow: 0 12px 30px rgba(15,23,42,.06); backdrop-filter: blur(10px);
    }
    .pw-header-main { display: grid; gap: 8px; min-width: 0; flex: 1 1 360px; }
    .pw-kicker {
        display: inline-flex; width: fit-content; padding: 6px 11px; border-radius: 999px; background: #fff3eb;
        border: 1px solid #f1e3da; color: #c9552a; font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
    }
    .pw-title { margin: 0; font-size: 30px; line-height: 1.1; font-weight: 800; letter-spacing: -0.03em; color: #111827; overflow-wrap: anywhere; }
    .pw-meta, .pw-chip-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .pw-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; font-weight: 700; color: #374151; background: #f3f4f6; padding: 5px 9px; border-radius: 10px; }
    .pw-chip { display: inline-flex; align-items: center; padding: 6px 11px; border-radius: 999px; background: #f8fafc; border: 1px solid #e5e7eb; color: #374151; font-size: 12px; font-weight: 700; }
    .pw-chip-ok { background: #e8fff1; border-color: #ccefd8; color: #17663a; }
    .pw-chip-warn { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
    .pw-chip-locked { background: #f3f4f6; color: #6b7280; }
    .pw-header-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .pw-save-state { font-size: 12px; font-weight: 800; color: #17663a; background: #e8fff1; padding: 7px 12px; border-radius: 999px; }
    .pw-save-state.is-dirty { color: #9a3412; background: #fff7ed; }
    .pw-save-state.is-saving { color: #1d4ed8; background: #eff6ff; }

    /* Body: section rail + panels */
    .pw-body { display: grid; grid-template-columns: 210px minmax(0, 1fr); gap: 18px; align-items: start; min-width: 0; }
    .pw-nav {
        position: sticky; top: calc(var(--pw-header-h) + 24px);
        display: grid; gap: 6px; padding: 10px; background: rgba(255,255,255,0.92); border: 1px solid #e8edf4;
        border-radius: 22px; box-shadow: 0 12px 30px rgba(15,23,42,.05);
    }
    .pw-nav-link {
        display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 11px 13px; border-radius: 14px;
        color: #374151; text-decoration: none; font-size: 14px; font-weight: 700; transition: background .15s ease;
    }
    .pw-nav-link:hover { background: #f8fafc; }
    .pw-nav-link.is-active { background: linear-gradient(135deg, #111827 0%, #1f2937 100%); color: #fff; box-shadow: 0 10px 20px rgba(15,23,42,0.14); }
    .pw-nav-link:focus-visible, .pw a:focus-visible, .pw button:focus-visible, .pw-drawer button:focus-visible { outline: 2px solid #e86a3a; outline-offset: 2px; }
    .pw-dirty-dot { width: 9px; height: 9px; border-radius: 999px; background: #f97316; flex-shrink: 0; }
    .pw-panels { min-width: 0; }
    .pw-panel[hidden] { display: none; }

    /* Cards */
    .pw-card { background: #fff; border: 1px solid #e8edf4; border-radius: 24px; padding: 22px; box-shadow: 0 12px 30px rgba(15,23,42,.05); min-width: 0; }
    .pw-card + .pw-card { margin-top: 18px; }
    .pw-card-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; flex-wrap: wrap; margin-bottom: 18px; }
    .pw-card-head > div:first-child { min-width: 0; flex: 1 1 280px; }
    .pw-card-title { margin: 0; font-size: 20px; font-weight: 800; color: #111827; letter-spacing: -0.02em; }
    .pw-card-sub { margin: 6px 0 0; color: #6b7280; font-size: 14px; line-height: 1.6; }
    .pw-card-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .pw-subtitle { margin: 22px 0 10px; font-size: 13px; font-weight: 800; color: #6b7280; text-transform: uppercase; letter-spacing: .06em; }

    .pw-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin: 0; }
    .pw-facts > div { padding: 14px; border: 1px solid #eef2f7; border-radius: 16px; background: #fbfcfe; min-width: 0; }
    .pw-facts .full { grid-column: 1 / -1; }
    .pw-facts dt { font-size: 12px; font-weight: 800; color: #6b7280; margin-bottom: 4px; }
    .pw-facts dd { margin: 0; font-weight: 700; color: #111827; overflow-wrap: anywhere; white-space: pre-line; }
    .pw-facts-compact > div { padding: 10px 12px; }

    .pw-row-card { border: 1px solid #eef2f7; border-radius: 18px; padding: 14px 16px; background: #fbfcfe; }
    .pw-row-card + .pw-row-card { margin-top: 12px; }
    .pw-row-card-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
    .pw-row-actions { margin-top: 10px; display: flex; gap: 8px; flex-wrap: wrap; }
    .pw-list { list-style: none; margin: 8px 0 0; padding: 0; display: grid; gap: 8px; }
    .pw-list li { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 12px; background: #fff; border: 1px solid #eef2f7; border-radius: 14px; }

    .pw-table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #eef2f7; border-radius: 18px; }
    .pw-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .pw-table th { background: #f8fafc; color: #6b7280; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; padding: 12px; text-align: center; white-space: nowrap; }
    .pw-table td { padding: 12px; border-top: 1px solid #eef2f7; text-align: center; vertical-align: middle; color: #111827; }
    .pw-table .text-left { text-align: left; }
    .pw-num { font-variant-numeric: tabular-nums; white-space: nowrap; font-weight: 700; }
    .pw-cell-note { margin-top: 6px; font-size: 12px; color: #6b7280; line-height: 1.4; min-width: 140px; }
    .pw-yes { color: #17663a; font-weight: 900; }
    .pw-no { color: #9ca3af; font-weight: 900; }

    .pw-alert { border-radius: 16px; padding: 12px 14px; font-size: 13px; font-weight: 700; line-height: 1.6; margin-bottom: 14px; }
    .pw-alert-info { background: #eff6ff; color: #1e40af; border: 1px solid #dbe7ff; }
    .pw-alert-warn { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
    .pw-alert-danger { background: #fff1f1; color: #b42318; border: 1px solid #fecaca; }
    .pw-alert ul { margin: 6px 0 0; padding-left: 18px; }
    .pw-empty { padding: 18px; border: 1px dashed #d1d5db; border-radius: 18px; color: #6b7280; font-weight: 700; text-align: center; }
    .pw-note { margin: 10px 0 0; font-size: 13px; color: #6b7280; line-height: 1.6; }
    .pw-muted { color: #6b7280; font-weight: 600; }

    /* Forms: same field treatment as products/create + products/edit. */
    .pw-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .pw-field { min-width: 0; }
    .pw-field.full { grid-column: 1 / -1; }
    .pw label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 800; color: #111827; }
    .pw input[type="text"], .pw select, .pw textarea,
    .pw-drawer input[type="text"], .pw-drawer select {
        width: 100%; box-sizing: border-box; border: 1px solid #d1d5db; border-radius: 14px; padding: 13px;
        font-size: 14px; font-family: inherit; color: #111827; background: #fff;
    }
    .pw textarea { min-height: 110px; resize: vertical; }
    .pw input:focus, .pw select:focus, .pw textarea:focus,
    .pw-drawer input:focus, .pw-drawer select:focus { outline: 2px solid rgba(232,106,58,.35); border-color: #e86a3a; }
    .pw-field-inline { display: flex; gap: 10px; align-items: stretch; }
    .pw-field-inline select { flex: 1 1 auto; min-width: 0; }
    .pw-field-inline .btn { min-height: 46px; }
    .pw-field-error { margin-top: 6px; font-size: 12px; font-weight: 700; color: #b42318; }
    .pw-field-error[hidden] { display: none; }
    .pw .is-invalid, .pw-drawer .is-invalid { border-color: #f04438 !important; }
    .pw-link { display: inline-block; margin-top: 6px; font-size: 12px; font-weight: 700; color: #ea580c; }
    .pw-mt { margin-top: 16px; }
    .pw-mt-sm { margin-top: 8px; }

    .pw-outlet-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .pw-outlet-card { display: flex; align-items: center; gap: 12px; border: 1px solid #e5e7eb; padding: 14px; border-radius: 16px; cursor: pointer; margin: 0; min-width: 0; }
    .pw-outlet-card:hover { background: #f8fafc; }
    .pw-outlet-card input { width: auto; flex-shrink: 0; }
    .pw-outlet-name { display: block; font-weight: 800; color: #111827; }
    .pw-outlet-desc { display: block; font-size: 12px; color: #6b7280; font-weight: 600; }
    .pw-preview:not(:empty) { margin-top: 16px; }
    .pw-preview .pw-alert { margin-bottom: 0; }

    /* Nested drawer (Category). Below the shared confirm dialog (9990) and toasts (9999). */
    .pw-drawer-backdrop { position: fixed; inset: 0; z-index: 1000; background: rgba(15, 23, 42, 0.42); }
    .pw-drawer-backdrop[hidden], .pw-drawer[hidden] { display: none; }
    .pw-drawer {
        position: fixed; z-index: 1001; top: 0; right: 0; bottom: 0; width: min(560px, 90vw);
        background: #fff; border-left: 1px solid #e5e7eb; border-radius: 28px 0 0 28px;
        box-shadow: -22px 0 60px rgba(15,23,42,0.22); overflow-y: auto;
    }
    .pw-drawer form { display: flex; flex-direction: column; min-height: 100%; }
    .pw-drawer-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 22px 22px 8px; }
    .pw-drawer-head .pw-kicker { margin-bottom: 8px; }
    .pw-drawer-close { border: 1px solid #e5e7eb; background: #f3f4f6; color: #111827; width: 40px; height: 40px; border-radius: 12px; font-size: 22px; line-height: 1; cursor: pointer; flex-shrink: 0; }
    .pw-drawer-body { display: grid; gap: 16px; align-content: start; padding: 14px 22px; flex: 1 1 auto; }
    .pw-drawer-body label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 800; color: #111827; }
    .pw-drawer .pw-check, .pw .pw-check { display: inline-flex; align-items: center; gap: 10px; font-weight: 700; margin: 0; }
    .pw-drawer-foot { position: sticky; bottom: 0; display: flex; justify-content: flex-end; gap: 10px; padding: 16px 22px; border-top: 1px solid #eef2f7; background: #fff; }
    body.pw-drawer-open { overflow: hidden; }
    .pw-drawer-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .pw-drawer .pw-outlet-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .pw-drawer-loading { padding: 40px 22px; color: #6b7280; font-weight: 700; }
    .pw-mt-0 { margin-top: 0; }
    .pw-row-buttons { display: flex; gap: 6px; justify-content: center; align-items: center; flex-wrap: wrap; }
    .pw-row-buttons form { margin: 0; }
    .pw-chip-link { text-decoration: none; cursor: pointer; }
    .pw-chip-link:hover { border-color: #e86a3a; color: #c9552a; }

    /* Recipe (UX-F): per-Recipe blocks in the section, item rows in the drawer. */
    .pw-recipe { margin-top: 12px; padding: 12px 14px; background: #fff; border: 1px solid #eef2f7; border-radius: 16px; min-width: 0; }
    .pw-recipe-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
    .pw-recipe-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; min-width: 0; overflow-wrap: anywhere; }
    .pw-recipe-title .pw-badge-sm { margin-left: 0; }
    .pw-recipe-table td { padding: 10px 12px; }
    .pw-warn-text { color: #9a3412; font-weight: 700; min-width: 0; }
    .pw-drawer-wide { width: min(720px, 92vw); }
    .pw-recipe-rows { display: grid; gap: 10px; }
    .pw-recipe-row { border: 1px solid #eef2f7; border-radius: 16px; padding: 12px; background: #fbfcfe; min-width: 0; }
    .pw-recipe-row-main { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 10px; align-items: center; }
    .pw-recipe-ingredient { min-width: 0; overflow-wrap: anywhere; }
    .pw-recipe-qty { display: flex; align-items: center; gap: 8px; }
    .pw-drawer .pw-recipe-qty input[type="text"] { width: 130px; padding: 10px 12px; text-align: right; font-variant-numeric: tabular-nums; }
    .pw-drawer .pw-recipe-ingredient select { padding: 10px 12px; }
    .pw-recipe-unit { min-width: 44px; font-size: 13px; font-weight: 800; color: #4b5563; }
    .pw-recipe-removed-note { display: none; margin-top: 6px; font-size: 12px; font-weight: 700; color: #b42318; }
    .pw-recipe-row.is-removed { background: #fff5f5; border-color: #fecaca; }
    .pw-recipe-row.is-removed .pw-recipe-ingredient strong { text-decoration: line-through; color: #9ca3af; }
    .pw-recipe-row.is-removed .pw-recipe-qty input { opacity: .5; }
    .pw-recipe-row.is-removed .pw-recipe-removed-note { display: block; }
    .pw-sr-only { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

    /* Tablet / mobile: sidebar is off-canvas (layout); the section rail becomes horizontal tabs. */
    @media (max-width: 1180px) {
        .pw-header { position: static; }
        .pw-body { grid-template-columns: minmax(0, 1fr); }
        .pw-nav {
            position: static; display: flex; gap: 6px; overflow-x: auto; -webkit-overflow-scrolling: touch;
            scrollbar-width: thin; padding: 8px;
        }
        .pw-nav-link { flex: 0 0 auto; white-space: nowrap; }
    }

    @media (max-width: 780px) {
        .pw-header { padding: 16px; border-radius: 20px; }
        .pw-title { font-size: 24px; }
        .pw-card { padding: 16px; border-radius: 20px; }
        .pw-facts, .pw-form-grid, .pw-outlet-grid { grid-template-columns: minmax(0, 1fr); }
        .pw-field-inline { flex-wrap: wrap; }
        .pw-field-inline .btn { flex: 1 1 100%; }
        .pw-header-actions { width: 100%; }
        .pw-header-actions .btn { flex: 1 1 auto; }
    }
    /* Phones: the nested drawer becomes a full-screen sheet (tablet keeps the 90vw panel). */
    @media (max-width: 779px) {
        .pw-drawer { width: 100vw; border-radius: 0; border-left: 0; }
        .pw-drawer-grid, .pw-drawer .pw-outlet-grid { grid-template-columns: minmax(0, 1fr); }
        .pw-drawer-wide { width: 100vw; }
        .pw-recipe-row-main { grid-template-columns: minmax(0, 1fr) auto; }
        .pw-recipe-ingredient { grid-column: 1 / -1; }
        .pw-recipe-qty { min-width: 0; }
        .pw-drawer .pw-recipe-qty input[type="text"] { width: 100%; min-width: 0; }
    }
</style>
