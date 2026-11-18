// Sample tracking page: searchable dialog pickers + coverage preselection.
// Loaded as a deferred module asset AFTER app.js, so the shared combo
// initializer is guaranteed to exist before this runs (inline scripts would
// execute too early). Progressive enhancement: without JavaScript the native
// selects and the plain dialog still work.
(() => {
    if (document.documentElement.dataset.sampleUiInit === '1') return;
    document.documentElement.dataset.sampleUiInit = '1';

    // Enhance every [data-combo] picker on this page with the shared behaviour.
    if (typeof window.projectOInitCombos === 'function') window.projectOInitCombos();

    const dialog = document.getElementById('sample-create');
    if (!dialog) return;
    const form = dialog.querySelector('form');
    const select = dialog.querySelector('[data-sample-material-select]');
    const hint = dialog.querySelector('[data-sample-material-hint]');
    const config = document.getElementById('sample-coverage-config');
    let annotations = {};
    if (config) {
        try { annotations = JSON.parse(config.textContent || '{}'); } catch (e) { annotations = {}; }
    }

    const optionLabel = (sel, value) => {
        if (!sel || value === '') return '';
        const option = [...sel.options].find(item => item.value === value);
        return option ? option.textContent.replace(/\s+/g, ' ').trim() : '';
    };
    // Write both the submitted select value and the visible search input, so
    // the preselection never depends on change-event wiring inside the combo.
    const setComboValue = (sel, value) => {
        if (!sel) return;
        sel.value = value;
        const combo = sel.closest('[data-combo]');
        const input = combo ? combo.querySelector('[data-combo-input]') : null;
        if (input) {
            input.value = optionLabel(sel, value);
            input.setCustomValidity('');
        }
        sel.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const updateHint = () => {
        if (!hint || !select) return;
        const text = annotations[select.value] || '';
        hint.textContent = text === '' ? '' : '该材料当前：' + text + '。被拒后重送或换色重送可直接新增一版，无需修改旧记录。';
        hint.hidden = text === '';
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-dialog-open="sample-create"]');
        if (!trigger) return;
        // Every open starts from the server-rendered defaults (today's sent
        // date, empty fields) so a cancelled entry never leaks into the next
        // sample; then apply the coverage row's material preselection.
        if (form) {
            form.reset();
            for (const combo of form.querySelectorAll('[data-combo]')) {
                const comboSelect = combo.querySelector('select[data-combo-select]');
                const comboInput = combo.querySelector('[data-combo-input]');
                if (comboInput) {
                    comboInput.value = optionLabel(comboSelect, comboSelect ? comboSelect.value : '');
                    comboInput.setCustomValidity('');
                }
            }
        }
        if (trigger.dataset.sampleMaterial) {
            setComboValue(select, trigger.dataset.sampleMaterial);
        } else if (select) {
            setComboValue(select, '');
        }
        updateHint();
    });
    if (select) select.addEventListener('change', updateHint);
})();
