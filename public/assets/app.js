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
        const linkField = document.querySelector('[data-share-link]');
        if (linkField) copyShareLink(copyTrigger, linkField);
    }
});

// Copy the public share link. The clipboard API needs a secure context, so
// fall back to selecting the readonly field (mobile long-press copy works too).
async function copyShareLink(button, field) {
    const original = button.textContent;
    const done = ok => {
        button.textContent = ok ? '已复制' : '请长按或全选复制';
        window.setTimeout(() => { button.textContent = original; }, 2000);
    };
    try {
        await navigator.clipboard.writeText(field.value);
        done(true);
    } catch {
        field.focus();
        field.select();
        let copied = false;
        try { copied = document.execCommand('copy'); } catch { copied = false; }
        done(copied);
    }
}

// Public crew share page: live search over the already-rendered cards.
const shareSearch = document.getElementById('share-search');
if (shareSearch) {
    const shareItems = [...document.querySelectorAll('#share-list .share-card')];
    const shareCount = document.getElementById('share-count');
    const shareNoMatch = document.getElementById('share-no-match');
    const filterShare = () => {
        const query = shareSearch.value.trim().toLocaleLowerCase();
        let visible = 0;
        for (const item of shareItems) {
            const matches = (item.dataset.search ?? '').includes(query);
            item.hidden = !matches;
            if (matches) visible++;
        }
        if (shareCount) shareCount.textContent = `${visible}${shareCount.dataset.unit ?? ' 个班组'}`;
        if (shareNoMatch) shareNoMatch.hidden = visible !== 0;
    };
    shareSearch.addEventListener('input', filterShare);
}

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
