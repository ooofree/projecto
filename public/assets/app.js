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
    // The data attribute is the single source of truth; the visible anchor is
    // display only. Trim so no stray whitespace ever rides along.
    const url = String(button.dataset.shareUrl || (field ? field.textContent : '')).trim();
    if (!/^https?:\/\/\S+$/.test(url)) {
        setShareFeedback(feedback, '链接无效，请关闭后重新打开分享。', true);
        return;
    }

    let copied = false;
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(url);
            copied = true;
        } catch {
            copied = false;
        }
    }
    if (!copied) copied = legacyCopyShareUrl(url, button.closest('dialog') ?? document.body);

    const original = button.dataset.shareLabel || button.textContent;
    button.dataset.shareLabel = original;
    if (copied) {
        button.textContent = '已复制 ✓';
        setShareFeedback(feedback, '链接已复制，去微信粘贴发送即可。', false);
    } else {
        // Honest fallback: select the visible URL so a long-press / Ctrl+C
        // copies exactly the link. Never claim success we cannot verify.
        if (field) {
            const range = document.createRange();
            range.selectNodeContents(field);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
        }
        button.textContent = original;
        setShareFeedback(feedback, '当前浏览器不允许自动复制，链接已选中：长按或按 Ctrl+C 复制后到微信粘贴。', true);
    }
    window.clearTimeout(button._shareCopyTimer);
    button._shareCopyTimer = window.setTimeout(() => {
        button.textContent = original;
    }, 2200);
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
