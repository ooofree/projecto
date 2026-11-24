// Materials catalogue page: persistent category rail + continuous entry.
// Selecting a category keeps the list AND the entry form on that category, so
// a batch of same-kind materials can be numbered while seeing what already
// exists. Saves post asynchronously (same pattern as the estimates quick
// entry); without JavaScript the forms still submit normally and the category
// select in the toolbar filters nothing but still scopes the Excel export.
(() => {
    if (document.documentElement.dataset.materialUiInit === '1') return;
    const workspace = document.querySelector('[data-material-workspace]');
    if (!workspace) return;
    document.documentElement.dataset.materialUiInit = '1';

    const search = document.getElementById('material-search');
    const categorySelect = document.getElementById('material-category');
    const countLabel = document.getElementById('material-count');
    const totalLabel = workspace.querySelector('[data-material-total]');
    const noMatch = document.getElementById('materials-no-match');
    const tbody = workspace.querySelector('[data-materials-body]');
    const rail = document.getElementById('material-rail');
    const entryForm = document.querySelector('form[data-material-add]');
    const entryCategory = entryForm ? entryForm.querySelector('[data-entry-category]') : null;
    const followToggle = entryForm ? entryForm.querySelector('[data-entry-follow]') : null;
    const categoryStat = entryForm ? entryForm.querySelector('[data-entry-category-stat]') : null;
    const codeInput = entryForm ? entryForm.querySelector('[data-entry-code]') : null;
    const codeNextButton = entryForm ? entryForm.querySelector('[data-entry-code-next]') : null;
    const codePreview = entryForm ? entryForm.querySelector('[data-entry-code-preview]') : null;
    const codeNote = entryForm ? entryForm.querySelector('[data-entry-code-note]') : null;
    const entryStatus = entryForm ? entryForm.querySelector('[data-entry-status]') : null;
    const entryCounter = document.querySelector('[data-material-entry-counter] strong');
    const recentBox = document.querySelector('[data-entry-recent]');
    const recentChips = document.querySelector('[data-entry-recent-chips]');
    const unitList = document.getElementById('material-unit-options');
    const unitQuick = document.querySelector('[data-unit-quick]');
    const mobileBar = document.querySelector('[data-mobile-actions]');
    const dialogsHost = document.querySelector('[data-material-dialogs]');
    const isPhoneLayout = () => window.matchMedia('(max-width: 760px)').matches;

    let rows = [...workspace.querySelectorAll('.material-row')];
    let railButtons = rail ? [...rail.querySelectorAll('[data-rail-category]')] : [];
    let selectedCategory = '';
    let savedCount = 0;
    let busy = false;
    let statusTimer = 0;
    let codeDirty = false;

    const storageKey = 'material-entry:' + (entryForm ? entryForm.dataset.projectId ?? '' : '');
    const readSaved = () => {
        try { return JSON.parse(window.localStorage.getItem(storageKey) ?? 'null'); } catch { return null; }
    };
    const persistEntry = () => {
        if (!entryForm) return;
        try {
            window.localStorage.setItem(storageKey, JSON.stringify({
                category: entryCategory ? entryCategory.value : '',
                unit: entryForm.querySelector('[data-entry-unit]')?.value ?? '',
                follow: followToggle ? followToggle.checked : true,
            }));
        } catch { /* storage unavailable (private mode); the form still works */ }
    };

    // ---- mobile unit quick-pick -----------------------------------------
    // iOS/Android browsers largely ignore <datalist> or show it only after a
    // long press, so the same unit suggestions are exposed as big tap targets
    // on phones. Both controls write the same input; chips never replace the
    // text field, so arbitrary units can still be typed.
    const unitInputOf = () => (entryForm ? entryForm.querySelector('[data-entry-unit]') : null);

    const syncUnitQuick = () => {
        if (!unitQuick) return;
        const value = (unitInputOf()?.value ?? '').trim();
        for (const chip of unitQuick.querySelectorAll('[data-unit-value]')) {
            const active = chip.dataset.unitValue === value;
            chip.classList.toggle('is-active', active);
            chip.setAttribute('aria-pressed', active ? 'true' : 'false');
        }
    };

    const renderUnitQuick = () => {
        if (!unitQuick) return;
        if (!unitList) { unitQuick.hidden = true; return; }
        const units = [...unitList.querySelectorAll('option')]
            .map(option => option.value.trim())
            .filter(unit => unit !== '');
        if (units.length === 0) { unitQuick.hidden = true; return; }
        const chips = units.slice(0, 18).map(unit => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'unit-chip';
            chip.dataset.unitValue = unit;
            chip.textContent = unit;
            return chip;
        });
        unitQuick.replaceChildren(...chips);
        unitQuick.hidden = false;
        syncUnitQuick();
    };

    // ---- filtering -------------------------------------------------------
    const applyFilter = () => {
        const query = search ? search.value.trim().toLocaleLowerCase() : '';
        let visible = 0;
        for (const row of rows) {
            const matches = (row.dataset.search ?? '').includes(query)
                && (selectedCategory === '' || row.dataset.category === selectedCategory);
            row.hidden = !matches;
            if (matches) visible++;
        }
        if (countLabel) countLabel.textContent = `${visible}${countLabel.dataset.unit ?? ' 项材料'}`;
        if (noMatch) noMatch.hidden = visible !== 0;
    };

    const categoryNameOf = value => {
        if (value === '') return '全部分类';
        if (value === '0') return '未分类';
        const option = entryCategory
            ? [...entryCategory.options].find(item => item.value === value)
            : categorySelect ? [...categorySelect.options].find(item => item.value === value) : null;
        return option ? option.textContent.trim() : '所选分类';
    };

    const updateCategoryStat = () => {
        if (!categoryStat || !entryCategory) return;
        const value = entryCategory.value;
        const total = rows.filter(row => row.dataset.category === (value === '' ? '0' : value)).length;
        const name = value === '' ? '未分类' : categoryNameOf(value);
        categoryStat.textContent = `「${name}」现有 ${total} 项`;
    };

    // ---- numbering helper ------------------------------------------------
    // Suggest the next code from the codes already used in the chosen category:
    // split the longest code into prefix + trailing digits and increment the
    // number, keeping the zero padding. Pure convenience — the server keeps
    // enforcing uniqueness, and any suggestion can be overwritten by typing.
    const nextCodeFor = categoryId => {
        const codes = rows
            .filter(row => row.dataset.category === (categoryId === '' ? '0' : categoryId))
            .map(row => (row.querySelector('.report-code')?.textContent ?? '').trim())
            .filter(code => code !== '' && code !== '—');
        if (codes.length === 0) return null;
        let best = null;
        for (const code of codes) {
            const match = /^(.*?)(\d+)$/.exec(code);
            if (!match) continue;
            const candidate = { prefix: match[1], number: parseInt(match[2], 10), width: match[2].length };
            if (
                best === null
                || candidate.number > best.number
                || (candidate.number === best.number && candidate.prefix.length > best.prefix.length)
            ) {
                best = candidate;
            }
        }
        if (best === null) return null;
        const next = String(best.number + 1);
        return best.prefix + (next.length < best.width ? next.padStart(best.width, '0') : next);
    };

    const refreshCodeTools = () => {
        if (!entryForm || !codeInput || !entryCategory) return;
        const current = codeInput.value.trim();
        const typed = /^(.*?)(\d+)$/.exec(current);
        let suggestion = null;
        if (typed) {
            // Continue the user's own prefix: typing "XX-001" suggests "XX-002"
            // even when the category has never used that prefix before.
            const next = String(parseInt(typed[2], 10) + 1);
            suggestion = typed[1] + (next.length < typed[2].length ? next.padStart(typed[2].length, '0') : next);
        } else {
            suggestion = nextCodeFor(entryCategory.value);
        }
        // Codes are unique per project, so skip anything already taken.
        const taken = new Set(rows.map(row => (row.querySelector('.report-code')?.textContent ?? '').trim()));
        if (suggestion !== null && taken.has(suggestion)) {
            const parts = /^(.*?)(\d+)$/.exec(suggestion);
            let number = parseInt(parts[2], 10);
            do {
                number++;
                const text = String(number);
                suggestion = parts[1] + (text.length < parts[2].length ? text.padStart(parts[2].length, '0') : text);
            } while (taken.has(suggestion) && number < 1e9);
        }
        if (suggestion === null) {
            if (codeNextButton) codeNextButton.hidden = true;
            if (codeNote) {
                codeNote.hidden = false;
                codeNote.textContent = '该分类还没有带尾号的编码；可直接输入，或留空。';
            }
            return;
        }
        if (codeNote) codeNote.hidden = true;
        if (codeNextButton) codeNextButton.hidden = false;
        if (codePreview) codePreview.textContent = suggestion;
        const duplicate = current !== '' && taken.has(current);
        if (codeNextButton) {
            codeNextButton.classList.toggle('is-warning', duplicate);
            codeNextButton.title = duplicate ? '当前编码已存在，点击改用建议编码' : '点击填入建议编码';
        }
        if (!codeDirty && current === '') codeInput.value = suggestion;
    };

    // ---- category selection ----------------------------------------------
    const selectCategory = (value, { syncEntry = true } = {}) => {
        selectedCategory = value;
        if (categorySelect && categorySelect.value !== value) categorySelect.value = value;
        for (const button of railButtons) {
            const active = button.dataset.railCategory === value;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        }
        if (syncEntry && entryCategory && followToggle && followToggle.checked && entryCategory.value !== value) {
            entryCategory.value = value;
            codeDirty = false;
            refreshCodeTools();
            updateCategoryStat();
            persistEntry();
        }
        applyFilter();
    };

    // ---- async save + in-place refresh ------------------------------------
    const showStatus = (node, message, isError) => {
        if (!node) return;
        window.clearTimeout(statusTimer);
        node.textContent = message;
        node.classList.toggle('is-error', isError);
        node.classList.toggle('is-success', !isError);
        node.hidden = false;
        if (!isError) statusTimer = window.setTimeout(() => { node.hidden = true; }, 4000);
    };

    const extractError = async response => {
        const text = await response.text().catch(() => '');
        try {
            const doc = new DOMParser().parseFromString(text, 'text/html');
            const message = doc.querySelector('.auth-card p:not(.eyebrow)');
            if (message && message.textContent.trim() !== '') return message.textContent.trim();
        } catch { /* fall through to the generic message */ }
        if (response.status === 419) return '表单已过期，请刷新页面后重试。';
        if (response.status === 403) return '没有执行此操作的权限。';
        return '保存失败，请重试。';
    };

    const addChip = material => {
        if (!recentBox || !recentChips) return;
        recentBox.hidden = false;
        const chip = document.createElement('span');
        chip.className = 'entry-chip';
        const code = document.createElement('strong');
        code.textContent = String(material.material_code ?? '') !== '' ? String(material.material_code) : '无编码';
        const name = document.createElement('span');
        name.textContent = String(material.material_name ?? '');
        const unit = document.createElement('span');
        unit.className = 'entry-chip-place';
        unit.textContent = String(material.unit ?? '');
        chip.append(code, name, unit);
        recentChips.prepend(chip);
        while (recentChips.children.length > 10) recentChips.lastElementChild.remove();
    };

    const refreshCatalogue = async (highlightId, { scroll = true } = {}) => {
        const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) return;
        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
        const freshBody = doc.querySelector('[data-materials-body]');
        if (freshBody && tbody) {
            tbody.replaceChildren(...freshBody.childNodes);
            rows = [...tbody.querySelectorAll('.material-row')];
        }
        const freshRail = doc.querySelector('#material-rail .rail-list');
        const railList = rail ? rail.querySelector('.rail-list') : null;
        if (freshRail && railList) {
            railList.replaceChildren(...freshRail.childNodes);
            railButtons = [...railList.querySelectorAll('[data-rail-category]')];
        }
        const freshDialogs = doc.querySelector('[data-material-dialogs]');
        if (freshDialogs && dialogsHost) dialogsHost.replaceChildren(...freshDialogs.childNodes);
        const freshUnits = doc.getElementById('material-unit-options');
        if (freshUnits && unitList) unitList.replaceChildren(...freshUnits.childNodes);
        renderUnitQuick();
        const freshTotal = doc.querySelector('[data-material-total]');
        if (freshTotal && totalLabel) totalLabel.textContent = freshTotal.textContent;
        if (highlightId != null) {
            const row = tbody ? tbody.querySelector(`[data-material-id="${highlightId}"]`) : null;
            if (row) {
                row.classList.add('is-new');
                if (scroll) row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        }
        // Re-apply the active category; do not touch the entry form itself.
        selectCategory(selectedCategory, { syncEntry: false });
        updateCategoryStat();
        refreshCodeTools();
    };

    const postMaterial = async body => {
        body.set('async', '1');
        const response = await fetch(window.location.href, {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload || payload.ok !== true || !payload.material) {
            throw new Error(response.ok ? '保存失败，请重试。' : await extractError(response));
        }
        return payload.material;
    };

    const initEntry = () => {
        if (!entryForm || typeof window.fetch !== 'function') return;
        const nameInput = entryForm.querySelector('[data-entry-name]');
        const unitInput = entryForm.querySelector('[data-entry-unit]');
        const submit = entryForm.querySelector('.entry-submit');

        const saved = readSaved();
        if (saved && typeof saved === 'object') {
            if (followToggle && typeof saved.follow === 'boolean') followToggle.checked = saved.follow;
            if (entryCategory && saved.category && [...entryCategory.options].some(option => option.value === saved.category)) {
                entryCategory.value = saved.category;
            }
            if (unitInput && typeof saved.unit === 'string' && saved.unit !== '') {
                unitInput.value = saved.unit;
            }
        }

        if (entryCategory) {
            entryCategory.addEventListener('change', () => {
                codeDirty = false;
                refreshCodeTools();
                updateCategoryStat();
                persistEntry();
                // Changing the entry category is also a deliberate list filter:
                // keep the rail and the visible list aligned with what is typed.
                selectCategory(entryCategory.value, { syncEntry: false });
            });
        }
        if (followToggle) {
            followToggle.addEventListener('change', () => {
                persistEntry();
                if (followToggle.checked && entryCategory && entryCategory.value !== selectedCategory) {
                    entryCategory.value = selectedCategory;
                    codeDirty = false;
                    refreshCodeTools();
                    updateCategoryStat();
                    persistEntry();
                }
            });
        }
        if (codeInput) {
            codeInput.addEventListener('input', () => {
                codeDirty = true;
                refreshCodeTools();
            });
        }
        if (codeNextButton) {
            codeNextButton.addEventListener('click', () => {
                const suggestion = nextCodeFor(entryCategory ? entryCategory.value : '');
                if (suggestion !== null && codeInput) {
                    codeInput.value = suggestion;
                    codeDirty = true;
                    refreshCodeTools();
                    if (nameInput) nameInput.focus();
                }
            });
        }
        if (unitInput) {
            unitInput.addEventListener('input', () => {
                persistEntry();
                syncUnitQuick();
            });
            unitInput.addEventListener('change', syncUnitQuick);
        }
        // Returning to the page keeps the last batch context visible: the list
        // opens on the category that was last being entered.
        if (entryCategory && entryCategory.value !== '') {
            selectCategory(entryCategory.value, { syncEntry: false });
        }
        // Single-line textareas keep the tab order short: Enter saves, while
        // Shift+Enter still inserts a line break for longer specifications.
        for (const field of entryForm.querySelectorAll('[data-entry-single-line]')) {
            field.addEventListener('keydown', event => {
                if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                    event.preventDefault();
                    entryForm.requestSubmit();
                }
            });
        }

        entryForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy) return;
            if (!entryForm.reportValidity()) return;
            busy = true;
            if (submit) { submit.disabled = true; submit.classList.add('is-busy'); }
            try {
                const material = await postMaterial(new FormData(entryForm));
                savedCount++;
                if (entryCounter) entryCounter.textContent = String(savedCount);
                const codeText = String(material.material_code ?? '');
                showStatus(entryStatus, `已保存：${codeText !== '' ? codeText + ' · ' : ''}${material.material_name}，继续录入下一条`, false);
                addChip(material);
                // Keep category + unit (the batch context), clear the rest.
                if (codeInput) codeInput.value = '';
                if (nameInput) nameInput.value = '';
                const specs = entryForm.querySelector('textarea[name="specifications"]');
                const description = entryForm.querySelector('textarea[name="material_description_detailed"]');
                if (specs) specs.value = '';
                if (description) description.value = '';
                codeDirty = false;
                persistEntry();
                // Refresh first, then suggest the next code against the new
                // list; refocus immediately so typing is never blocked.
                // Phones keep the entry panel and the soft keyboard in place for
                // continuous entry; the status line confirms the new row.
                refreshCatalogue(material.material_id, { scroll: !isPhoneLayout() })
                    .catch(() => {})
                    .finally(() => refreshCodeTools());
                if (nameInput) nameInput.focus();
            } catch (error) {
                showStatus(entryStatus, error instanceof Error && error.message ? error.message : '网络异常，保存失败，请重试。', true);
            } finally {
                busy = false;
                if (submit) { submit.disabled = false; submit.classList.remove('is-busy'); }
            }
        });

        updateCategoryStat();
        refreshCodeTools();
    };

    const initEditDialogs = () => {
        if (typeof window.fetch !== 'function') return;
        // Delegated so dialogs replaced by refreshCatalogue keep working.
        document.addEventListener('submit', async event => {
            const form = event.target.closest('form[data-material-edit]');
            if (!form) return;
            event.preventDefault();
            if (form.dataset.busy === '1') return;
            if (!form.reportValidity()) return;
            form.dataset.busy = '1';
            const submit = form.querySelector('button[type="submit"]');
            const status = form.querySelector('[data-edit-status]');
            if (submit) submit.disabled = true;
            try {
                const material = await postMaterial(new FormData(form));
                const dialog = form.closest('dialog');
                if (dialog) dialog.close();
                // The status line lives inside the collapsible entry panel;
                // reveal it so the confirmation is never silently hidden.
                const disclosure = document.getElementById('material-entry');
                if (disclosure && disclosure.tagName === 'DETAILS' && !disclosure.open) disclosure.open = true;
                showStatus(entryStatus, `已更新：${material.material_name}`, false);
                refreshCatalogue(material.material_id).catch(() => {});
            } catch (error) {
                showStatus(status, error instanceof Error && error.message ? error.message : '网络异常，保存失败，请重试。', true);
            } finally {
                form.dataset.busy = '';
                if (submit) submit.disabled = false;
            }
        });
        document.addEventListener('keydown', event => {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                const dialog = document.querySelector('dialog.edit-dialog[open]');
                const form = dialog ? dialog.querySelector('form[data-material-edit]') : null;
                if (form) {
                    event.preventDefault();
                    form.requestSubmit();
                }
            }
        });
    };

    // ---- wiring -----------------------------------------------------------
    if (!entryForm && categorySelect) {
        // Without the entry panel the "未分类" option only exists for the rail;
        // keep it out of the export select so no invalid category id is posted.
        const uncategorised = categorySelect.querySelector('option[value="0"]');
        if (uncategorised) uncategorised.remove();
    }
    if (search) search.addEventListener('input', applyFilter);
    if (categorySelect) {
        categorySelect.addEventListener('change', () => selectCategory(categorySelect.value));
    }
    if (rail) {
        rail.addEventListener('click', event => {
            const button = event.target.closest('[data-rail-category]');
            if (button) selectCategory(button.dataset.railCategory);
        });
    }
    if (unitQuick) {
        unitQuick.addEventListener('click', event => {
            const chip = event.target.closest('[data-unit-value]');
            const unitInput = unitInputOf();
            if (!chip || !unitInput) return;
            unitInput.value = chip.dataset.unitValue ?? '';
            unitInput.dispatchEvent(new Event('input', { bubbles: true }));
            persistEntry();
            syncUnitQuick();
        });
    }
    // On phones the whole material card is a comfortable edit target; taps on
    // the delete form, the edit button or any field keep their own behaviour.
    const materialsTable = document.getElementById('materials-table');
    if (materialsTable) {
        materialsTable.addEventListener('click', event => {
            if (!isPhoneLayout()) return;
            if (event.target.closest('button, a, input, select, textarea, form, summary, label')) return;
            if (!event.target.closest('.material-cell-main, .material-cell-status')) return;
            const row = event.target.closest('.material-row');
            if (!row || row.hidden) return;
            row.querySelector('[data-dialog-open]')?.click();
        });
    }
    // ---- collapsible entry panel ------------------------------------------
    // The catalogue owns the first screen; the full entry form lives in a
    // <details> that starts closed. The user's choice is remembered per project
    // (client-side only, same mechanism as the batch context). Without JS the
    // details element still toggles natively and the form posts normally.
    const entryDisclosure = document.getElementById('material-entry');
    const categoryDisclosure = document.getElementById('material-categories');
    const disclosureKey = storageKey + ':open';
    // The fixed bottom action bar is for browsing only: while the entry panel
    // or the category manager is open it would cover their own controls.
    const syncMobileBar = () => {
        if (!mobileBar) return;
        const entryOpen = Boolean(entryDisclosure && entryDisclosure.tagName === 'DETAILS' && entryDisclosure.open);
        const categoriesOpen = Boolean(categoryDisclosure && categoryDisclosure.open);
        mobileBar.hidden = entryOpen || categoriesOpen;
    };
    const openEntry = ({ focus = true } = {}) => {
        if (!entryDisclosure) return;
        entryDisclosure.open = true;
        syncMobileBar();
        try { window.localStorage.setItem(disclosureKey, '1'); } catch { /* private mode */ }
        if (focus) {
            const nameInput = entryForm ? entryForm.querySelector('[data-entry-name]') : null;
            if (nameInput) nameInput.focus();
        }
    };
    if (entryDisclosure && entryDisclosure.tagName === 'DETAILS') {
        let savedOpen = null;
        try { savedOpen = window.localStorage.getItem(disclosureKey); } catch { /* private mode */ }
        if (savedOpen === '1') entryDisclosure.open = true;
        entryDisclosure.addEventListener('toggle', () => {
            try { window.localStorage.setItem(disclosureKey, entryDisclosure.open ? '1' : '0'); } catch { /* private mode */ }
            syncMobileBar();
            if (entryDisclosure.open) {
                refreshCodeTools();
                updateCategoryStat();
            }
        });
    }
    if (categoryDisclosure) categoryDisclosure.addEventListener('toggle', syncMobileBar);
    syncMobileBar();
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-entry-focus]');
        if (!trigger || !entryForm) return;
        openEntry({ focus: false });
        if (entryDisclosure) entryDisclosure.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const nameInput = entryForm.querySelector('[data-entry-name]');
        if (nameInput) nameInput.focus();
    });

    initEntry();
    initEditDialogs();
    applyFilter();
    renderUnitQuick();
})();

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

// 组价详情的“添加资源行”：类型决定能填哪个目标字段（加工/机械/人工走资源目录，
// 辅材走辅材目录）。没选类型时两个都藏起来，切换类型会清空已选值，并且只保留
// 同类型的目录项——否则一个目录项选错类型就是 422，整页变成错误页。
function initResourceForms() {
    const forms = document.querySelectorAll('form[data-resource-form]');
    if (!forms.length) return;

    const sync = form => {
        const typeField = form.querySelector('[data-resource-type]');
        if (!typeField) return;
        const type = typeField.value;

        form.querySelectorAll('[data-target]').forEach(box => {
            const isAuxBox = box.dataset.target === 'aux';
            const active = type !== '' && (type === 'auxiliary' ? isAuxBox : !isAuxBox);
            box.hidden = !active;
            box.querySelectorAll('select, input').forEach(field => {
                field.disabled = !active;
                if (!active) field.value = '';
            });
        });

        const catalog = form.querySelector('select[name="catalog_id"]');
        if (!catalog) return;
        const isCatalogType = type !== '' && type !== 'auxiliary';
        catalog.querySelectorAll('option[data-catalog-type]').forEach(option => {
            const match = isCatalogType && option.dataset.catalogType === type;
            option.hidden = !match;
            option.disabled = !match;
        });
        if (!isCatalogType) catalog.value = '';
    };

    forms.forEach(form => {
        form.querySelector('[data-resource-type]')?.addEventListener('change', () => sync(form));
        sync(form);
    });
}

initResourceForms();

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
    // Optional combinable dropdown filters: each select declares which row
    // data-attribute it matches (data-list-filter="status" → data-status).
    // Empty value = no constraint. Pure client-side; the server-rendered list
    // and export stay untouched.
    const filters = panel ? [...panel.querySelectorAll('[data-list-filter]')] : [];
    const clearButton = panel?.querySelector('[data-list-filter-clear]');
    const filter = () => {
        const query = search.value.trim().toLocaleLowerCase();
        const active = filters
            .map(select => [select.dataset.listFilter, select.value])
            .filter(([, value]) => value !== '');
        let visibleCount = 0;
        for (const item of items) {
            const visible = (item.dataset.search ?? '').includes(query)
                && active.every(([key, value]) => (item.dataset[key] ?? '') === value);
            item.hidden = !visible;
            if (visible) visibleCount++;
        }
        if (emptyState) emptyState.hidden = visibleCount !== 0;
        if (count) count.textContent = `${visibleCount}${count.dataset.unit ?? ' 条'}`;
        if (clearButton) clearButton.hidden = query === '' && active.length === 0;
    };

    search.addEventListener('input', filter);
    for (const select of filters) select.addEventListener('change', filter);
    if (clearButton) {
        clearButton.addEventListener('click', () => {
            search.value = '';
            for (const select of filters) select.value = '';
            filter();
            search.focus();
        });
    }
}

// Material estimates page: fast entry (searchable material picker, remembered
// space selection) and a grouped/filterable record ledger. Everything here is
// progressive enhancement: without JavaScript the original selects still work.
(() => {
    // Guard against the bundle being evaluated twice (e.g. injected preview
    // harnesses). A DOM dataset flag is shared across execution worlds, so the
    // listeners and submit handlers are registered exactly once per page load.
    if (document.documentElement.dataset.estimateUiInit === '1') return;
    document.documentElement.dataset.estimateUiInit = '1';

    const normalize = text => String(text).toLowerCase().replace(/\s+/g, ' ').trim();

    function initCombos() {
        // Persistent registry shared across re-init calls (ledger refresh), so the
        // document click handler is attached once and never leaks listeners.
        window.comboRegistry = window.comboRegistry || [];
        const combos = window.comboRegistry;
        let comboIndex = window.comboIndex || 0;
        window.comboResetFns = window.comboResetFns || [];
        for (const combo of document.querySelectorAll('[data-combo]')) {
            if (combo.dataset.comboInit === '1') continue;
            combo.dataset.comboInit = '1';
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
            // Optional pickers (e.g. the sample quotation link) keep their
            // empty choice valid and skip the required-field submit guard.
            const isRequired = select.required;
            select.required = false;
            select.tabIndex = -1;
            select.setAttribute('aria-hidden', 'true');

            const chosenOption = () => options.find(option => option.value === select.value) ?? null;
            const syncInput = () => {
                const chosen = chosenOption();
                if (chosen) input.value = chosen.textContent.replace(/\s+/g, ' ').trim();
            };
            const unitOf = option => {
                const match = /\(([^()]*)\)\s*$/.exec(option ? option.textContent : '');
                return match ? match[1].trim() : '';
            };
            const syncUnit = () => {
                const addForm = combo.closest('form[data-estimate-add]');
                const unit = addForm ? addForm.querySelector('[data-quantity-unit]') : null;
                if (unit) unit.textContent = unitOf(chosenOption()) || '—';
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
                    empty.textContent = combo.dataset.comboEmpty || '没有匹配的材料';
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
                syncUnit();
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
            window.comboIndex = comboIndex;
            if (combo.closest('form[data-estimate-add]')) {
                window.comboResetFns.push(() => {
                    select.value = '';
                    input.value = '';
                    input.setCustomValidity('');
                    close();
                    syncUnit();
                });
            }

            if (form && isRequired) {
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
                            syncUnit();
                            return;
                        }
                    }
                    event.preventDefault();
                    const noun = combo.dataset.comboNoun || '材料';
                    input.setCustomValidity(needle !== '' ? '请从搜索结果中选择' + noun + '。' : '请选择' + noun + '。');
                    input.reportValidity();
                    input.focus();
                    open(needle);
                });
            }

            syncInput();
            syncUnit();
        }
        if (!window.comboClickBound) {
            window.comboClickBound = true;
            document.addEventListener('click', event => {
                for (const entry of window.comboRegistry ?? []) {
                    if (!entry.combo.contains(event.target)) entry.close();
                }
            });
        }
    }

    function initCascades() {
        for (const form of document.querySelectorAll('form[data-estimate-form], form[data-cascade-form]')) {
            if (form.dataset.cascadeInit === '1') continue;
            form.dataset.cascadeInit = '1';
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

    // Continuous entry: save asynchronously, keep the remembered location,
    // clear material/quantity/remarks and refocus the material search so the
    // next record can be typed immediately. Without fetch the form still
    // submits normally and the page reloads.
    function initQuickEntry() {
        const form = document.querySelector('form[data-estimate-add]');
        if (!form || typeof window.fetch !== 'function') return;

        const counter = document.querySelector('[data-entry-counter] strong');
        const status = form.querySelector('[data-entry-status]');
        const recent = document.querySelector('[data-entry-recent]');
        const chips = document.querySelector('[data-entry-recent-chips]');
        const quantity = form.querySelector('[data-quantity-input]');
        const remarks = form.querySelector('input[name="remarks"]');
        const submit = form.querySelector('.entry-submit');
        let busy = false;
        let savedCount = 0;
        let statusTimer = 0;

        const showStatus = (message, isError) => {
            if (!status) return;
            window.clearTimeout(statusTimer);
            status.textContent = message;
            status.classList.toggle('is-error', isError);
            status.classList.toggle('is-success', !isError);
            status.hidden = false;
            if (!isError) statusTimer = window.setTimeout(() => { status.hidden = true; }, 4000);
        };

        const addChip = record => {
            if (!recent || !chips) return;
            recent.hidden = false;
            const chip = document.createElement('span');
            chip.className = 'entry-chip';
            const name = document.createElement('strong');
            name.textContent = String(record.material_name ?? '');
            const qty = document.createElement('span');
            qty.textContent = `${String(record.estimated_quantity ?? '').replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '')} ${record.unit ?? ''}`;
            const place = document.createElement('span');
            place.className = 'entry-chip-place';
            place.textContent = [record.building_name || record.building_number, record.floor_number, record.room_number]
                .filter(part => part !== null && part !== undefined && part !== '')
                .join(' · ');
            chip.append(name, qty, place);
            chips.prepend(chip);
            while (chips.children.length > 10) chips.lastElementChild.remove();
        };

        const normalizeQuantity = value => {
            const text = String(value ?? '');
            return /^-?\d+(?:\.\d+)?$/.test(text) ? (text.replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '') || '0') : text;
        };

        const refreshLedger = async record => {
            const ledger = document.querySelector('[data-estimate-ledger]');
            if (!ledger) return;
            const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) return;
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const fresh = doc.querySelector('[data-estimate-ledger]');
            if (!fresh) return;
            const openIds = new Set([...ledger.querySelectorAll('details[open]')].map(item => item.dataset.estimateId ?? '').filter(id => id !== ''));
            const expandedRecord = ledger.querySelector('details[data-ledger-record][open]');
            const expandedId = expandedRecord ? expandedRecord.dataset.estimateId : null;
            ledger.replaceChildren(...fresh.childNodes);
            // Re-enhance the replaced edit forms (searchable material picker and
            // cascading selects); the entry form is untouched by the swap.
            initCombos();
            initCascades();
            for (const item of ledger.querySelectorAll('details[data-ledger-record]')) {
                const id = item.dataset.estimateId ?? '';
                if (openIds.has(id) || id === expandedId || id === String(record.estimate_detail_id)) item.open = true;
            }
            const freshCount = doc.querySelector('[data-estimate-count]');
            const count = document.querySelector('[data-estimate-count]');
            if (freshCount && count) count.textContent = freshCount.textContent;
            const freshLimit = doc.querySelector('[data-estimate-limit-note]');
            const limit = document.querySelector('[data-estimate-limit-note]');
            if (freshLimit && limit) limit.textContent = freshLimit.textContent;
            const emptyState = document.querySelector('[data-estimate-empty-state]');
            if (emptyState) emptyState.remove();
            const added = ledger.querySelector(`[data-estimate-id="${record.estimate_detail_id}"]`);
            if (added) added.classList.add('is-new');
        };

        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (busy) return;
            const material = form.querySelector('select[name="material_id"]');
            if (!material || material.value === '') {
                form.reportValidity();
                return;
            }
            if (quantity && !quantity.checkValidity()) {
                quantity.reportValidity();
                return;
            }
            busy = true;
            if (submit) { submit.disabled = true; submit.classList.add('is-busy'); }
            const body = new FormData(form);
            body.set('async', '1');
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const payload = await response.json().catch(() => null);
                if (!response.ok || !payload || payload.ok !== true || !payload.estimate) {
                    throw new Error(payload && payload.message ? payload.message : '保存失败，请重试。');
                }
                const record = payload.estimate;
                savedCount++;
                if (counter) counter.textContent = String(savedCount);
                const materialName = String(record.material_name ?? '');
                showStatus(`已保存：${materialName} × ${normalizeQuantity(record.estimated_quantity)} ${record.unit ?? ''}，继续录入下一条`, false);
                addChip(record);
                for (const reset of window.comboResetFns ?? []) reset();
                if (quantity) quantity.value = '';
                if (remarks) remarks.value = '';
                refreshLedger(record).catch(() => {});
                const materialInput = form.querySelector('[data-combo-input]');
                if (materialInput) { materialInput.focus(); materialInput.select(); }
            } catch (error) {
                showStatus(error instanceof Error && error.message ? error.message : '网络异常，保存失败，请重试。', true);
                if (quantity) { quantity.focus(); quantity.select(); }
            } finally {
                busy = false;
                if (submit) { submit.disabled = false; submit.classList.remove('is-busy'); }
            }
        });
    }

    // Exposed so other pages (e.g. the sample dialogs) can enhance their own
    // [data-combo] pickers with the exact same behaviour; the registry and the
    // per-combo init flag keep repeated calls idempotent.
    window.projectOInitCombos = initCombos;
    initCombos();
    initCascades();
    initLedgerFilter();
    initQuickEntry();
})();

// V2.1 app shell: sidebar collapse (desktop rail) + narrow-screen drawer.
// Pure layout chrome — no business form, fetch or filter logic is touched.
// The collapsed choice is remembered per browser; without JS the sidebar
// simply stays expanded and every link keeps working.
(() => {
    const sidebar = document.querySelector('[data-sidebar]');
    if (!sidebar) return;
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const collapseButton = document.querySelector('[data-sidebar-collapse]');
    const scrim = document.querySelector('[data-sidebar-scrim]');
    const storageKey = 'project-o:sidebar-collapsed';
    const readCollapsed = () => {
        try { return window.localStorage.getItem(storageKey) === '1'; } catch { return false; }
    };
    const writeCollapsed = value => {
        try { window.localStorage.setItem(storageKey, value ? '1' : '0'); } catch { /* private mode */ }
    };
    const applyCollapsed = value => {
        document.body.classList.toggle('sidebar-collapsed', value);
        if (collapseButton) {
            collapseButton.setAttribute('aria-label', value ? '展开侧边栏' : '收起侧边栏');
            const text = collapseButton.querySelector('.sidebar-text');
            if (text) text.textContent = value ? '展开侧栏' : '收起侧栏';
        }
    };
    applyCollapsed(readCollapsed());

    const setDrawer = open => {
        document.body.classList.toggle('sidebar-open', open);
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (scrim) scrim.hidden = !open;
    };
    if (toggle) {
        toggle.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 760px)').matches) {
                setDrawer(!document.body.classList.contains('sidebar-open'));
            } else {
                const next = !document.body.classList.contains('sidebar-collapsed');
                applyCollapsed(next);
                writeCollapsed(next);
            }
        });
    }
    if (collapseButton) {
        collapseButton.addEventListener('click', () => {
            const next = !document.body.classList.contains('sidebar-collapsed');
            applyCollapsed(next);
            writeCollapsed(next);
        });
    }
    if (scrim) scrim.addEventListener('click', () => setDrawer(false));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) setDrawer(false);
    });
    // Navigating from the drawer closes it; desktop keeps the rail state.
    sidebar.addEventListener('click', event => {
        if (event.target.closest('a') && window.matchMedia('(max-width: 760px)').matches) setDrawer(false);
    });
    window.addEventListener('resize', () => {
        if (!window.matchMedia('(max-width: 760px)').matches) setDrawer(false);
    });
})();

// V2.1 project home: client-side name search + status tabs over the portfolio
// cards (high-fidelity prototype interaction). Pure display filtering — the
// server-rendered list, the enter/select POST forms and the archived view stay
// untouched. Without JS every card and form keeps working; only the live
// filter is absent.
(() => {
    const grid = document.querySelector('[data-project-grid]');
    if (!grid) return;
    const search = document.querySelector('[data-project-search]');
    const tabs = [...document.querySelectorAll('[data-project-filter]')];
    const count = document.querySelector('[data-project-count]');
    const empty = document.querySelector('[data-project-empty]');
    const cards = [...grid.querySelectorAll('[data-project-card]')];
    let activeStatus = '';

    const apply = () => {
        const query = search ? search.value.trim().toLocaleLowerCase() : '';
        let visible = 0;
        for (const card of cards) {
            const matches = (card.dataset.search ?? '').includes(query)
                && (activeStatus === '' || (card.dataset.status ?? '') === activeStatus);
            card.hidden = !matches;
            if (matches) visible++;
        }
        if (count) count.textContent = `${visible}${count.dataset.unit ?? ' 个项目'}`;
        if (empty) empty.hidden = visible !== 0;
    };

    if (search) search.addEventListener('input', apply);
    for (const tab of tabs) {
        tab.addEventListener('click', () => {
            activeStatus = tab.dataset.projectFilter ?? '';
            for (const other of tabs) {
                const isActive = other === tab;
                other.classList.toggle('is-active', isActive);
                other.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            }
            apply();
        });
    }

    // Whole-card click enters the project: submit the card's hidden
    // project.select form (same POST contract as the explicit button). Inner
    // forms/buttons (delete/archive) keep their own behaviour. Without JS the
    // card is inert but every form on the page still works.
    const enterFromCard = card => {
        const form = card.querySelector('[data-project-enter-form]');
        if (form) form.requestSubmit();
    };
    grid.addEventListener('click', event => {
        if (event.target.closest('form, button, a, input, select, summary')) return;
        const card = event.target.closest('[data-project-enter]');
        if (card) enterFromCard(card);
    });
    grid.addEventListener('keydown', event => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        const card = event.target.closest('[data-project-enter]');
        if (card && event.target === card) {
            event.preventDefault();
            enterFromCard(card);
        }
    });
})();

// Top-bar project switcher: close the dropdown on outside click / Escape.
// The menu itself is a native <details>, so it also works without JS.
(() => {
    const switcher = document.querySelector('.project-switcher');
    if (!switcher) return;
    document.addEventListener('click', event => {
        if (switcher.open && !switcher.contains(event.target)) switcher.open = false;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && switcher.open) switcher.open = false;
    });
})();

// Project spaces deck (2026-11-23): a floor-by-floor stage instead of the old
// stacked tree. Everything here is client-side presentation only — the forms,
// action names, CSRF tokens and server-side permission gates are untouched, and
// without this block every floor stays expanded and every form still submits.
(() => {
    const deck = document.querySelector('[data-space-deck]');
    if (!deck) return;

    const panels = [...deck.querySelectorAll('[data-space-panel]')];
    const chips = [...deck.querySelectorAll('[data-space-floor]')];
    const search = deck.querySelector('[data-space-search]');
    const countNode = deck.querySelector('[data-space-count]');
    const hudFloor = document.querySelector('[data-space-hud-floor]');
    const densityButtons = [...deck.querySelectorAll('[data-space-density]')];
    const railToggles = [...deck.querySelectorAll('[data-space-rail-toggle]')];
    const quickForms = [...deck.querySelectorAll('[data-space-quick]')];
    const projectKey = deck.dataset.project || '0';

    if (!panels.length) return;

    const panelFor = floorId => panels.find(panel => panel.dataset.floor === floorId);

    const updateCount = (matchInfo = null) => {
        if (!countNode) return;
        const total = deck.querySelectorAll('[data-space-tile]').length;
        if (matchInfo) {
            countNode.innerHTML = `匹配 <b>${matchInfo.tiles}</b> 间 · 命中 <b>${matchInfo.panels}</b> 层`;
            return;
        }
        const panel = panelFor(deck.dataset.activeFloor);
        const local = panel ? panel.querySelectorAll('[data-space-tile]').length : 0;
        countNode.innerHTML = `本层 <b>${local}</b> 间 · 全项目 <b>${total}</b> 间`;
    };

    const focusQuick = (panel, smooth = true) => {
        const number = panel ? panel.querySelector('[data-space-quick-number]') : null;
        if (!number) return;
        number.focus({ preventScroll: true });
        if (smooth) {
            (number.closest('form') || number).scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    };

    const activate = (floorId, options = {}) => {
        const panel = panelFor(floorId);
        if (!panel) return;
        panels.forEach(other => other.classList.toggle('is-current', other === panel));
        chips.forEach(chip => {
            const current = chip.dataset.spaceFloor === floorId;
            chip.classList.toggle('is-current', current);
            if (current) chip.setAttribute('aria-current', 'page');
            else chip.removeAttribute('aria-current');
        });
        deck.dataset.activeFloor = floorId;

        const buildingId = panel.dataset.building;
        const building = deck.querySelector(`[data-space-building="${buildingId}"]`);
        if (building) building.open = true;

        if (hudFloor) {
            hudFloor.textContent = (panel.dataset.label || '').trim().split(' ')[0] || '—';
        }
        updateCount();
        if (options.focusQuick) focusQuick(panel, false);
    };

    chips.forEach(chip => {
        chip.addEventListener('click', event => {
            event.preventDefault();
            if (search && search.value !== '') {
                search.value = '';
                applySearch();
            }
            const floorId = chip.dataset.spaceFloor;
            activate(floorId);
            const url = new URL(window.location.href);
            url.searchParams.set('floor', floorId);
            url.hash = `floor-${floorId}`;
            window.history.replaceState(null, '', url);
        });
    });

    const applySearch = () => {
        const query = search ? search.value.trim().toLocaleLowerCase() : '';
        const searching = query !== '';
        deck.classList.toggle('is-searching', searching);
        let matchedTiles = 0;
        let matchedPanels = 0;

        for (const panel of panels) {
            const tiles = [...panel.querySelectorAll('[data-space-tile]')];
            let panelMatches = !searching || (panel.dataset.search || '').includes(query);
            let visibleTiles = 0;

            for (const tile of tiles) {
                const matches = !searching || panelMatches || (tile.dataset.search || '').includes(query);
                tile.hidden = !matches;
                if (matches) visibleTiles++;
            }

            if (searching && !panelMatches) {
                panelMatches = visibleTiles > 0;
            }
            if (!panelMatches) {
                panel.classList.remove('is-match');
                continue;
            }
            if (searching) {
                panel.classList.add('is-match');
                matchedPanels++;
                matchedTiles += visibleTiles;
            } else {
                panel.classList.remove('is-match');
            }
        }

        chips.forEach(chip => {
            const panel = panelFor(chip.dataset.spaceFloor);
            chip.classList.toggle('is-filtered', searching && (!panel || !panel.classList.contains('is-match')));
        });

        updateCount(searching ? { tiles: matchedTiles, panels: matchedPanels } : null);
    };

    if (search) {
        search.addEventListener('input', applySearch);
        search.addEventListener('keydown', event => {
            if (event.key === 'Escape' && search.value !== '') {
                event.preventDefault();
                search.value = '';
                applySearch();
            }
        });
    }

    const densityKey = 'projectO.spaceDensity';
    const applyDensity = value => {
        const density = ['dense', 'normal', 'roomy'].includes(value) ? value : 'normal';
        deck.classList.toggle('is-density-dense', density === 'dense');
        deck.classList.toggle('is-density-roomy', density === 'roomy');
        for (const button of densityButtons) {
            button.setAttribute('aria-pressed', button.dataset.spaceDensity === density ? 'true' : 'false');
        }
        try {
            localStorage.setItem(densityKey, density);
        } catch (error) {
            /* storage may be unavailable in private modes; the layout still works */
        }
    };
    for (const button of densityButtons) {
        button.addEventListener('click', () => applyDensity(button.dataset.spaceDensity));
    }

    const railKey = 'projectO.spaceRail';
    const setRail = hidden => {
        deck.classList.toggle('is-rail-hidden', hidden);
        for (const button of railToggles) {
            button.setAttribute('aria-expanded', hidden ? 'false' : 'true');
        }
        try {
            localStorage.setItem(railKey, hidden ? 'hidden' : 'shown');
        } catch (error) {
            /* ignore */
        }
    };
    for (const button of railToggles) {
        button.addEventListener('click', () => setRail(!deck.classList.contains('is-rail-hidden')));
    }

    try {
        applyDensity(localStorage.getItem(densityKey) || 'normal');
        if (localStorage.getItem(railKey) === 'hidden') setRail(true);
    } catch (error) {
        applyDensity('normal');
    }

    // Last room type is remembered per project so repeat entry on the same
    // floor does not retype it; users can always overwrite or clear the field.
    const typeKey = `projectO.spaceRoomType.${projectKey}`;
    try {
        const lastType = localStorage.getItem(typeKey);
        if (lastType) {
            for (const form of quickForms) {
                const input = form.querySelector('[data-space-quick-type]');
                if (input && input.value === '') input.value = lastType;
            }
        }
    } catch (error) {
        /* ignore */
    }
    for (const form of quickForms) {
        form.addEventListener('submit', () => {
            const input = form.querySelector('[data-space-quick-type]');
            if (!input || input.value.trim() === '') return;
            try {
                localStorage.setItem(typeKey, input.value.trim());
            } catch (error) {
                /* ignore */
            }
        });
    }

    // Opening a room dialog should land in the room-number field instead of the
    // close button, so the first keystroke goes into the new room.
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-dialog-open^="space-room-create-"]');
        if (!trigger) return;
        const dialog = document.getElementById(trigger.dataset.dialogOpen);
        if (!dialog) return;
        event.preventDefault();
        window.setTimeout(() => {
            const input = dialog.querySelector('input[name="number"]');
            if (input) input.focus();
        }, 0);
    });

    // Keyboard: "/" jumps to search, "N" jumps to quick entry, arrow keys move
    // between tiles of the active floor, Enter opens the tile's first action.
    document.addEventListener('keydown', event => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const inField = target.closest('input, textarea, select, [contenteditable="true"]');
        if (event.key === '/' && !inField && !event.ctrlKey && !event.metaKey && !event.altKey) {
            if (!search) return;
            event.preventDefault();
            search.focus();
            return;
        }
        if ((event.key === 'n' || event.key === 'N') && !inField && !event.ctrlKey && !event.metaKey && !event.altKey) {
            const panel = panelFor(deck.dataset.activeFloor);
            if (!panel || !panel.querySelector('[data-space-quick-number]')) return;
            event.preventDefault();
            activate(deck.dataset.activeFloor, { focusQuick: true });
        }
    });

    deck.addEventListener('keydown', event => {
        const tile = event.target.closest('[data-space-tile]');
        if (!tile || event.target !== tile || !deck.contains(tile)) return;
        const grid = tile.closest('[data-space-grid]');
        if (!grid) return;
        const visible = [...grid.querySelectorAll('[data-space-tile]')].filter(item => !item.hidden);
        const index = visible.indexOf(tile);
        if (index < 0) return;
        const columns = window.getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length || 1;
        let next = -1;
        if (event.key === 'ArrowRight') next = index + 1;
        else if (event.key === 'ArrowLeft') next = index - 1;
        else if (event.key === 'ArrowDown') next = index + columns;
        else if (event.key === 'ArrowUp') next = index - columns;
        else if (event.key === 'Enter' && event.target === tile) {
            const action = tile.querySelector('[data-dialog-open]');
            if (action) {
                event.preventDefault();
                action.click();
            }
            return;
        }
        if (next < 0 || next >= visible.length || next === index) return;
        event.preventDefault();
        visible[next].focus();
    });

    deck.dataset.ready = '1';
    updateCount();

    // After "保存并继续" the server sends ?floor=…&focus=room so the next room
    // number can be typed immediately without reopening a dialog or rescrolling.
    if (deck.dataset.focus === 'room') {
        const panel = panelFor(deck.dataset.activeFloor);
        if (panel) {
            window.setTimeout(() => focusQuick(panel), 0);
        }
    }
})();
