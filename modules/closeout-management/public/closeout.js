// Closeout workspace progressive enhancement:
// - click any list row to open it in the side panel (no page jump needed);
// - batch bar tracks selection and rejects empty/field-less submits;
// - BOM select dialog tracks selectable materials and guards empty submits;
// - dialogs rendered with [open] are upgraded to modal when JS is available.
// The server stays authoritative: without JS every form still submits and the
// service rejects empty or conflicting selections.
(() => {
    // ---------------------------------------------------------------- viewport
    // Every action posts and reloads the page, and a fresh load starts at the
    // top — which throws the user away from the row they just acted on. Remember
    // the scroll position (and the expanded space lists) for one short hop, then
    // restore it after the reload.
    const SCROLL_KEY = 'closeout:scroll';
    const EXPANDED_KEY = 'closeout:expanded';
    const pageName = new URLSearchParams(window.location.search).get('page') || '';

    // Opening an item is also a full navigation (?page=closeouts&item_id=…), so
    // it must be recorded too — otherwise clicking any row lands at the top.
    const persistScroll = itemId => {
        let rowTop = null;
        const id = String(itemId || '');
        if (id) {
            const row = document.getElementById('closeout-item-' + id);
            if (row) rowTop = row.getBoundingClientRect().top + window.scrollY;
        }
        try {
            sessionStorage.setItem(SCROLL_KEY, JSON.stringify({
                y: window.scrollY,
                page: pageName,
                itemId: id || null,
                rowTop,
                at: Date.now(),
            }));
        } catch (error) {
            // Private mode / storage disabled: nothing to restore from.
        }
    };

    const rememberScroll = event => {
        const form = (event && event.target) || null;
        let itemId = '';
        const itemInput = form && form.querySelector ? form.querySelector('input[name="item_id"]') : null;
        if (itemInput) {
            itemId = String(itemInput.value || '');
        } else if (form && form.closest) {
            const row = form.closest('tr[data-item-id]');
            if (row) itemId = String(row.dataset.itemId || '');
        }
        persistScroll(itemId);
    };
    document.addEventListener('submit', event => {
        if (!event.defaultPrevented) rememberScroll(event);
    }, true);

    // ------------------------------------------------------- in-place detail
    // Opening an item used to be a full navigation, so any scroll fix was a
    // patch on top of a page that had already reloaded. Instead the detail
    // panel is refreshed in place: no reload, no scroll movement at all.
    const detailPanel = document.getElementById('closeout-detail');
    let detailRequest = 0;

    const highlightRow = itemId => {
        for (const row of document.querySelectorAll('[data-closeout-row]')) {
            row.classList.toggle('is-selected', String(row.dataset.itemId || '') === String(itemId));
        }
    };

    const openItem = async (itemId, href) => {
        if (!detailPanel || !href) {
            window.location.href = href || window.location.href;
            return;
        }
        const token = ++detailRequest;
        highlightRow(itemId);
        detailPanel.classList.add('is-loading');
        try {
            const url = /[?&]item_id=\d+/.test(href)
                ? href
                : href + (href.indexOf('?') === -1 ? '?' : '&') + 'item_id=' + encodeURIComponent(itemId);
            const response = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const html = await response.text();
            if (token !== detailRequest) return;
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const fresh = parsed.getElementById('closeout-detail');
            if (!fresh) throw new Error('detail missing');
            detailPanel.innerHTML = fresh.innerHTML;
            bindDetail(detailPanel);
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', url);
            }
        } catch (error) {
            // Anything unexpected: fall back to the ordinary navigation, which
            // the scroll restore below already covers.
            persistScroll(itemId);
            window.location.href = href;
        } finally {
            detailPanel.classList.remove('is-loading');
        }
    };

    document.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        const target = event.target;
        const link = target && target.closest ? target.closest('a[href]') : null;
        if (!link) return;
        const href = link.getAttribute('href') || '';
        if (href.indexOf('page=') === -1) return;
        const match = href.match(/[?&]item_id=(\d+)/);
        if (!match || href.indexOf('#') !== -1) {
            // Filters, exports, anchors: normal navigation, keep the position.
            persistScroll(match ? match[1] : '');
            return;
        }
        event.preventDefault();
        openItem(match[1], href);
    }, true);

    // Row click = select. Interactive controls keep their own behaviour.
    for (const row of document.querySelectorAll('[data-closeout-row]')) {
        const href = row.dataset.href;
        if (!href) continue;
        row.addEventListener('click', event => {
            if (event.target.closest('a, button, input, select, textarea, label, form, details, summary')) {
                return;
            }
            openItem(row.dataset.itemId || '', href);
        });
        row.addEventListener('keydown', event => {
            if (event.key === 'Enter' && event.target === row) {
                openItem(row.dataset.itemId || '', href);
            }
        });
    }

    const master = document.querySelector('[data-closeout-check-all]');
    const boxes = Array.from(document.querySelectorAll('[data-closeout-check]'));
    const batch = document.querySelector('[data-closeout-batch]');
    const selectedLabel = document.querySelector('[data-closeout-selected]');
    const updateBatchState = () => {
        const count = boxes.filter(box => box.checked).length;
        if (selectedLabel) selectedLabel.textContent = `已选 ${count} 项`;
        if (batch) batch.classList.toggle('has-selection', count > 0);
        if (master) {
            master.checked = count > 0 && count === boxes.length;
            master.indeterminate = count > 0 && count < boxes.length;
        }
    };

    if (master && boxes.length > 0) {
        master.addEventListener('change', () => {
            for (const box of boxes) box.checked = master.checked;
            updateBatchState();
        });
    }
    for (const box of boxes) box.addEventListener('change', updateBatchState);
    updateBatchState();

    if (batch) {
        batch.addEventListener('submit', event => {
            if (!boxes.some(box => box.checked)) {
                event.preventDefault();
                window.alert('请先勾选需要批量调整的事项。');
                return;
            }
            const date = batch.querySelector('input[name="planned_end_date"]')?.value ?? '';
            const user = batch.querySelector('select[name="responsible_user_id"]')?.value ?? '';
            const supplier = batch.querySelector('select[name="responsible_supplier_id"]')?.value ?? '';
            if (date === '' && user === '' && supplier === '') {
                event.preventDefault();
                window.alert('请至少填一项要批量调整的内容：计划完成日期、责任人或责任单位。');
            }
        });
    }

    const selectForm = document.querySelector('[data-closeout-select-form]');
    if (selectForm) {
        const materials = Array.from(selectForm.querySelectorAll('[data-closeout-material]'));
        const selectAll = selectForm.querySelector('[data-closeout-material-all]');
        const selectLabel = document.querySelector('[data-closeout-select-count]');
        const updateSelectState = () => {
            const count = materials.filter(item => item.checked).length;
            if (selectLabel) selectLabel.textContent = `已选 ${count} 项`;
            if (selectAll) {
                const enabled = materials.filter(item => !item.disabled);
                selectAll.checked = count > 0 && count === enabled.length;
                selectAll.indeterminate = count > 0 && count < enabled.length;
            }
        };
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                for (const item of materials) {
                    if (!item.disabled) item.checked = selectAll.checked;
                }
                updateSelectState();
            });
        }
        for (const item of materials) item.addEventListener('change', updateSelectState);
        updateSelectState();
        selectForm.addEventListener('submit', event => {
            if (!materials.some(item => item.checked)) {
                event.preventDefault();
                window.alert('请先勾选至少一项可纳入的 BOM 材料。');
            }
        });
    }

    // Expand / collapse the space list of one item row in place, and keep the
    // opened rows open across a reload (acting inside them reloads the page).
    const readExpanded = () => {
        try {
            return new Set((JSON.parse(sessionStorage.getItem(EXPANDED_KEY) || '[]') || []).map(String));
        } catch (error) {
            return new Set();
        }
    };
    const writeExpanded = expanded => {
        try {
            sessionStorage.setItem(EXPANDED_KEY, JSON.stringify([...expanded]));
        } catch (error) {
            // Storage unavailable: expansion simply does not survive the reload.
        }
    };
    const expanded = readExpanded();
    for (const toggle of document.querySelectorAll('[data-closeout-loc-toggle]')) {
        const row = toggle.closest('tr');
        const panel = row && row.nextElementSibling;
        if (!panel || !panel.hasAttribute('data-closeout-loc-panel')) continue;
        const itemId = String(panel.dataset.itemId || '');
        const setOpen = open => {
            panel.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.textContent = open ? '收起空间' : '展开空间';
        };
        if (itemId && expanded.has(itemId)) setOpen(true);
        toggle.addEventListener('click', () => {
            const willOpen = panel.hidden;
            setOpen(willOpen);
            if (!itemId) return;
            if (willOpen) {
                expanded.add(itemId);
            } else {
                expanded.delete(itemId);
            }
            writeExpanded(expanded);
        });
    }

    // Restore the viewport only after the expanded panels exist again — opening
    // them changes the height above the row, so a raw pixel offset would land
    // on the wrong place. Anchor to the acted row instead: keep the same offset
    // between the row and the viewport top, whatever moved above it.
    let savedScroll = null;
    try {
        const raw = sessionStorage.getItem(SCROLL_KEY);
        if (raw) {
            sessionStorage.removeItem(SCROLL_KEY);
            savedScroll = JSON.parse(raw);
        }
    } catch (error) {
        savedScroll = null;
    }

    if (savedScroll && typeof savedScroll.y === 'number') {
        const fresh = Date.now() - Number(savedScroll.at || 0) < 30000;
        // Closeout list / list-by-items / item page are the same errand: any
        // hop between them keeps the position.
        const closeoutPages = ['closeouts', 'closeout-items', 'closeout-item'];
        const sameFlow = closeoutPages.indexOf(savedScroll.page) !== -1
            && closeoutPages.indexOf(pageName) !== -1;
        if (fresh && sameFlow) {
            // The redirect carries `#closeout-item-x` for the no-JS case. With
            // JS the browser would scroll there on load, fighting our restore,
            // so drop the fragment before positioning.
            if (window.location.hash) {
                history.replaceState(null, '', window.location.pathname + window.location.search);
            }
            const restore = () => {
                const savedY = Number(savedScroll.y);
                const rowTop = savedScroll.rowTop;
                const itemId = savedScroll.itemId;
                let target = savedY;
                if (itemId && typeof rowTop === 'number') {
                    const row = document.getElementById('closeout-item-' + itemId);
                    if (row) {
                        const offset = savedY - Number(rowTop);
                        target = row.getBoundingClientRect().top + window.scrollY - offset;
                    }
                }
                window.scrollTo(0, Math.max(0, target));
            };
            restore();
            requestAnimationFrame(restore);
            // Later passes absorb fonts, images and the side panel opening.
            setTimeout(restore, 120);
            setTimeout(restore, 350);
            window.addEventListener('load', restore, { once: true });
        }
    }

    // Ticking a space = submit for acceptance. Never closes anything by itself:
    // the server only moves it to pending_accept, acceptance needs a separate
    // permission-guarded action. Re-runnable: the detail panel is swapped in
    // place, so freshly injected forms need the same wiring.
    function bindDetail(scope) {
        if (!scope) return;
        for (const box of scope.querySelectorAll('[data-closeout-location-check]')) {
            if (box.dataset.closeoutBound === '1') continue;
            box.dataset.closeoutBound = '1';
            box.addEventListener('change', () => {
                if (!box.checked) return;
                const form = box.closest('form');
                if (!form) return;
                // One click = submitted. No confirmation dialog: the action is
                // reversible-ish (it only moves the space to 待验收, acceptance
                // still needs a separate click) and the prompt just added a
                // click to the hottest path on site.
                // form.submit() skips the submit event, so save the position here.
                rememberScroll({ target: form });
                form.submit();
            });
        }

        for (const dialog of scope.querySelectorAll('dialog[data-auto-open]')) {
            if (typeof dialog.showModal !== 'function' || dialog.dataset.closeoutUpgraded === '1') continue;
            dialog.dataset.closeoutUpgraded = '1';
            try {
                // The server renders `open` as a no-JS fallback; a dialog that is
                // already open cannot be upgraded to modal, so close it first.
                dialog.removeAttribute('open');
                dialog.showModal();
            } catch (error) {
                dialog.setAttribute('open', 'open');
            }
        }
    }
    bindDetail(document);

    // "/" focuses the first live search on the page (keyboard-first entry).
    document.addEventListener('keydown', event => {
        if (event.key !== '/' || event.target.closest('input, textarea, select, [contenteditable]')) {
            return;
        }
        const search = document.querySelector('input[data-list-search]');
        if (search) {
            event.preventDefault();
            search.focus();
        }
    });
})();
