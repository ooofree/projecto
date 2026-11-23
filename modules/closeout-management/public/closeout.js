// Closeout workspace progressive enhancement:
// - click any list row to open it in the side panel (no page jump needed);
// - batch bar tracks selection and rejects empty/field-less submits;
// - BOM select dialog tracks selectable materials and guards empty submits;
// - dialogs rendered with [open] are upgraded to modal when JS is available.
// The server stays authoritative: without JS every form still submits and the
// service rejects empty or conflicting selections.
(() => {
    // Row click = select. Interactive controls keep their own behaviour.
    for (const row of document.querySelectorAll('[data-closeout-row]')) {
        const href = row.dataset.href;
        if (!href) continue;
        row.addEventListener('click', event => {
            if (event.target.closest('a, button, input, select, textarea, label, form, details, summary')) {
                return;
            }
            window.location.href = href;
        });
        row.addEventListener('keydown', event => {
            if (event.key === 'Enter' && event.target === row) {
                window.location.href = href;
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

    for (const dialog of document.querySelectorAll('dialog[data-auto-open]')) {
        if (typeof dialog.showModal === 'function') {
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
