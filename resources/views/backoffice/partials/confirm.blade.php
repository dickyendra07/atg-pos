{{--
    Reusable confirmation dialog (replaces the browser confirm()). Mark a form:

        <form ... data-bo-confirm
              data-bo-confirm-title="Hapus Product Permanen?"
              data-bo-confirm-body="Product “X” akan dihapus permanen."
              data-bo-confirm-note="2 Variant ... juga akan dihapus."   (optional)
              data-bo-confirm-label="Hapus Permanen"
              data-bo-confirm-tone="danger|warning">

    and a button that only explains why something is not allowed:

        <button type="button" data-bo-blocked="Product tidak dapat dihapus permanen karena ...">
--}}
<style>
    .bo-confirm-overlay {
        position: fixed;
        inset: 0;
        z-index: 9990;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
        background: rgba(15, 23, 42, 0.5);
    }

    .bo-confirm-overlay.is-open { display: flex; }

    .bo-confirm-dialog {
        width: 100%;
        max-width: 440px;
        padding: 22px;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 30px 80px rgba(15, 23, 42, 0.3);
        font-family: Arial, sans-serif;
        color: #111827;
    }

    .bo-confirm-title { margin: 0 0 10px; font-size: 19px; font-weight: 800; }
    .bo-confirm-body { margin: 0 0 8px; font-size: 15px; line-height: 1.5; color: #374151; overflow-wrap: anywhere; }
    .bo-confirm-note { margin: 0 0 8px; padding: 10px 12px; border-radius: 12px; background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; font-size: 14px; line-height: 1.45; }
    .bo-confirm-note:empty, .bo-confirm-body:empty { display: none; }
    .bo-confirm-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; flex-wrap: wrap; }

    .bo-confirm-actions button {
        min-height: 44px;
        padding: 0 18px;
        border-radius: 12px;
        border: 1px solid #d1d5db;
        background: #fff;
        color: #111827;
        font-size: 14px;
        font-weight: 800;
        cursor: pointer;
    }

    .bo-confirm-actions button.bo-confirm-ok { border-color: transparent; color: #fff; background: #dc2626; }
    .bo-confirm-overlay[data-tone="warning"] .bo-confirm-ok { background: #ea580c; }
    .bo-confirm-actions button:focus-visible { outline: 3px solid rgba(37, 99, 235, 0.55); outline-offset: 2px; }
    .bo-confirm-actions button[disabled] { opacity: 0.6; cursor: wait; }

    @media (max-width: 480px) {
        .bo-confirm-actions button { flex: 1; }
    }
</style>

<div class="bo-confirm-overlay" id="bo-confirm-overlay" aria-hidden="true">
    <div class="bo-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="bo-confirm-title" aria-describedby="bo-confirm-body">
        <h2 class="bo-confirm-title" id="bo-confirm-title"></h2>
        <p class="bo-confirm-body" id="bo-confirm-body"></p>
        <p class="bo-confirm-note" id="bo-confirm-note"></p>
        <div class="bo-confirm-actions">
            <button type="button" id="bo-confirm-cancel">Batal</button>
            <button type="button" class="bo-confirm-ok" id="bo-confirm-ok">Ya</button>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';

        var overlay = document.getElementById('bo-confirm-overlay');
        if (!overlay) { return; }

        var titleEl = document.getElementById('bo-confirm-title');
        var bodyEl = document.getElementById('bo-confirm-body');
        var noteEl = document.getElementById('bo-confirm-note');
        var okBtn = document.getElementById('bo-confirm-ok');
        var cancelBtn = document.getElementById('bo-confirm-cancel');
        var pendingForm = null;
        var pendingResolve = null;   // BackofficeConfirm.ask(): resolves true (OK) or false (cancel)
        var opener = null;

        function show(title, body, note, label, tone) {
            opener = document.activeElement;
            titleEl.textContent = title || 'Yakin?';
            bodyEl.textContent = body || '';
            noteEl.textContent = note || '';
            okBtn.textContent = label || 'Ya';
            okBtn.disabled = false;
            overlay.setAttribute('data-tone', tone || 'danger');
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            cancelBtn.focus();   // the safe choice has focus
        }

        function open(form) {
            pendingForm = form;
            show(form.getAttribute('data-bo-confirm-title'), form.getAttribute('data-bo-confirm-body'),
                form.getAttribute('data-bo-confirm-note'), form.getAttribute('data-bo-confirm-label'),
                form.getAttribute('data-bo-confirm-tone'));
        }

        function settle(result) {
            var resolve = pendingResolve;
            pendingResolve = null;
            if (resolve) { resolve(result); }
        }

        function close() {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            pendingForm = null;
            if (opener && opener.focus) { try { opener.focus(); } catch (e) { /* ignore */ } }
            settle(false);
        }

        // Same dialog for client-side questions (e.g. leaving a page with unsaved changes):
        // BackofficeConfirm.ask({ title, body, note, label, tone }).then(function (ok) { ... })
        window.BackofficeConfirm = {
            ask: function (options) {
                options = options || {};
                settle(false);
                pendingForm = null;
                return new Promise(function (resolve) {
                    pendingResolve = resolve;
                    show(options.title, options.body, options.note, options.label, options.tone);
                });
            },
            isOpen: function () { return overlay.classList.contains('is-open'); }
        };

        // Runs after the position-memory listener of the feedback partial (it is registered in the
        // capture phase), so the list scroll offset has already been saved when a form is held here.
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!form || !form.hasAttribute || !form.hasAttribute('data-bo-confirm')) { return; }
            event.preventDefault();
            open(form);
        });

        okBtn.addEventListener('click', function () {
            if (pendingResolve) {
                var resolve = pendingResolve;
                pendingResolve = null;
                close();
                resolve(true);
                return;
            }
            if (!pendingForm) { return; }
            var form = pendingForm;
            okBtn.disabled = true;          // one click, one request
            // HTMLFormElement.submit() does not fire a submit event, so this goes straight out without
            // being intercepted again (and nothing is left marked "confirmed" on the form).
            form.submit();
        });

        // Coming back with the Back button can restore this page from the browser's cache with the dialog
        // state as it was: reset it, so a destructive form is never one click away from re-sending.
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                overlay.classList.remove('is-open');
                overlay.setAttribute('aria-hidden', 'true');
                pendingForm = null;
                okBtn.disabled = false;
                settle(false);
            }
        });

        cancelBtn.addEventListener('click', function () { close(); });
        overlay.addEventListener('click', function (event) { if (event.target === overlay) { close(); } });

        document.addEventListener('keydown', function (event) {
            if (!overlay.classList.contains('is-open')) { return; }
            if (event.key === 'Escape') { event.preventDefault(); close(); return; }
            if (event.key === 'Tab') {   // keep focus inside the dialog
                var first = cancelBtn;
                var last = okBtn.disabled ? cancelBtn : okBtn;
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });

        // A button that only says why an action is not available (no request is made).
        document.addEventListener('click', function (event) {
            var blocked = event.target.closest ? event.target.closest('[data-bo-blocked]') : null;
            if (!blocked) { return; }
            event.preventDefault();
            if (window.BackofficeToast) { window.BackofficeToast.show('error', blocked.getAttribute('data-bo-blocked')); }
        });
    })();
</script>
