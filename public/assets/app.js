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
});
