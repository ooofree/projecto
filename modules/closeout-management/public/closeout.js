// Closeout progressive enhancement: select-all, batch guard, selection guard.
// The server remains authoritative: without JS the forms still submit, and the
// service rejects empty or conflicting selections.
(() => {
    const master = document.querySelector('[data-closeout-check-all]');
    const boxes = Array.from(document.querySelectorAll('[data-closeout-check]'));
    if (master && boxes.length > 0) {
        master.addEventListener('change', () => {
            for (const box of boxes) box.checked = master.checked;
        });
    }

    const batch = document.querySelector('[data-closeout-batch]');
    if (batch) {
        batch.addEventListener('submit', event => {
            if (!boxes.some(box => box.checked)) {
                event.preventDefault();
                window.alert('请先勾选需要批量调整的事项。');
            }
        });
    }

    const selectForm = document.querySelector('[data-closeout-select-page]');
    if (selectForm) {
        const items = Array.from(selectForm.querySelectorAll('[data-closeout-select-item]'));
        const selectAll = selectForm.querySelector('[data-closeout-select-all]');
        if (selectAll) {
            selectAll.addEventListener('click', () => {
                for (const item of items) item.checked = true;
            });
        }
        selectForm.addEventListener('submit', event => {
            if (!items.some(item => item.checked)) {
                event.preventDefault();
                window.alert('请先勾选至少一项可纳入的 BOM 材料。');
            }
        });
    }
})();
