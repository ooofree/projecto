const materialSearch = document.getElementById('material-search');
const materialCategory = document.getElementById('material-category');

if (materialSearch && materialCategory) {
    const materialRows = [...document.querySelectorAll('.material-row')];
    const materialCount = document.getElementById('material-count');
    const noMaterialMatch = document.getElementById('materials-no-match');

    function filterMaterials() {
        const query = materialSearch.value.trim().toLocaleLowerCase();
        const categoryId = materialCategory.value;
        let visible = 0;
        for (const row of materialRows) {
            const matches = row.dataset.search.includes(query)
                && (categoryId === '' || row.dataset.category === categoryId);
            row.hidden = !matches;
            if (matches) visible++;
        }
        materialCount.textContent = `${visible}${materialCount.dataset.unit}`;
        if (noMaterialMatch) noMaterialMatch.hidden = visible !== 0;
    }

    materialSearch.addEventListener('input', filterMaterials);
    materialCategory.addEventListener('change', filterMaterials);
}

document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-disclosure-open]');
    if (trigger) {
        const disclosure = document.getElementById(trigger.dataset.disclosureOpen);
        if (disclosure) {
            disclosure.open = true;
            disclosure.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    const dialogTrigger = event.target.closest('[data-dialog-open]');
    if (dialogTrigger) {
        document.getElementById(dialogTrigger.dataset.dialogOpen)?.showModal();
    }

    if (event.target.closest('[data-dialog-close]')) {
        event.target.closest('dialog')?.close();
    }

    const copyTrigger = event.target.closest('[data-copy-share]');
    if (copyTrigger) {
        copyShareLink(copyTrigger);
    }
});

// Copy the share link as plain text: exactly the URL, nothing else, so it
// pastes into WeChat (or anywhere) as a clickable link. WeChat's built-in
// browser and plain-http LAN pages often lack navigator.clipboard, so try the
// async API first and fall back to a temporary textarea + execCommand. Only
// report success when the clipboard really received the URL; otherwise select
// the visible link and say so honestly instead of faking a "copied" state.
async function copyShareLink(button) {
    const dialog = button.closest('dialog') ?? document;
    const field = dialog.querySelector('[data-share-link]');
    const feedback = dialog.querySelector('[data-share-feedback]');
    const original = button.dataset.shareLabel || button.textContent;
    button.dataset.shareLabel = original;

    // Resolve the URL defensively. Older cached copies of this script read
    // `field.value`, which is undefined on an <a> and used to put the literal
    // text "undefined" on the clipboard. Every candidate is validated, and
    // nothing is ever written unless it is a real http(s) URL.
    const candidates = [
        button.dataset.shareUrl,
        button.getAttribute('data-share-url'),
        field ? field.getAttribute('href') : null,
        field ? field.textContent : null,
        dialog.querySelector('[data-share-url]')?.getAttribute('data-share-url'),
    ];
    let url = '';
    for (const candidate of candidates) {
        if (typeof candidate !== 'string') continue;
        const value = candidate.trim();
        if (/^https?:\/\/[^\s]+$/i.test(value)) {
            url = value;
            break;
        }
    }
    if (url === '') {
        setShareFeedback(feedback, '没能读到分享链接，请关闭对话框后重新打开「分享链接」。', true);
        return;
    }

    // Card mode puts the project title and a one-line summary above the URL so
    // the chat message is informative even where no link preview is generated.
    const withTitle = button.dataset.shareMode === 'card';
    const title = typeof button.dataset.shareTitle === 'string' ? button.dataset.shareTitle.trim() : '';
    const desc = typeof button.dataset.shareDesc === 'string' ? button.dataset.shareDesc.trim() : '';
    const payload = withTitle && title !== ''
        ? title + (desc !== '' ? '\n' + desc : '') + '\n' + url
        : url;
    // Final guard: never let a stringified undefined/null reach the clipboard.
    if (typeof payload !== 'string' || payload === '' || /\bundefined\b|\bnull\b/.test(payload.split('\n')[0])) {
        setShareFeedback(feedback, '分享内容异常，已阻止复制，请重新打开「分享链接」。', true);
        return;
    }

    let copied = await writeClipboardText(payload, button.closest('dialog') ?? document.body);

    if (copied) {
        button.textContent = '已复制 ✓';
        setShareFeedback(feedback, withTitle && title !== ''
            ? '标题和链接已复制，去微信粘贴发送即可。'
            : '链接已复制，去微信粘贴发送即可。', false);
    } else {
        // Honest fallback: show a selectable field holding the exact payload so
        // a long-press / Ctrl+C copies it. Never claim unverified success.
        revealManualCopy(dialog, payload);
        button.textContent = original;
        setShareFeedback(feedback, '当前浏览器不允许自动复制：下方内容已选中，长按（或按 Ctrl+C）复制后到微信粘贴。', true);
    }
    window.clearTimeout(button._shareCopyTimer);
    button._shareCopyTimer = window.setTimeout(() => {
        button.textContent = button.dataset.shareLabel || original;
    }, 2200);
}

/**
 * Write text to the clipboard across browsers, including WeChat's WebView.
 * Order: async Clipboard API (secure contexts) -> execCommand on a temporary
 * textarea inside the open dialog (elements outside a modal are inert and
 * cannot take focus). Returns true only when a copy actually succeeded.
 */
async function writeClipboardText(text, host) {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            // fall through to the legacy path
        }
    }
    return legacyCopyShareUrl(text, host);
}

/**
 * Last resort: put the exact payload in a visible, pre-selected field so the
 * user can copy it manually. Reuses one node so repeated attempts do not pile
 * up elements inside the dialog.
 */
function revealManualCopy(dialog, payload) {
    let box = dialog.querySelector('[data-share-manual]');
    if (!box) {
        box = document.createElement('textarea');
        box.setAttribute('data-share-manual', '');
        box.setAttribute('readonly', '');
        box.setAttribute('aria-label', '手动复制分享内容');
        box.rows = 3;
        box.className = 'share-manual-copy';
        const anchor = dialog.querySelector('[data-share-feedback]');
        if (anchor && anchor.parentNode) {
            anchor.parentNode.insertBefore(box, anchor);
        } else {
            dialog.appendChild(box);
        }
    }
    box.value = payload;
    box.hidden = false;
    box.focus();
    box.select();
    box.setSelectionRange(0, payload.length);
}

// execCommand fallback for non-secure contexts (http LAN) and older WebViews.
// The temporary textarea holds only the URL and is removed immediately. It
// must live inside the open <dialog>: elements outside a modal dialog are
// inert and cannot take focus, which would silently break the copy.
function legacyCopyShareUrl(url, host) {
    const area = document.createElement('textarea');
    area.value = url;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.top = '0';
    area.style.left = '0';
    area.style.opacity = '0';
    host.appendChild(area);
    area.focus();
    area.select();
    area.setSelectionRange(0, url.length);
    let ok = false;
    try { ok = document.execCommand('copy'); } catch { ok = false; }
    area.remove();
    return ok;
}

function setShareFeedback(node, message, isWarning) {
    if (!node) return;
    node.textContent = message;
    node.classList.toggle('is-warning', isWarning);
    node.hidden = false;
}

// The public share page is a standalone static file with its own inline
// search/filter script; nothing here serves it anymore.

document.addEventListener('submit', event => {
    const form = event.target.closest('form[data-confirm]');
    if (form && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

for (const search of document.querySelectorAll('[data-list-search]')) {
    const list = document.getElementById(search.dataset.listSearch);
    if (!list) continue;

    const panel = search.closest('.panel');
    const emptyState = panel?.querySelector('[data-list-empty]');
    const count = panel?.querySelector('[data-list-count]');
    const items = [...list.querySelectorAll('[data-list-item]')];
    const filter = () => {
        const query = search.value.trim().toLocaleLowerCase();
        let visibleCount = 0;
        for (const item of items) {
            const visible = (item.dataset.search ?? '').includes(query);
            item.hidden = !visible;
            if (visible) visibleCount++;
        }
        if (emptyState) emptyState.hidden = visibleCount !== 0;
        if (count) count.textContent = `${visibleCount}${count.dataset.unit ?? ' 条'}`;
    };

    search.addEventListener('input', filter);
}

// Material estimates page: fast entry (searchable material picker, remembered
// space selection) and a grouped/filterable record ledger. Everything here is
// progressive enhancement: without JavaScript the original selects still work.
(() => {
    const normalize = text => String(text).toLowerCase().replace(/\s+/g, ' ').trim();

    function initCombos() {
        const combos = [];
        let comboIndex = 0;
        for (const combo of document.querySelectorAll('[data-combo]')) {
            const select = combo.querySelector('select[data-combo-select]');
            const input = combo.querySelector('[data-combo-input]');
            const list = combo.querySelector('[data-combo-options]');
            if (!select || !input || !list) continue;

            const options = [...select.options].filter(option => option.value !== '');
            if (options.length === 0) continue;
            const labelOf = option => normalize(option.textContent);
            const form = combo.closest('form');
            const comboId = 'combo-list-' + (++comboIndex);
            list.id = comboId;
            input.setAttribute('aria-controls', comboId);
            input.setAttribute('aria-autocomplete', 'list');
            let matches = [];
            let active = -1;

            combo.classList.add('is-enhanced');
            select.classList.add('combo-native');
            // The native select stays the submitted value but is not the focus
            // target any more; the visible input carries the validation.
            select.required = false;
            select.tabIndex = -1;
            select.setAttribute('aria-hidden', 'true');

            const chosenOption = () => options.find(option => option.value === select.value) ?? null;
            const syncInput = () => {
                const chosen = chosenOption();
                if (chosen) input.value = chosen.textContent.replace(/\s+/g, ' ').trim();
            };
            const close = () => {
                list.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                input.removeAttribute('aria-activedescendant');
                active = -1;
            };
            const setActive = index => {
                const items = [...list.children];
                for (const item of items) item.classList.remove('is-active');
                if (items.length === 0) {
                    active = -1;
                    return;
                }
                active = (index + items.length) % items.length;
                const item = items[active];
                item.classList.add('is-active');
                if (item.id !== '') input.setAttribute('aria-activedescendant', item.id);
                item.scrollIntoView({ block: 'nearest' });
            };
            const render = query => {
                const needle = normalize(query);
                matches = needle === '' ? options : options.filter(option => labelOf(option).includes(needle));
                list.innerHTML = '';
                for (const option of matches.slice(0, 100)) {
                    const item = document.createElement('li');
                    item.setAttribute('role', 'option');
                    item.id = comboId + '-' + list.children.length;
                    item.textContent = option.textContent.replace(/\s+/g, ' ').trim();
                    item.addEventListener('mousedown', event => {
                        event.preventDefault();
                        choose(option);
                    });
                    list.appendChild(item);
                }
                if (matches.length === 0) {
                    const empty = document.createElement('li');
                    empty.className = 'combo-empty';
                    empty.textContent = '没有匹配的材料';
                    list.appendChild(empty);
                }
                active = -1;
            };
            const open = (query = '') => {
                render(query);
                list.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            };
            const choose = option => {
                select.value = option.value;
                input.value = option.textContent.replace(/\s+/g, ' ').trim();
                input.setCustomValidity('');
                close();
                select.dispatchEvent(new Event('change', { bubbles: true }));
                // After picking a material the quantity is the only field left
                // for the common case, so move the caret there.
                const addForm = combo.closest('form[data-estimate-add]');
                if (addForm) {
                    const quantity = addForm.querySelector('input[name="estimated_quantity"]');
                    if (quantity) {
                        quantity.focus();
                        quantity.select();
                    }
                }
            };

            input.addEventListener('focus', () => {
                if (chosenOption()) input.select();
                open('');
            });
            input.addEventListener('input', () => {
                select.value = '';
                input.setCustomValidity('');
                open(input.value);
            });
            input.addEventListener('keydown', event => {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    if (list.hidden) open(input.value);
                    setActive(active + 1);
                    return;
                }
                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (list.hidden) open(input.value);
                    setActive(active - 1);
                    return;
                }
                if (event.key === 'Escape') {
                    if (!list.hidden) {
                        event.preventDefault();
                        close();
                    }
                    return;
                }
                if (event.key === 'Enter' && !list.hidden) {
                    event.preventDefault();
                    const items = matches.slice(0, 100);
                    if (active >= 0) {
                        if (items[active]) choose(items[active]);
                    } else if (items.length === 1) {
                        choose(items[0]);
                    } else if (items.length > 0) {
                        // Ambiguous query: highlight the first match instead of
                        // silently choosing one of several materials.
                        setActive(0);
                    }
                }
            });
            input.addEventListener('blur', () => window.setTimeout(close, 120));
            select.addEventListener('change', syncInput);
            combos.push({ combo, close });

            if (form) {
                form.addEventListener('submit', event => {
                    if (select.value !== '') return;
                    const needle = normalize(input.value);
                    if (needle !== '') {
                        const exact = options.find(option => labelOf(option) === needle);
                        const partials = options.filter(option => labelOf(option).includes(needle));
                        const resolved = exact ?? (partials.length === 1 ? partials[0] : null);
                        if (resolved) {
                            select.value = resolved.value;
                            input.value = resolved.textContent.trim();
                            input.setCustomValidity('');
                            return;
                        }
                    }
                    event.preventDefault();
                    input.setCustomValidity(needle !== '' ? '请从搜索结果中选择材料。' : '请选择材料。');
                    input.reportValidity();
                    input.focus();
                    open(needle);
                });
            }

            syncInput();
        }
        document.addEventListener('click', event => {
            for (const entry of combos) {
                if (!entry.combo.contains(event.target)) entry.close();
            }
        });
    }

    function initCascades() {
        for (const form of document.querySelectorAll('form[data-estimate-form]')) {
            const building = form.querySelector('select[data-cascade-building]');
            const floor = form.querySelector('select[data-cascade-floor]');
            const room = form.querySelector('select[data-cascade-room]');
            if (!building || !floor || !room) continue;

            const floorOptions = [...floor.options]
                .filter(option => option.value !== '')
                .map(option => ({ value: option.value, label: option.textContent.trim(), building: option.dataset.buildingId ?? '' }));
            const roomOptions = [...room.options]
                .filter(option => option.value !== '')
                .map(option => ({
                    value: option.value,
                    label: option.textContent.trim(),
                    building: option.dataset.buildingId ?? '',
                    floor: option.dataset.floorId ?? '',
                }));

            const rebuildFloors = selected => {
                floor.length = 0;
                floor.add(new Option('不指定', ''));
                for (const option of floorOptions) {
                    if (option.building === building.value) floor.add(new Option(option.label, option.value));
                }
                if (selected && [...floor.options].some(item => item.value === selected)) floor.value = selected;
            };
            const rebuildRooms = selected => {
                room.length = 0;
                room.add(new Option('不指定', ''));
                for (const option of roomOptions) {
                    if (option.building === building.value && option.floor === floor.value) room.add(new Option(option.label, option.value));
                }
                if (selected && [...room.options].some(item => item.value === selected)) room.value = selected;
            };

            const isAddForm = form.hasAttribute('data-estimate-add');
            const storageKey = 'estimate-location:' + (form.dataset.projectId ?? '');
            const readLocation = () => {
                try {
                    return JSON.parse(window.localStorage.getItem(storageKey) ?? 'null');
                } catch {
                    return null;
                }
            };
            const saveLocation = () => {
                if (!isAddForm) return;
                try {
                    window.localStorage.setItem(storageKey, JSON.stringify({
                        building: building.value,
                        floor: floor.value,
                        room: room.value,
                    }));
                } catch {
                    // Storage may be unavailable (private mode); the form still works.
                }
            };

            const saved = isAddForm ? readLocation() : null;
            if (saved && typeof saved === 'object' && [...building.options].some(option => option.value === saved.building)) {
                building.value = saved.building;
            }
            rebuildFloors(saved?.floor ?? floor.value);
            rebuildRooms(saved?.room ?? room.value);

            building.addEventListener('change', () => {
                rebuildFloors('');
                rebuildRooms('');
                saveLocation();
            });
            floor.addEventListener('change', () => {
                rebuildRooms('');
                saveLocation();
            });
            room.addEventListener('change', saveLocation);
        }
    }

    function initLedgerFilter() {
        const filter = document.querySelector('[data-estimate-filter]');
        const ledger = document.querySelector('[data-estimate-ledger]');
        if (!filter || !ledger) return;
        document.querySelector('[data-estimate-filter-field]')?.removeAttribute('hidden');

        const records = [...ledger.querySelectorAll('[data-ledger-record]')];
        const groups = [...ledger.querySelectorAll('[data-ledger-group]')];
        const subgroups = [...ledger.querySelectorAll('[data-ledger-subgroup]')];
        const empty = document.querySelector('[data-estimate-empty]');
        const count = document.querySelector('[data-estimate-count]');
        // The export form submits the same keyword the live filter uses, so the
        // downloaded records always match what the page currently shows.
        const exportSearch = document.querySelector('#estimate-export-form input[name="search"]');
        const unit = count?.dataset.unit ?? ' 条';

        const apply = () => {
            const needle = normalize(filter.value);
            let visible = 0;
            for (const record of records) {
                const matches = needle === '' || (record.dataset.search ?? '').includes(needle);
                record.hidden = !matches;
                if (!matches) continue;
                visible++;
                if (needle !== '') {
                    const group = record.closest('[data-ledger-group]');
                    const subgroup = record.closest('[data-ledger-subgroup]');
                    if (group) group.open = true;
                    if (subgroup) subgroup.open = true;
                }
            }
            for (const subgroup of subgroups) {
                const shown = [...subgroup.querySelectorAll('[data-ledger-record]')].filter(item => !item.hidden).length;
                subgroup.hidden = shown === 0;
                const badge = subgroup.querySelector('[data-subgroup-count]');
                if (badge) badge.textContent = shown + unit;
            }
            for (const group of groups) {
                const shown = [...group.querySelectorAll('[data-ledger-record]')].filter(item => !item.hidden).length;
                group.hidden = shown === 0;
                const badge = group.querySelector('[data-group-count]');
                if (badge) badge.textContent = shown + unit;
            }
            if (count) count.textContent = visible + unit;
            if (empty) empty.hidden = visible !== 0;
            if (exportSearch) exportSearch.value = filter.value;
        };

        filter.addEventListener('input', apply);
    }

    initCombos();
    initCascades();
    initLedgerFilter();
})();
