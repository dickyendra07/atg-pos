{{--
    Shared Backoffice feedback: global toast (Laravel session flash) + list position restore.
    Included once by layouts/app.blade.php and by the standalone Backoffice pages that do not extend it.
--}}
@php
    $boToasts = [];

    foreach (['success', 'error', 'warning', 'info'] as $boToastType) {
        $boToastMessage = session($boToastType);

        if (is_string($boToastMessage) && trim($boToastMessage) !== '') {
            $boToasts[] = ['type' => $boToastType, 'message' => $boToastMessage];
        }
    }

    // One summary toast for validation failures; the inline per-field messages stay as they are.
    if ($errors->any()) {
        $boToasts[] = ['type' => 'error', 'message' => 'Ada data yang perlu diperbaiki.'];
    }
@endphp

<style>
    .bo-toast-region {
        position: fixed;
        top: 16px;
        right: 16px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: min(380px, calc(100vw - 32px));
        pointer-events: none;
    }

    .bo-toast {
        pointer-events: auto;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 14px;
        border-radius: 14px;
        border: 1px solid;
        box-shadow: 0 14px 34px rgba(15, 23, 42, 0.18);
        font-family: Arial, sans-serif;
        font-size: 14px;
        font-weight: 700;
        line-height: 1.45;
        animation: bo-toast-in 0.2s ease-out;
    }

    .bo-toast.is-leaving { opacity: 0; transform: translateY(-6px); transition: opacity 0.2s ease, transform 0.2s ease; }
    .bo-toast--success { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
    .bo-toast--error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .bo-toast--warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .bo-toast--info { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
    .bo-toast-message { flex: 1; min-width: 0; overflow-wrap: anywhere; }

    .bo-toast-close {
        flex-shrink: 0;
        border: 0;
        background: transparent;
        color: inherit;
        font-size: 20px;
        line-height: 1;
        padding: 0 2px;
        cursor: pointer;
        opacity: 0.7;
    }

    .bo-toast-close:hover,
    .bo-toast-close:focus-visible { opacity: 1; }

    .bo-flash-target { animation: bo-target-pulse 2.2s ease-out 1; }

    @keyframes bo-toast-in { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes bo-target-pulse { 0%, 35% { outline: 3px solid rgba(232, 106, 58, 0.55); outline-offset: -2px; background-color: rgba(255, 237, 213, 0.7); } 100% { outline: 3px solid transparent; outline-offset: -2px; } }

    @media (prefers-reduced-motion: reduce) {
        .bo-toast, .bo-flash-target { animation: none; }
        .bo-toast.is-leaving { transition: none; }
    }

    @media (max-width: 560px) {
        .bo-toast-region { top: 10px; right: 10px; left: 10px; width: auto; }
    }
</style>

<div class="bo-toast-region" id="bo-toast-region" aria-live="polite" aria-atomic="false">
    @foreach($boToasts as $boToast)
        <div class="bo-toast bo-toast--{{ $boToast['type'] }}"
             data-bo-toast
             data-bo-toast-type="{{ $boToast['type'] }}"
             role="{{ in_array($boToast['type'], ['error', 'warning'], true) ? 'alert' : 'status' }}">
            <div class="bo-toast-message">{{ $boToast['message'] }}</div>
            <button type="button" class="bo-toast-close" data-bo-toast-close aria-label="Tutup notifikasi">&times;</button>
        </div>
    @endforeach
</div>

<script>
    (function () {
        'use strict';

        // ---- Toast -------------------------------------------------------------------------------
        var region = document.getElementById('bo-toast-region');
        var DISMISS_MS = { success: 4000, info: 4000, warning: 5000, error: 5000 };

        function dismiss(toast) {
            if (!toast || toast.classList.contains('is-leaving')) { return; }
            toast.classList.add('is-leaving');
            setTimeout(function () { if (toast.parentNode) { toast.parentNode.removeChild(toast); } }, 220);
        }

        function arm(toast) {
            var timer = null;
            var ms = DISMISS_MS[toast.getAttribute('data-bo-toast-type')] || 4000;

            function start() { timer = setTimeout(function () { dismiss(toast); }, ms); }
            function stop() { clearTimeout(timer); }

            toast.querySelector('[data-bo-toast-close]').addEventListener('click', function () { stop(); dismiss(toast); });
            toast.addEventListener('mouseenter', stop);
            toast.addEventListener('mouseleave', start);
            toast.addEventListener('focusin', stop);
            toast.addEventListener('focusout', start);
            start();
        }

        if (region) {
            region.querySelectorAll('[data-bo-toast]').forEach(arm);
        }

        // Same toast for client-side callers: BackofficeToast.show('success', 'Tersimpan.')
        window.BackofficeToast = {
            show: function (type, message) {
                if (!region) { return; }
                var toast = document.createElement('div');
                var text = document.createElement('div');
                var close = document.createElement('button');

                type = DISMISS_MS[type] ? type : 'info';
                toast.className = 'bo-toast bo-toast--' + type;
                toast.setAttribute('data-bo-toast', '');
                toast.setAttribute('data-bo-toast-type', type);
                toast.setAttribute('role', (type === 'error' || type === 'warning') ? 'alert' : 'status');
                text.className = 'bo-toast-message';
                text.textContent = message;
                close.type = 'button';
                close.className = 'bo-toast-close';
                close.setAttribute('data-bo-toast-close', '');
                close.setAttribute('aria-label', 'Tutup notifikasi');
                close.innerHTML = '&times;';
                toast.appendChild(text);
                toast.appendChild(close);
                region.appendChild(toast);
                arm(toast);
            }
        };

        // ---- List position (return_to) -----------------------------------------------------------
        // Before the user leaves a list through an Edit / Create / row-action control that carries
        // return_to, remember the scroll offset; when they come back to that same list URL (after a
        // save, or via Cancel) bring the touched record into view, otherwise restore the offset.
        var KEY = 'atg.backoffice.listPosition';
        var TTL_MS = 30 * 60 * 1000;

        function here() { return location.pathname + location.search; }

        function stripHash(url) { var i = url.indexOf('#'); return i === -1 ? url : url.slice(0, i); }

        function readState() {
            try {
                var state = JSON.parse(sessionStorage.getItem(KEY) || 'null');
                if (state && state.url === here() && Date.now() - state.ts < TTL_MS) { return state; }
            } catch (e) { /* storage unavailable: anchors still work */ }
            return null;
        }

        function clearState() {
            try { sessionStorage.removeItem(KEY); } catch (e) { /* ignore */ }
        }

        function remember(returnTo) {
            // Only the list itself may record a position; an edit page carrying return_to must not.
            if (!returnTo || stripHash(returnTo) !== here()) { return; }
            try {
                sessionStorage.setItem(KEY, JSON.stringify({ url: here(), y: window.pageYOffset || 0, ts: Date.now() }));
            } catch (e) { /* ignore */ }
        }

        function returnToOfLink(link) {
            try {
                return new URL(link.href, location.href).searchParams.get('return_to');
            } catch (e) { return null; }
        }

        document.addEventListener('click', function (event) {
            var link = event.target.closest ? event.target.closest('a[href*="return_to="]') : null;
            if (link) { remember(returnToOfLink(link)); }
        }, true);

        document.addEventListener('submit', function (event) {
            var field = event.target && event.target.querySelector ? event.target.querySelector('input[name="return_to"]') : null;
            if (field) { remember(field.value); }
        }, true);

        function reveal(target) {
            // Lets a page expand whatever collapsed section holds the record (see products/variants).
            target.dispatchEvent(new CustomEvent('bo:reveal', { bubbles: true }));
            var details = target.closest ? target.closest('details') : null;
            if (details) { details.open = true; }
        }

        function inView(el) {
            var rect = el.getBoundingClientRect();
            return rect.height > 0 && rect.top >= 0 && rect.bottom <= (window.innerHeight || document.documentElement.clientHeight);
        }

        function restore() {
            var nav = window.performance && performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
            if (nav && nav.type === 'back_forward') { return; }   // the browser restores those itself

            var id = location.hash ? decodeURIComponent(location.hash.slice(1)) : '';
            var target = id ? document.getElementById(id) : null;
            var state = readState();

            if (!target && !document.querySelector('[data-bo-toast]')) { return; }

            function place() {
                if (target) { reveal(target); }
                if (state) { window.scrollTo(0, state.y); }
                if (target && !inView(target)) { target.scrollIntoView({ block: 'center' }); }
            }

            // The browser also jumps to #fragment on its own (aligning the row to the very top) once
            // the page has loaded, and layout can shift while images/fonts arrive, so place the
            // position again after load and let that final pass win.
            place();

            if (target) { target.classList.add('bo-flash-target'); }

            window.addEventListener('load', function () {
                setTimeout(function () {
                    place();
                    if (state) { clearState(); }
                }, 0);
            });

            if (document.readyState === 'complete') { place(); if (state) { clearState(); } }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', restore);
        } else {
            restore();
        }
    })();
</script>
