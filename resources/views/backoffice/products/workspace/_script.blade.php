<script>
    (function () {
        'use strict';

        var root = document.querySelector('[data-product-workspace]');
        if (!root) { return; }

        var EDITABLE = ['general', 'outlets'];
        var LABELS = { general: 'General', outlets: 'Outlets' };
        var snapshots = {};
        var saving = false;
        var leaving = false;
        var csrf = (document.querySelector('[data-pw-form] input[name="_token"]') || {}).value || '';
        // Same summary as the server-side validation toast (partials/feedback). Assembled here so the
        // page source holds that sentence only where a real toast is rendered.
        var INVALID_MESSAGE = ['Ada data yang perlu', 'diperbaiki.'].join(' ');

        function header() { return root.querySelector('[data-pw-header]'); }
        function panel(key) { return root.querySelector('[data-pw-panel="' + key + '"]'); }
        function formFor(key) { return root.querySelector('[data-pw-form="' + key + '"]'); }

        function toast(type, message) {
            if (window.BackofficeToast) { window.BackofficeToast.show(type, message); }
        }

        function confirmAsk(options) {
            if (window.BackofficeConfirm) { return window.BackofficeConfirm.ask(options); }
            return Promise.resolve(window.confirm(options.title + '\n' + (options.body || '')));
        }

        // ---- Section switching (?section=..., no navigation) --------------------------------------
        function showSection(key, syncUrl) {
            var target = panel(key);
            if (!target) { return; }

            root.querySelectorAll('[data-pw-panel]').forEach(function (other) { other.hidden = other !== target; });
            root.querySelectorAll('[data-pw-nav]').forEach(function (link) {
                var active = link.getAttribute('data-pw-nav') === key;
                link.classList.toggle('is-active', active);
                if (active) { link.setAttribute('aria-current', 'page'); } else { link.removeAttribute('aria-current'); }
            });
            root.setAttribute('data-pw-section', key);

            if (syncUrl) {
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.set('section', key);
                    history.replaceState(history.state, '', url.pathname + url.search);
                } catch (e) { /* keep working without URL sync */ }
            }
        }

        root.addEventListener('click', function (event) {
            var link = event.target.closest ? event.target.closest('[data-pw-nav]') : null;
            if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) { return; }
            event.preventDefault();
            showSection(link.getAttribute('data-pw-nav'), true);
            if (window.innerWidth > 1180) {
                var top = root.getBoundingClientRect().top + window.pageYOffset - 12;
                if (window.pageYOffset > top) { window.scrollTo(0, top); }
            }
        });

        // The sticky section rail sits under the sticky header.
        function syncHeaderHeight() {
            var el = header();
            if (el) { root.style.setProperty('--pw-header-h', el.offsetHeight + 'px'); }
        }
        window.addEventListener('resize', syncHeaderHeight);

        // ---- Dirty state ---------------------------------------------------------------------------
        function serialize(form) {
            var parts = [];
            new FormData(form).forEach(function (value, name) {
                if (name === '_token' || name === '_method' || name === 'return_to') { return; }
                parts.push(name + '=' + value);
            });
            return parts.sort().join('&');
        }

        function snapshot(key) {
            var form = formFor(key);
            if (form) { snapshots[key] = serialize(form); }
        }

        // A form re-rendered with validation errors (no-JS fallback) holds unsaved input.
        function initialSnapshot(key) {
            var form = formFor(key);
            if (!form) { return; }
            snapshots[key] = form.querySelector('[data-pw-error]:not([hidden])') ? '\u0000invalid' : serialize(form);
        }

        function isDirty(key) {
            var form = formFor(key);
            return !!form && snapshots[key] !== undefined && serialize(form) !== snapshots[key];
        }

        function dirtyKeys() { return EDITABLE.filter(isDirty); }

        function anyDirty() { return dirtyKeys().length > 0 || dirtyDrawers().length > 0; }

        // Everything unsaved: editable sections plus open drawers that hold input.
        function dirtyLabels() {
            var labels = dirtyKeys().map(function (key) { return LABELS[key]; });
            dirtyDrawers().forEach(function (key) {
                if (labels.indexOf(DRAWER_LABELS[key]) === -1) { labels.push(DRAWER_LABELS[key]); }
            });
            return labels;
        }

        function refreshState() {
            var labels = dirtyLabels();
            var dots = dirtyKeys().concat(dirtyDrawers().map(drawerSection));

            root.querySelectorAll('[data-pw-dirty-dot]').forEach(function (dot) {
                dot.hidden = dots.indexOf(dot.getAttribute('data-pw-dirty-dot')) === -1;
            });

            var state = root.querySelector('[data-pw-save-state]');
            if (!state) { return; }

            state.classList.toggle('is-saving', saving);
            state.classList.toggle('is-dirty', !saving && labels.length > 0);
            state.textContent = saving ? 'Menyimpan…'
                : labels.length === 0 ? 'Tersimpan'
                : labels.length === 1 ? 'Belum disimpan: ' + labels[0]
                : labels.length + ' bagian belum disimpan';
        }

        ['input', 'change'].forEach(function (type) {
            document.addEventListener(type, function (event) {
                var target = event.target;
                if (!target.closest || !target.closest('[data-pw-form], [data-pw-drawer-form]')) { return; }
                if (type === 'input' && target.classList.contains('pw-rupiah')) { formatRupiahInput(target); }
                refreshState();
                if (type === 'change' && target.hasAttribute('data-pw-outlet-checkbox')) { schedulePreview(); }
            });
        });

        // Same formatting as the Variant editor (cleanCurrencyNumber / formatRupiah); the server
        // normalises the value again (VariantWriter::normalizeRupiah).
        function cleanRupiah(value) {
            return String(value || '').trim().replace(/[.,]00$/, '').replace(/[^\d]/g, '');
        }

        function formatRupiahInput(input) {
            var numeric = cleanRupiah(input.value);
            input.value = numeric ? 'Rp. ' + Number(numeric).toLocaleString('id-ID') : '';
        }

        // ---- Validation errors ---------------------------------------------------------------------
        // Once the user edits a field, its error from the last save is stale: hide that field's message
        // only (other fields' errors stay until the next save, which renders the server's errors again).
        function clearFieldError(input) {
            if (!input || !input.name || !input.closest) { return; }

            var scope = input.closest('[data-pw-form], [data-pw-drawer-form]');
            if (!scope) { return; }

            var attr = scope.hasAttribute('data-pw-drawer-form') ? 'data-pw-drawer-error' : 'data-pw-error';
            var key = input.name.replace(/\[\]$/, '').split('[')[0];
            var box = scope.querySelector('[' + attr + '="' + key + '"]');

            if (box) { box.textContent = ''; box.hidden = true; }
            scope.querySelectorAll('[name="' + key + '"], [name="' + key + '[]"]').forEach(function (field) {
                field.classList.remove('is-invalid');
            });
        }

        ['input', 'change'].forEach(function (type) {
            document.addEventListener(type, function (event) { clearFieldError(event.target); });
        });

        function clearErrors(scope, attr) {
            scope.querySelectorAll('[' + attr + ']').forEach(function (el) { el.textContent = ''; el.hidden = true; });
            scope.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
        }

        function showErrors(scope, attr, errors) {
            var first = null;
            Object.keys(errors || {}).forEach(function (field) {
                var key = field.split('.')[0];
                var box = scope.querySelector('[' + attr + '="' + key + '"]');
                if (box && box.hidden) {
                    box.textContent = errors[field][0];
                    box.hidden = false;
                }
                scope.querySelectorAll('[name="' + key + '"], [name="' + key + '[]"]').forEach(function (input) {
                    if (input.type !== 'hidden') {
                        input.classList.add('is-invalid');
                        first = first || input;
                    }
                });
            });
            if (first && first.focus) { first.focus(); }
        }

        // ---- Requests ------------------------------------------------------------------------------
        function send(url, body) {
            return fetch(url, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                return response.json().catch(function () { return null; }).then(function (data) {
                    return { status: response.status, ok: response.ok, data: data || {} };
                });
            });
        }

        function failureToast(result) {
            if (result.status === 422) { toast('error', INVALID_MESSAGE); return; }
            if (result.status === 419) { toast('error', 'Sesi sudah berakhir. Perubahan belum tersimpan; muat ulang halaman lalu simpan lagi.'); return; }
            if (result.status === 403) { toast('error', result.data.message || 'Akses ditolak.'); return; }
            toast('error', 'Gagal menyimpan. Perubahan belum tersimpan, silakan coba lagi.');
        }

        // ---- Section save --------------------------------------------------------------------------
        function applySaved(data, savedKey) {
            // Never overwrite another section the user is still editing.
            var keep = {};
            EDITABLE.forEach(function (key) { if (key !== savedKey && isDirty(key)) { keep[key] = true; } });

            Object.keys(data.sections || {}).forEach(function (key) {
                var target = panel(key);
                if (!target || keep[key]) { return; }
                target.innerHTML = data.sections[key];
                if (EDITABLE.indexOf(key) !== -1) { snapshot(key); }
            });

            if (data.header && header()) { header().innerHTML = data.header; }
            if (data.title) { document.title = data.title + ' - Product Workspace - Back Office ATG POS'; }

            enhance();
            syncHeaderHeight();
        }

        function save(form, key) {
            var button = form.querySelector('[data-pw-save]');

            clearErrors(form, 'data-pw-error');
            saving = true;
            if (button) { button.disabled = true; }
            refreshState();

            return send(form.action, new FormData(form)).then(function (result) {
                if (result.ok && result.data.ok) {
                    applySaved(result.data, key);
                    toast('success', result.data.message);
                    return;
                }
                if (result.status === 422) { showErrors(form, 'data-pw-error', result.data.errors); }
                failureToast(result);
            }).catch(function () {
                toast('error', 'Koneksi terputus. Perubahan belum tersimpan, silakan coba lagi.');
            }).then(function () {
                saving = false;
                var current = formFor(key);
                var currentButton = current ? current.querySelector('[data-pw-save]') : null;
                if (currentButton) { currentButton.disabled = false; }
                refreshState();
            });
        }

        root.addEventListener('submit', function (event) {
            var rowAction = event.target.closest ? event.target.closest('[data-pw-row-action]') : null;
            if (rowAction) {
                event.preventDefault();
                runRowAction(rowAction);
                return;
            }

            var form = event.target.closest ? event.target.closest('[data-pw-form]') : null;
            if (!form) { return; }
            event.preventDefault();
            if (saving) { return; }

            var key = form.getAttribute('data-pw-form');

            if (key !== 'outlets') { save(form, key); return; }

            // Outlets: re-check the consequences on the server right before saving.
            requestPreview(form).then(function (preview) {
                if (!preview || !preview.has_consequences) { save(form, key); return; }

                confirmAsk({
                    title: 'Simpan perubahan outlet?',
                    body: preview.deactivated_variant_ids.length
                        ? preview.deactivated_variant_ids.length + ' Variant akan dinonaktifkan karena tidak lagi memiliki outlet. Detailnya ada di ringkasan di atas tombol ini.'
                        : 'Outlet beberapa Variant atau Promo ikut terdampak. Periksa ringkasan perubahan sebelum menyimpan.',
                    note: preview.promo_ids.length ? 'Promo tidak diubah otomatis.' : '',
                    label: 'Simpan Outlets',
                    tone: 'warning'
                }).then(function (ok) { if (ok) { save(form, key); } });
            });
        });

        // Row actions (e.g. "Nonaktifkan" a Variant): confirm, then save in place. Without JS the same form
        // posts normally and the server redirects back to the section.
        function runRowAction(form) {
            confirmAsk({
                title: form.getAttribute('data-bo-confirm-title'),
                body: form.getAttribute('data-bo-confirm-body'),
                label: form.getAttribute('data-bo-confirm-label'),
                tone: form.getAttribute('data-bo-confirm-tone') || 'warning'
            }).then(function (ok) {
                if (!ok) { return; }
                send(form.action, new FormData(form)).then(function (result) {
                    if (result.ok && result.data.ok) {
                        applySaved(result.data, null);
                        toast('success', result.data.message);
                        return;
                    }
                    failureToast(result);
                }).catch(function () {
                    toast('error', 'Koneksi terputus. Perubahan belum tersimpan, silakan coba lagi.');
                }).then(refreshState);
            });
        }

        // ---- Outlet consequence preview (server-computed) ------------------------------------------
        var previewTimer = null;
        var previewSeq = 0;

        function requestPreview(form) {
            var box = form.querySelector('[data-pw-outlet-preview]');
            var body = new FormData();
            var seq = ++previewSeq;

            body.append('_token', csrf);
            form.querySelectorAll('[data-pw-outlet-checkbox]:checked').forEach(function (box) { body.append('outlet_ids[]', box.value); });

            return send(form.getAttribute('data-pw-preview-url'), body).then(function (result) {
                if (seq !== previewSeq) { return null; }
                if (result.ok && result.data.ok) {
                    if (box) { box.innerHTML = result.data.html; }
                    return result.data;
                }
                if (box) {
                    box.innerHTML = '';
                    var alert = document.createElement('div');
                    alert.className = 'pw-alert pw-alert-danger';
                    alert.textContent = result.status === 422 && result.data.errors
                        ? result.data.errors[Object.keys(result.data.errors)[0]][0]
                        : 'Ringkasan perubahan tidak dapat dimuat.';
                    box.appendChild(alert);
                }
                return null;
            }).catch(function () { return null; });
        }

        function schedulePreview() {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(function () {
                var form = formFor('outlets');
                if (!form) { return; }
                if (!isDirty('outlets')) {
                    previewSeq++;
                    var box = form.querySelector('[data-pw-outlet-preview]');
                    if (box) { box.innerHTML = ''; }
                    return;
                }
                requestPreview(form);
            }, 200);
        }

        // ---- Leaving with unsaved changes ----------------------------------------------------------
        function leaveMessage() {
            return 'Perubahan di ' + dirtyLabels().join(' dan ') + ' belum disimpan dan akan hilang.';
        }

        function askLeave() {
            return confirmAsk({ title: 'Tinggalkan perubahan?', body: leaveMessage(), label: 'Tinggalkan', tone: 'warning' });
        }

        window.addEventListener('beforeunload', function (event) {
            if (leaving || !anyDirty()) { return; }
            event.preventDefault();
            event.returnValue = '';
        });

        window.addEventListener('pageshow', function () { leaving = false; });

        // Sidebar, Close, editor links, anything that leaves this page.
        document.addEventListener('click', function (event) {
            var link = event.target.closest ? event.target.closest('a[href]') : null;
            if (!link || link.hasAttribute('data-pw-nav')) { return; }
            if (link.hasAttribute('data-pw-open-drawer') && drawerEl(link.getAttribute('data-pw-open-drawer'))) { return; }   // opens a drawer, stays here
            if ((link.target && link.target !== '_self') || link.hasAttribute('download')) { return; }
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) { return; }

            var href = link.getAttribute('href') || '';
            if (href === '' || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) { return; }
            if (!anyDirty()) { return; }

            event.preventDefault();
            askLeave().then(function (ok) {
                if (!ok) { return; }
                leaving = true;
                window.location.href = link.href;
            });
        }, true);

        // The Active Outlet selector submits on change and would reload this page.
        var outletSelect = document.getElementById('active-backoffice-outlet');
        var outletValue = outletSelect ? outletSelect.value : null;

        document.addEventListener('change', function (event) {
            if (!outletSelect || event.target !== outletSelect) { return; }
            if (!anyDirty()) { outletValue = outletSelect.value; return; }

            event.stopPropagation();   // capture phase: keeps the inline onchange (submit) from running
            var chosen = outletSelect.value;
            outletSelect.value = outletValue;

            askLeave().then(function (ok) {
                if (!ok) { return; }
                leaving = true;
                outletSelect.value = chosen;
                outletSelect.form.submit();
            });
        }, true);

        // ---- Drawers (stacked) ---------------------------------------------------------------------
        // category / ingredient-category: static forms, reset on open. variant / ingredient: the form is
        // fetched when opened, so it always reflects the server (e.g. the Product's current outlets).
        var DRAWER_LABELS = { category: 'Category baru', variant: 'Variants', ingredient: 'Ingredient', 'ingredient-category': 'Ingredient Category baru' };
        var DISCARD_TITLES = { category: 'Buang Category ini?', variant: 'Buang perubahan Variant?', ingredient: 'Buang perubahan Ingredient?', 'ingredient-category': 'Buang Category ini?' };
        var stack = [];          // open drawer keys, top last
        var drawerState = {};    // key -> { snapshot, opener, saving, section }

        function drawerEl(key) { return document.querySelector('[data-pw-drawer="' + key + '"]'); }
        function backdropEl(key) { return document.querySelector('[data-pw-drawer-backdrop="' + key + '"]'); }
        function drawerFormOf(key) { var el = drawerEl(key); return el ? el.querySelector('[data-pw-drawer-form]') : null; }

        // .shell uses backdrop-filter, which makes it the containing block of position:fixed children.
        // Like the shared confirm dialog and toasts, drawers live directly under <body> instead.
        document.querySelectorAll('[data-pw-drawer-backdrop], [data-pw-drawer]').forEach(function (el) { document.body.appendChild(el); });

        function isDrawerDirty(key) {
            var state = drawerState[key];
            var form = drawerFormOf(key);
            return stack.indexOf(key) !== -1 && !!state && state.snapshot !== null && !!form && serialize(form) !== state.snapshot;
        }

        function dirtyDrawers() { return stack.filter(isDrawerDirty); }

        function drawerSection(key) {
            if (key === 'variant') { return 'variants'; }
            if (key === 'category') { return 'general'; }
            return (drawerState.ingredient && drawerState.ingredient.section) || 'stock';
        }

        function showDrawer(key, opener) {
            var el = drawerEl(key);
            var backdrop = backdropEl(key);
            var depth = stack.length;

            backdrop.style.zIndex = String(1000 + depth * 2);
            el.style.zIndex = String(1001 + depth * 2);
            el.hidden = false;
            backdrop.hidden = false;
            stack.push(key);
            document.body.classList.add('pw-drawer-open');

            drawerState[key] = { snapshot: serialize(drawerFormOf(key)), opener: opener, saving: false, section: (drawerState[key] || {}).section };

            var first = el.querySelector('input:not([type="hidden"]), select, textarea');
            if (first) { first.focus(); }
            refreshState();
        }

        function hideDrawer(key) {
            var state = drawerState[key] || {};

            drawerEl(key).hidden = true;
            backdropEl(key).hidden = true;
            stack = stack.filter(function (open) { return open !== key; });
            drawerState[key] = { snapshot: null, section: state.section };

            if (!stack.length) { document.body.classList.remove('pw-drawer-open'); }
            if (state.opener && state.opener.focus && document.body.contains(state.opener)) {
                try { state.opener.focus(); } catch (e) { /* ignore */ }
            }
            refreshState();
        }

        function closeDrawer(key) {
            if (!isDrawerDirty(key)) { hideDrawer(key); return; }
            confirmAsk({ title: DISCARD_TITLES[key], body: 'Data yang sudah diisi di panel ini akan hilang. Form lain tidak berubah.', label: 'Buang', tone: 'warning' })
                .then(function (ok) { if (ok) { hideDrawer(key); } });
        }

        function openStaticDrawer(key, opener) {
            var form = drawerFormOf(key);
            if (!form) { return; }

            form.reset();
            clearErrors(form, 'data-pw-drawer-error');

            if (key === 'category') {
                var brand = root.querySelector('[data-pw-brand-select]');
                var drawerBrand = form.querySelector('[name="brand_id"]');
                if (brand && drawerBrand && drawerBrand.querySelector('option[value="' + brand.value + '"]')) { drawerBrand.value = brand.value; }
            }

            showDrawer(key, opener);
        }

        function openFetchedDrawer(key, url, opener) {
            var el = drawerEl(key);
            var content = el.querySelector('[data-pw-drawer-content]');

            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (response) {
                    return response.json().catch(function () { return null; }).then(function (data) {
                        return { ok: response.ok, data: data || {} };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.data.ok) {
                        toast('error', result.data.message || 'Form tidak dapat dibuka.');
                        return;
                    }
                    content.innerHTML = result.data.html;
                    if (key === 'ingredient') {
                        var section = content.querySelector('[name="return_section"]');
                        drawerState.ingredient = { section: section ? section.value : 'stock' };
                    }
                    content.querySelectorAll('.pw-rupiah').forEach(formatRupiahInput);
                    showDrawer(key, opener);
                })
                .catch(function () { toast('error', 'Koneksi terputus. Form tidak dapat dibuka.'); });
        }

        function addOption(select, item) {
            if (!select) { return; }

            var option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.name;

            var before = Array.prototype.find.call(select.options, function (existing) {
                return existing.value !== '' && existing.textContent.localeCompare(item.name, 'id', { sensitivity: 'base' }) > 0;
            });
            select.insertBefore(option, before || null);
            select.value = String(item.id);
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        document.addEventListener('click', function (event) {
            if (!event.target.closest) { return; }

            var opener = event.target.closest('[data-pw-open-drawer]');
            if (opener) {
                if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) { return; }
                var key = opener.getAttribute('data-pw-open-drawer');
                if (!drawerEl(key)) { return; }   // no drawer for this user: the link works as a normal page
                event.preventDefault();
                if (stack.indexOf(key) !== -1) { return; }
                if (opener.hasAttribute('data-pw-form-url')) {
                    openFetchedDrawer(key, opener.getAttribute('data-pw-form-url'), opener);
                } else {
                    openStaticDrawer(key, opener);
                }
                return;
            }

            var cancel = event.target.closest('[data-pw-drawer-cancel]');
            var cancelDrawer = cancel ? cancel.closest('[data-pw-drawer]') : null;
            if (cancelDrawer) {
                event.preventDefault();
                closeDrawer(cancelDrawer.getAttribute('data-pw-drawer'));
                return;
            }

            if (event.target.hasAttribute && event.target.hasAttribute('data-pw-drawer-backdrop')) {
                closeDrawer(event.target.getAttribute('data-pw-drawer-backdrop'));
            }
        });

        // Esc / Tab act on the TOP drawer only, and never while the confirm dialog is on top of it.
        document.addEventListener('keydown', function (event) {
            if (!stack.length) { return; }
            if (window.BackofficeConfirm && window.BackofficeConfirm.isOpen()) { return; }

            var top = stack[stack.length - 1];

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                closeDrawer(top);
                return;
            }

            if (event.key === 'Tab') {
                var focusable = Array.prototype.filter.call(
                    drawerEl(top).querySelectorAll('button, input:not([type="hidden"]), select, textarea, a[href]'),
                    function (el) { return !el.disabled && el.offsetParent !== null; }
                );
                if (!focusable.length) { return; }
                var first = focusable[0];
                var last = focusable[focusable.length - 1];
                if (!drawerEl(top).contains(document.activeElement)) { event.preventDefault(); first.focus(); return; }
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        }, true);

        function onDrawerSaved(key, data) {
            if (key === 'category' || key === 'ingredient-category') {
                var select = key === 'category'
                    ? root.querySelector('[data-pw-category-select]')
                    : (drawerFormOf('ingredient') || document.createElement('form')).querySelector('[data-pw-ingredient-category-select]');
                if (data.category.is_active) { addOption(select, data.category); }
                hideDrawer(key);
                toast(data.category.is_active ? 'success' : 'warning', data.message);
                return;
            }

            // Variant / Ingredient: refresh the workspace from the server and stay on the section.
            hideDrawer(key);
            applySaved(data, null);
            if (data.section) { showSection(data.section, true); }
            toast('success', data.message);
        }

        document.addEventListener('submit', function (event) {
            var form = event.target.closest ? event.target.closest('[data-pw-drawer-form]') : null;
            var el = form ? form.closest('[data-pw-drawer]') : null;
            if (!el) { return; }
            event.preventDefault();

            var key = el.getAttribute('data-pw-drawer');
            var state = drawerState[key] || {};
            if (state.saving) { return; }

            var button = form.querySelector('[data-pw-drawer-save]');
            state.saving = true;
            if (button) { button.disabled = true; }
            clearErrors(form, 'data-pw-drawer-error');

            send(form.action, new FormData(form)).then(function (result) {
                if (result.ok && result.data.ok) { onDrawerSaved(key, result.data); return; }
                if (result.status === 422) { showErrors(form, 'data-pw-drawer-error', result.data.errors); }
                failureToast(result);
            }).catch(function () {
                toast('error', 'Koneksi terputus. Data belum tersimpan, silakan coba lagi.');
            }).then(function () {
                state.saving = false;
                if (button) { button.disabled = false; }
                refreshState();
            });
        });

        // ---- Init ----------------------------------------------------------------------------------
        // Drawer buttons that only make sense with JS (e.g. "+ Buat Category") start hidden.
        function enhance() {
            root.querySelectorAll('[data-pw-open-drawer][hidden]').forEach(function (button) {
                button.hidden = !drawerEl(button.getAttribute('data-pw-open-drawer'));
            });
        }

        EDITABLE.forEach(initialSnapshot);
        enhance();
        syncHeaderHeight();
        refreshState();

        window.ProductWorkspace = { root: root, showSection: showSection, isDirty: isDirty, dirtyKeys: dirtyKeys };
    })();
</script>
