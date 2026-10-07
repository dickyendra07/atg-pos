{{--
    Confirmation dialog for the TEMPORARY cleanup delete (Product, Variant, Ingredient, Recipe).
    Included once by the layout, and only when the feature flag is on for an owner / admin_pusat.

    A button marked data-cleanup-delete opens it. The dialog asks the server for the CURRENT impact
    (counts, blocking dependencies), shows it, and - only when nothing blocks - asks the user to type the
    exact name before the red button becomes active. The DELETE itself is a normal form post whose
    confirmation text the server checks again.
--}}
<style>
    .bo-cleanup-overlay { position: fixed; inset: 0; z-index: 9995; display: none; align-items: center; justify-content: center; padding: 16px; background: rgba(15, 23, 42, 0.55); }
    .bo-cleanup-overlay.is-open { display: flex; }
    .bo-cleanup-dialog { width: 100%; max-width: 520px; max-height: calc(100vh - 32px); overflow-y: auto; padding: 22px; border-radius: 18px; background: #fff; box-shadow: 0 30px 80px rgba(15, 23, 42, 0.35); font-family: Arial, sans-serif; color: #111827; }
    .bo-cleanup-kicker { display: inline-block; margin-bottom: 8px; padding: 3px 10px; border-radius: 999px; background: #fee2e2; color: #991b1b; font-size: 12px; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; }
    .bo-cleanup-title { margin: 0 0 6px; font-size: 20px; font-weight: 800; }
    .bo-cleanup-name { margin: 0 0 12px; font-size: 16px; font-weight: 700; overflow-wrap: anywhere; }
    .bo-cleanup-warning { margin: 0 0 12px; padding: 10px 12px; border-radius: 12px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 14px; line-height: 1.45; }
    .bo-cleanup-counts { width: 100%; min-width: 0; margin: 0 0 12px; border-collapse: collapse; font-size: 14px; table-layout: fixed; }   /* min-width: the layout gives every table a wide minimum */
    .bo-cleanup-counts td { padding: 6px 4px; border-bottom: 1px solid #f1f5f9; overflow-wrap: anywhere; text-align: left; }
    .bo-cleanup-counts td:last-child { width: 56px; text-align: right; font-weight: 800; white-space: nowrap; padding-left: 12px; }
    .bo-cleanup-blockers { margin: 0 0 12px; padding: 12px; border-radius: 12px; background: #fff7ed; border: 1px solid #fdba74; color: #9a3412; font-size: 14px; line-height: 1.45; }
    .bo-cleanup-blockers strong { display: block; margin-bottom: 4px; }
    .bo-cleanup-blockers ul { margin: 6px 0 0; padding-left: 18px; }
    .bo-cleanup-blockers p { margin: 0 0 8px; }
    .bo-cleanup-notes { margin: 0 0 12px; padding-left: 18px; color: #374151; font-size: 13px; line-height: 1.5; }
    .bo-cleanup-confirm label { display: block; margin-bottom: 6px; font-size: 14px; color: #374151; }
    .bo-cleanup-confirm code { padding: 2px 6px; border-radius: 6px; background: #f3f4f6; font-weight: 800; overflow-wrap: anywhere; }
    .bo-cleanup-confirm input { width: 100%; min-height: 44px; padding: 0 12px; border: 1px solid #d1d5db; border-radius: 12px; font-size: 15px; box-sizing: border-box; }
    .bo-cleanup-confirm input:focus { outline: 3px solid rgba(220, 38, 38, .25); border-color: #dc2626; }
    .bo-cleanup-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 18px; flex-wrap: wrap; }
    .bo-cleanup-actions button { min-height: 44px; padding: 0 18px; border-radius: 12px; border: 1px solid #d1d5db; background: #fff; color: #111827; font-size: 14px; font-weight: 800; cursor: pointer; }
    .bo-cleanup-actions button.bo-cleanup-go { border-color: transparent; color: #fff; background: #dc2626; }
    .bo-cleanup-actions button.bo-cleanup-go[disabled] { opacity: .4; cursor: not-allowed; }
    .bo-cleanup-actions button:focus-visible { outline: 3px solid rgba(37, 99, 235, .55); outline-offset: 2px; }
    .bo-cleanup-status { margin: 0 0 12px; font-size: 14px; color: #475569; }
    @media (max-width: 480px) { .bo-cleanup-actions button { flex: 1; } .bo-cleanup-dialog { padding: 18px; } }
</style>

<div class="bo-cleanup-overlay" id="bo-cleanup-overlay" aria-hidden="true"
     data-impact-url="{{ route('backoffice.cleanup.impact', ['type' => '__TYPE__', 'id' => '__ID__'], false) }}"
     data-destroy-url="{{ route('backoffice.cleanup.destroy', ['type' => '__TYPE__', 'id' => '__ID__'], false) }}"
     data-token="{{ csrf_token() }}">
    <div class="bo-cleanup-dialog" role="alertdialog" aria-modal="true" aria-labelledby="bo-cleanup-title" id="bo-cleanup-dialog">
        <span class="bo-cleanup-kicker">Mode hapus data uji</span>
        <h2 class="bo-cleanup-title" id="bo-cleanup-title"></h2>
        <p class="bo-cleanup-name" id="bo-cleanup-name"></p>
        <div id="bo-cleanup-body"></div>
        <div class="bo-cleanup-actions">
            <button type="button" id="bo-cleanup-cancel">Batal</button>
            <button type="button" class="bo-cleanup-go" id="bo-cleanup-go" disabled hidden>Hapus dari Sistem</button>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';

        var overlay = document.getElementById('bo-cleanup-overlay');
        if (!overlay) { return; }

        var titleEl = document.getElementById('bo-cleanup-title');
        var nameEl = document.getElementById('bo-cleanup-name');
        var bodyEl = document.getElementById('bo-cleanup-body');
        var goBtn = document.getElementById('bo-cleanup-go');
        var cancelBtn = document.getElementById('bo-cleanup-cancel');
        var state = null;      // { type, id, ret, impact, opener }
        var token = 0;         // ignores a late response for a dialog that was already closed

        function el(tag, className, text) {
            var node = document.createElement(tag);
            if (className) { node.className = className; }
            if (text !== undefined) { node.textContent = text; }
            return node;
        }

        function url(template, type, id) { return template.replace('__TYPE__', type).replace('__ID__', id); }

        function open(button) {
            token += 1;
            state = {
                type: button.getAttribute('data-cleanup-type'),
                id: button.getAttribute('data-cleanup-id'),
                ret: button.getAttribute('data-cleanup-return') || '',
                name: button.getAttribute('data-cleanup-name') || '',
                impact: null,
                opener: button
            };
            titleEl.textContent = 'Memeriksa dampak…';
            nameEl.textContent = state.name;
            bodyEl.replaceChildren(el('p', 'bo-cleanup-status', 'Menghitung data yang terkait…'));
            goBtn.hidden = true;
            goBtn.disabled = true;
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            cancelBtn.focus();

            var mine = token;
            fetch(url(overlay.getAttribute('data-impact-url'), state.type, state.id), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (json) { return { ok: response.ok, json: json }; }, function () { return { ok: false, json: {} }; });
            }).then(function (result) {
                if (mine !== token || !state) { return; }
                if (!result.ok || !result.json.impact) {
                    titleEl.textContent = 'Tidak dapat melanjutkan';
                    bodyEl.replaceChildren(el('p', 'bo-cleanup-warning', (result.json && result.json.message) || 'Data tidak dapat diperiksa. Muat ulang halaman lalu coba lagi.'));
                    return;
                }
                render(result.json.impact);
            }).catch(function () {
                if (mine !== token || !state) { return; }
                titleEl.textContent = 'Tidak dapat melanjutkan';
                bodyEl.replaceChildren(el('p', 'bo-cleanup-warning', 'Koneksi bermasalah. Coba lagi.'));
            });
        }

        function render(impact) {
            state.impact = impact;
            var isRecipe = impact.type === 'recipe';
            titleEl.textContent = isRecipe ? 'Hapus ' + impact.type_label + ' Permanen?' : 'Hapus ' + impact.type_label + ' dari Sistem?';
            nameEl.textContent = impact.type_label + ': ' + impact.name;

            var body = document.createDocumentFragment();

            body.appendChild(el('p', 'bo-cleanup-warning', isRecipe
                ? 'Ini pembersihan permanen dan tidak bisa dibatalkan. Recipe dan semua itemnya benar-benar dihapus.'
                : 'Ini pembersihan data uji dan tidak bisa dibatalkan dari Back Office. Data akan hilang dari seluruh tampilan operasional; riwayat transaksi dan stok tidak dihapus.'));

            if (impact.counts && impact.counts.length) {
                var table = el('table', 'bo-cleanup-counts');
                impact.counts.forEach(function (row) {
                    var tr = el('tr');
                    tr.appendChild(el('td', '', row.label));
                    tr.appendChild(el('td', '', String(row.value)));
                    table.appendChild(tr);
                });
                body.appendChild(table);
            }

            if (impact.blockers && impact.blockers.length) {
                var box = el('div', 'bo-cleanup-blockers');
                box.setAttribute('data-testid', 'cleanup-blockers');
                box.appendChild(el('strong', '', 'Tidak dapat dihapus sekarang'));
                impact.blockers.forEach(function (blocker) {
                    box.appendChild(el('p', '', blocker.message));
                    if (blocker.items && blocker.items.length) {
                        var list = el('ul');
                        blocker.items.forEach(function (item) { list.appendChild(el('li', '', item)); });
                        box.appendChild(list);
                    }
                });
                body.appendChild(box);
            }

            if (impact.notes && impact.notes.length) {
                var notes = el('ul', 'bo-cleanup-notes');
                impact.notes.forEach(function (note) { notes.appendChild(el('li', '', note)); });
                body.appendChild(notes);
            }

            if (impact.can_delete) {
                var wrap = el('div', 'bo-cleanup-confirm');
                var label = el('label');
                label.setAttribute('for', 'bo-cleanup-input');
                label.appendChild(document.createTextNode('Ketik nama persis untuk konfirmasi: '));
                label.appendChild(el('code', '', impact.confirm_text));
                var input = el('input');
                input.type = 'text';
                input.id = 'bo-cleanup-input';
                input.autocomplete = 'off';
                input.spellcheck = false;
                input.setAttribute('data-testid', 'cleanup-confirm-input');
                input.addEventListener('input', function () { goBtn.disabled = input.value.trim() !== impact.confirm_text; });
                wrap.appendChild(label);
                wrap.appendChild(input);
                body.appendChild(wrap);
                goBtn.textContent = isRecipe ? 'Hapus Permanen' : 'Hapus dari Sistem';
                goBtn.hidden = false;
                goBtn.disabled = true;
            } else {
                goBtn.hidden = true;
            }

            bodyEl.replaceChildren(body);
            var firstInput = document.getElementById('bo-cleanup-input');
            if (firstInput) { firstInput.focus(); }
            cancelBtn.textContent = impact.can_delete ? 'Batal' : 'Tutup';
        }

        function close() {
            token += 1;
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            var opener = state && state.opener;
            state = null;
            cancelBtn.textContent = 'Batal';
            if (opener && opener.focus) { try { opener.focus(); } catch (e) { /* ignore */ } }
        }

        function submit() {
            var input = document.getElementById('bo-cleanup-input');
            if (!state || !state.impact || !input || input.value.trim() !== state.impact.confirm_text) { return; }

            goBtn.disabled = true;   // one click, one request

            var form = document.createElement('form');
            form.method = 'POST';
            form.action = url(overlay.getAttribute('data-destroy-url'), state.type, state.id);
            [['_token', overlay.getAttribute('data-token')], ['_method', 'DELETE'], ['confirmation', input.value], ['return_to', state.ret]].forEach(function (pair) {
                if (pair[0] === 'return_to' && !pair[1]) { return; }
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = pair[0];
                hidden.value = pair[1];
                form.appendChild(hidden);
            });
            document.body.appendChild(form);
            form.submit();
        }

        document.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('[data-cleanup-delete]') : null;
            if (!button) { return; }
            event.preventDefault();
            open(button);
        });

        goBtn.addEventListener('click', submit);
        cancelBtn.addEventListener('click', close);
        overlay.addEventListener('click', function (event) { if (event.target === overlay) { close(); } });

        document.addEventListener('keydown', function (event) {
            if (!overlay.classList.contains('is-open')) { return; }
            if (event.key === 'Escape') { event.preventDefault(); close(); return; }
            if (event.key === 'Enter' && event.target && event.target.id === 'bo-cleanup-input') {
                event.preventDefault();
                if (!goBtn.disabled) { submit(); }
            }
        });

        // Back/forward cache must never bring back an open (or half-typed) destructive dialog.
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) { token += 1; state = null; overlay.classList.remove('is-open'); overlay.setAttribute('aria-hidden', 'true'); }
        });
    })();
</script>
