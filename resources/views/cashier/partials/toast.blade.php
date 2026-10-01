{{--
    Cashier toast: a notice that always sits above every modal/backdrop (the inline #cashier-alert-*
    boxes live in the page flow and end up behind the Variant/payment modals).
    Plain DOM + CSS only (no dependency, no experimental API) so it works in the Android WebView.
    Use: CashierToast.show('error', 'Recipe belum tersedia.')
--}}
<style>
    .cashier-toast-region {
        position: fixed;
        top: max(12px, env(safe-area-inset-top));
        left: 50%;
        transform: translateX(-50%);
        z-index: 2147483647;
        width: min(560px, calc(100vw - 24px));
        display: flex;
        flex-direction: column;
        gap: 8px;
        pointer-events: none;
    }

    .cashier-toast {
        pointer-events: auto;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 8px 12px 16px;
        border-radius: 16px;
        border: 1px solid;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.28);
        font-family: Arial, sans-serif;
        font-size: 16px;
        font-weight: 700;
        line-height: 1.4;
    }

    .cashier-toast--error { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
    .cashier-toast--warning { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
    .cashier-toast--info { background: #eff6ff; border-color: #93c5fd; color: #1e40af; }
    .cashier-toast--success { background: #ecfdf5; border-color: #6ee7b7; color: #065f46; }

    .cashier-toast-message { flex: 1; min-width: 0; overflow-wrap: anywhere; padding-top: 2px; }

    .cashier-toast-close {
        flex-shrink: 0;
        width: 44px;
        height: 44px;
        margin: -6px 0;
        border: 0;
        border-radius: 12px;
        background: transparent;
        color: inherit;
        font-size: 26px;
        line-height: 1;
        cursor: pointer;
    }

    @media (max-width: 560px) {
        .cashier-toast { font-size: 15px; }
    }
</style>

<div class="cashier-toast-region" id="cashier-toast-region" aria-live="assertive" aria-atomic="false"></div>

<script>
    (function () {
        'use strict';

        var region = document.getElementById('cashier-toast-region');
        // How long each kind stays. Errors stay long enough to read and act on; tapping the toast
        // (or its x) dismisses it at once, and a new toast with the same text just restarts the timer.
        var LIFETIME = { error: 10000, warning: 8000, info: 5000, success: 4000 };

        function remove(toast) {
            if (toast && toast.parentNode) { toast.parentNode.removeChild(toast); }
        }

        window.CashierToast = {
            show: function (type, message) {
                if (!region || !message) { return; }

                type = LIFETIME[type] ? type : 'info';

                var existing = region.querySelectorAll('.cashier-toast');
                for (var i = 0; i < existing.length; i++) {
                    if (existing[i].getAttribute('data-message') === message) {
                        clearTimeout(existing[i]._timer);
                        existing[i]._timer = setTimeout(function (t) { return function () { remove(t); }; }(existing[i]), LIFETIME[type]);
                        return;
                    }
                }

                var toast = document.createElement('div');
                var text = document.createElement('div');
                var close = document.createElement('button');

                toast.className = 'cashier-toast cashier-toast--' + type;
                toast.setAttribute('role', type === 'error' || type === 'warning' ? 'alert' : 'status');
                toast.setAttribute('data-message', message);
                text.className = 'cashier-toast-message';
                text.textContent = message;
                close.type = 'button';
                close.className = 'cashier-toast-close';
                close.setAttribute('aria-label', 'Tutup pesan');
                close.innerHTML = '&times;';

                toast.appendChild(text);
                toast.appendChild(close);
                toast.addEventListener('click', function () { clearTimeout(toast._timer); remove(toast); });
                region.appendChild(toast);

                // Never pile up more than three.
                while (region.children.length > 3) { remove(region.firstChild); }

                toast._timer = setTimeout(function () { remove(toast); }, LIFETIME[type]);
            }
        };
    })();
</script>
