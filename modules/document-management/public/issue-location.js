// Issue location picker — approach F: search-first, tree browse, recent chips.
// Progressive enhancement: the native <select name="location"> stays in the DOM
// and remains the submitted value, so the form still works without JavaScript.
(() => {
    'use strict';
    if (document.documentElement.dataset.issueLocationInit === '1') return;
    document.documentElement.dataset.issueLocationInit = '1';

    const KIND_LABEL = { building: '楼栋', floor: '楼层', area: '区域', room: '房间' };
    const RECENT_LIMIT = 5;
    const RESULT_LIMIT = 80;
    const norm = value => String(value == null ? '' : value).toLowerCase().replace(/\s+/g, ' ').trim();
    const el = (tag, cls, text) => {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined && text !== null) node.textContent = String(text);
        return node;
    };

    // Recent picks are per project, stored locally; never authoritative data.
    const recentStore = {
        key: projectId => 'projectO.issueLocation.recent.' + projectId,
        read(projectId) {
            try {
                const raw = window.localStorage.getItem(this.key(projectId));
                const list = raw ? JSON.parse(raw) : [];
                return Array.isArray(list) ? list.filter(v => typeof v === 'string').slice(0, RECENT_LIMIT) : [];
            } catch (error) { return []; }
        },
        write(projectId, list) {
            try { window.localStorage.setItem(this.key(projectId), JSON.stringify(list.slice(0, RECENT_LIMIT))); }
            catch (error) { /* private mode: recent list simply stays in memory */ }
        }
    };

    const openPickers = new Set();
    document.addEventListener('pointerdown', event => {
        for (const picker of openPickers) {
            if (!picker.searchWrap.contains(event.target)) picker.closeResults();
        }
    });

    function readCatalog(root) {
        const script = root.querySelector('[data-location-catalog]');
        if (!script) return null;
        try {
            const data = JSON.parse(script.textContent || '{}');
            return Array.isArray(data.options) ? data : null;
        } catch (error) { return null; }
    }

    function initPicker(root) {
        if (root.dataset.locationInit === '1') return;
        const native = root.querySelector('select[name="location"]');
        const catalog = readCatalog(root);
        if (!native || !catalog || catalog.options.length === 0) return;
        root.dataset.locationInit = '1';

        const projectId = root.dataset.projectId || '0';
        const options = catalog.options;
        const byValue = new Map(options.map(option => [option.value, option]));
        const legacyValue = [...native.options].some(o => o.value === 'legacy') ? 'legacy' : '';
        const legacyLabel = legacyValue
            ? ([...native.options].find(o => o.value === 'legacy').textContent || '').trim()
            : '';

        // The native select keeps ownership of the submitted value.
        native.classList.add('loc-native');
        native.tabIndex = -1;
        native.setAttribute('aria-hidden', 'true');
        native.style.display = 'none';

        const ui = el('div', 'loc-picker');
        const head = el('div', 'loc-head');
        const tabs = el('div', 'loc-tabs');
        const tabSearch = el('button', 'loc-tab', '搜索');
        const tabTree = el('button', 'loc-tab', '按楼层浏览');
        for (const tab of [tabSearch, tabTree]) {
            tab.type = 'button';
            tab.setAttribute('aria-expanded', 'false');
        }
        tabs.append(tabSearch, tabTree);
        const hint = el('p', 'loc-hint', '点“搜索”或“按楼层浏览”选择位置；不选则留空。');
        head.append(tabs, hint);

        const current = el('div', 'loc-current');
        current.setAttribute('aria-live', 'polite');

        let recent = recentStore.read(projectId).filter(value => byValue.has(value));

        const recentBox = el('div', 'loc-recent');
        const recentTitle = el('p', 'loc-recent-title', '最近使用');
        const chips = el('div', 'loc-chips');
        recentBox.append(recentTitle, chips);

        // --- search pane ---
        const searchPane = el('div');
        const searchWrap = el('div', 'loc-search-wrap');
        const input = el('input', 'loc-search');
        input.type = 'search';
        input.autocomplete = 'off';
        input.placeholder = '搜索位置，如 单间4、三层、会议室';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-label', '搜索项目空间');
        const results = el('ul', 'loc-results');
        results.hidden = true;
        results.setAttribute('role', 'listbox');
        const listId = 'loc-results-' + Math.random().toString(36).slice(2, 9);
        results.id = listId;
        input.setAttribute('aria-controls', listId);
        searchWrap.append(input, results);
        searchPane.append(searchWrap);

        // --- tree pane (built lazily on first switch) ---
        const treePane = el('div');
        treePane.hidden = true;
        let treeBuilt = false;

        const noteBox = el('div', 'loc-note');
        const noteLabel = el('label', null, '位置补充说明（可选）');
        const noteInput = el('input');
        noteInput.type = 'text';
        noteInput.name = 'location_note';
        noteInput.maxLength = 255;
        noteInput.placeholder = '如 C-E轴、前室靠门侧、管井内';
        const noteId = 'loc-note-' + Math.random().toString(36).slice(2, 9);
        noteInput.id = noteId;
        noteLabel.setAttribute('for', noteId);
        const noteHelp = el('p', 'loc-note-help', '轴线、前室等没有独立空间记录的部位写在这里，与所选空间一并保存。');
        noteBox.append(noteLabel, noteInput, noteHelp);
        // Move the server-rendered note value into the enhanced control.
        const serverNote = root.querySelector('[data-location-note]');
        if (serverNote) {
            noteInput.value = serverNote.value || '';
            serverNote.closest('[data-location-note-field]')?.remove();
        }

        // Both panes start collapsed: the field only appears after the user
        // opens one of the two entries.
        searchPane.hidden = true;
        treePane.hidden = true;
        recentBox.hidden = true;
        searchPane.append(recentBox, searchWrap);
        ui.append(head, current, searchPane, treePane, noteBox);
        root.append(ui);

        let matches = [];
        let active = -1;

        const selectedOption = () => byValue.get(native.value) || null;

        function renderCurrent() {
            current.textContent = '';
            current.append(el('span', 'loc-current-label', '当前定位'));
            const option = selectedOption();
            if (option) {
                const crumb = el('span', 'loc-crumb');
                option.path.forEach((segment, index) => {
                    if (index) crumb.append(el('span', 'loc-crumb-sep', '›'));
                    crumb.append(el('span', index === option.path.length - 1 ? 'loc-crumb-leaf' : null, segment));
                });
                current.append(crumb);
                const kind = el('span', 'loc-kind', KIND_LABEL[option.type] || option.type);
                kind.dataset.kind = option.type;
                current.append(kind);
            } else if (native.value === 'legacy' && legacyLabel) {
                current.append(el('span', 'loc-legacy-text', legacyLabel));
            } else {
                current.append(el('span', 'loc-current-empty', '未指定位置'));
            }
            const clear = el('button', 'loc-clear', native.value === '' ? '已为空' : '清空位置');
            clear.type = 'button';
            clear.disabled = native.value === '';
            clear.addEventListener('click', () => {
                setValue('');
                input.value = '';
                closeResults();
            });
            current.append(clear);
        }

        function renderRecent() {
            chips.textContent = '';
            if (recent.length === 0) {
                recentBox.append();
                chips.append(el('p', 'loc-recent-empty', '选过的位置会出现在这里，下次一键选中。'));
                return;
            }
            for (const value of recent) {
                const option = byValue.get(value);
                if (!option) continue;
                const chip = el('button', 'loc-chip');
                chip.type = 'button';
                chip.title = option.label;
                const kind = el('span', 'loc-kind', KIND_LABEL[option.type] || option.type);
                kind.dataset.kind = option.type;
                chip.append(kind, el('span', 'loc-chip-text', option.path.slice(-2).join(' › ')));
                chip.addEventListener('click', () => {
                    setValue(option.value);
                    input.value = option.name;
                    closeResults();
                    setMode(null);
                });
                chips.append(chip);
            }
        }

        function rememberRecent(value) {
            if (!byValue.has(value)) return;
            recent = [value, ...recent.filter(item => item !== value)].slice(0, RECENT_LIMIT);
            recentStore.write(projectId, recent);
            renderRecent();
        }

        function setValue(value) {
            native.value = value;
            native.dispatchEvent(new Event('change', { bubbles: true }));
            renderCurrent();
            for (const node of ui.querySelectorAll('.loc-node.is-selected')) node.classList.remove('is-selected');
            if (value) {
                const node = ui.querySelector('.loc-node[data-value="' + CSS.escape(value) + '"]');
                if (node) node.classList.add('is-selected');
                rememberRecent(value);
            }
        }

        function highlight(container, text, query) {
            const needle = query.trim();
            const index = needle ? text.toLowerCase().indexOf(needle.toLowerCase()) : -1;
            if (index < 0) { container.textContent = text; return; }
            container.textContent = '';
            container.append(document.createTextNode(text.slice(0, index)));
            container.append(el('mark', null, text.slice(index, index + needle.length)));
            container.append(document.createTextNode(text.slice(index + needle.length)));
        }

        // Keep the result list inside the visible dialog: cap its height to the
        // space actually available, and flip above the input when the space
        // below is too small. Without this the list is clipped by the dialog.
        function placeResults() {
            results.style.maxHeight = '';
            results.style.top = '';
            results.style.bottom = '';
            const dialog = root.closest('dialog');
            const bounds = dialog ? dialog.getBoundingClientRect() : null;
            const inputBox = input.getBoundingClientRect();
            const viewportBottom = window.innerHeight || document.documentElement.clientHeight;
            const limitBottom = bounds ? Math.min(bounds.bottom - 8, viewportBottom - 8) : viewportBottom - 8;
            const limitTop = bounds ? Math.max(bounds.top + 8, 8) : 8;
            const below = limitBottom - inputBox.bottom - 6;
            const above = inputBox.top - limitTop - 6;
            if (below < 170 && above > below) {
                results.style.top = 'auto';
                results.style.bottom = 'calc(100% + 5px)';
                results.style.maxHeight = Math.max(120, Math.min(290, above)) + 'px';
            } else {
                results.style.maxHeight = Math.max(120, Math.min(290, below)) + 'px';
            }
        }

        function closeResults() {
            results.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            active = -1;
        }

        function renderResults(query) {
            const needle = norm(query);
            const pool = needle === ''
                ? options
                : options.filter(option => norm(option.label).includes(needle) || norm(option.name).includes(needle));
            matches = pool.slice(0, RESULT_LIMIT);
            results.textContent = '';
            if (matches.length === 0) {
                results.append(el('li', 'loc-results-empty', '没有匹配的空间。可只输入房间名，或用“按楼层浏览”查看。'));
            } else {
                matches.forEach((option, index) => {
                    const item = el('li', 'loc-result');
                    item.setAttribute('role', 'option');
                    item.id = listId + '-' + index;
                    item.dataset.value = option.value;
                    item.setAttribute('aria-selected', option.value === native.value ? 'true' : 'false');
                    if (option.depth > 0) {
                        item.append(el('span', 'loc-indent', '│ '.repeat(option.depth - 1) + '└ '));
                    }
                    const main = el('span', 'loc-result-main');
                    const name = el('span', 'loc-result-name');
                    highlight(name, option.name, query);
                    main.append(name);
                    const ancestors = option.path.slice(0, -1).join(' › ');
                    if (ancestors) main.append(el('span', 'loc-result-path', ancestors));
                    item.append(main);
                    const kind = el('span', 'loc-kind', KIND_LABEL[option.type] || option.type);
                    kind.dataset.kind = option.type;
                    item.append(kind);
                    item.addEventListener('mousedown', event => {
                        event.preventDefault();
                        choose(option);
                    });
                    results.append(item);
                });
                if (pool.length > matches.length) {
                    results.append(el('li', 'loc-results-more', '另有 ' + (pool.length - matches.length) + ' 项，继续输入可缩小范围。'));
                }
            }
            active = -1;
            results.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            placeResults();
        }

        function choose(option) {
            setValue(option.value);
            input.value = option.name;
            closeResults();
            setMode(null);
        }

        function moveActive(delta) {
            if (results.hidden) { renderResults(input.value); return; }
            if (matches.length === 0) return;
            active = (active + delta + matches.length) % matches.length;
            const items = [...results.querySelectorAll('.loc-result')];
            items.forEach((item, index) => item.classList.toggle('is-active', index === active));
            const item = items[active];
            if (item) {
                input.setAttribute('aria-activedescendant', item.id);
                item.scrollIntoView({ block: 'nearest' });
            }
        }

        input.addEventListener('focus', () => renderResults(input.value));
        input.addEventListener('input', () => renderResults(input.value));
        input.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown') { event.preventDefault(); moveActive(1); return; }
            if (event.key === 'ArrowUp') { event.preventDefault(); moveActive(-1); return; }
            if (event.key === 'Escape') { if (!results.hidden) { event.preventDefault(); closeResults(); } return; }
            if (event.key === 'Enter' && !results.hidden) {
                event.preventDefault();
                if (active >= 0 && matches[active]) choose(matches[active]);
                else if (matches.length === 1) choose(matches[0]);
                else if (matches.length > 1) moveActive(1);
            }
        });
        input.addEventListener('blur', () => window.setTimeout(closeResults, 120));

        // --- tree construction from the same catalogue ---
        function buildTree() {
            if (treeBuilt) return;
            treeBuilt = true;
            const tree = el('div', 'loc-tree');
            tree.setAttribute('role', 'tree');
            const rootList = el('ul');
            const buildings = options.filter(option => option.type === 'building');
            const makeNode = (option, children) => {
                const li = el('li');
                const row = el('div', 'loc-node');
                row.dataset.value = option.value;
                row.setAttribute('role', 'treeitem');
                row.tabIndex = 0;
                const hasKids = children.length > 0;
                const tw = el('button', 'loc-tw' + (hasKids ? '' : ' is-leaf'), hasKids ? '▸' : '·');
                tw.type = 'button';
                tw.setAttribute('aria-label', '展开或折叠 ' + option.name);
                if (!hasKids) tw.tabIndex = -1;
                row.append(tw);
                const kind = el('span', 'loc-kind', KIND_LABEL[option.type] || option.type);
                kind.dataset.kind = option.type;
                row.append(kind, el('span', 'loc-node-name', option.name));
                if (option.type === 'area') {
                    const flag = el('span', 'loc-node-flag', '楼栋级');
                    flag.title = '区域在数据中归属楼栋，不属于某一楼层';
                    row.append(flag);
                }
                if (hasKids) row.append(el('span', 'loc-node-count', String(children.length)));
                li.append(row);
                let sub = null;
                if (hasKids) {
                    sub = el('ul');
                    sub.setAttribute('role', 'group');
                    sub.hidden = true;
                    for (const child of children) sub.append(child);
                    li.append(sub);
                }
                const toggle = () => {
                    if (!sub) return;
                    sub.hidden = !sub.hidden;
                    tw.textContent = sub.hidden ? '▸' : '▾';
                    row.setAttribute('aria-expanded', sub.hidden ? 'false' : 'true');
                };
                tw.addEventListener('click', event => { event.stopPropagation(); toggle(); });
                const select = () => {
                    setValue(option.value);
                    input.value = option.name;
                    setMode(null);
                };
                row.addEventListener('click', select);
                row.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); select(); }
                    else if (event.key === 'ArrowRight' && sub && sub.hidden) { event.preventDefault(); toggle(); }
                    else if (event.key === 'ArrowLeft' && sub && !sub.hidden) { event.preventDefault(); toggle(); }
                });
                li._expand = () => { if (sub && sub.hidden) toggle(); };
                li._collapse = () => { if (sub && !sub.hidden) toggle(); };
                li._row = row;
                return li;
            };
            for (const building of buildings) {
                const kids = [];
                for (const area of options.filter(o => o.type === 'area' && o.building_id === building.id)) {
                    kids.push(makeNode(area, []));
                }
                for (const floor of options.filter(o => o.type === 'floor' && o.building_id === building.id)) {
                    const rooms = options
                        .filter(o => o.type === 'room' && o.floor_id === floor.id)
                        .map(room => makeNode(room, []));
                    kids.push(makeNode(floor, rooms));
                }
                rootList.append(makeNode(building, kids));
            }
            tree.append(rootList);
            treePane.append(tree);
            // Open at the floor level: the point of this view is to see what a
            // floor contains, so buildings start expanded and floors collapsed.
            for (const li of rootList.children) {
                if (li._expand) li._expand();
            }

            const actions = el('div', 'loc-tree-actions');
            const expand = el('button', 'button button-secondary', '展开全部');
            expand.type = 'button';
            expand.addEventListener('click', () => {
                for (const li of tree.querySelectorAll('li')) if (li._expand) li._expand();
            });
            const collapse = el('button', 'button button-secondary', '折叠全部');
            collapse.type = 'button';
            collapse.addEventListener('click', () => {
                for (const li of tree.querySelectorAll('li')) if (li._collapse) li._collapse();
            });
            actions.append(expand, collapse);
            treePane.append(actions);
            if (options.some(option => option.type === 'area')) {
                const note = el('p', 'loc-area-note');
                note.append(el('span', null, '⚠'), el('span', null, '区域按楼栋归属，与楼层并列；它不属于某一楼层。'));
                treePane.append(note);
            }
            const selected = selectedOption();
            if (selected) {
                const node = tree.querySelector('.loc-node[data-value="' + CSS.escape(selected.value) + '"]');
                if (node) {
                    node.classList.add('is-selected');
                    let parent = node.closest('li');
                    while (parent) {
                        if (parent._expand) parent._expand();
                        parent = parent.parentElement ? parent.parentElement.closest('li') : null;
                    }
                }
            }
        }

        let openMode = null;
        function setMode(mode) {
            openMode = mode;
            const isSearch = mode === 'search';
            const isTree = mode === 'tree';
            tabSearch.setAttribute('aria-expanded', isSearch ? 'true' : 'false');
            tabTree.setAttribute('aria-expanded', isTree ? 'true' : 'false');
            tabSearch.classList.toggle('is-open', isSearch);
            tabTree.classList.toggle('is-open', isTree);
            searchPane.hidden = !isSearch;
            recentBox.hidden = !isSearch;
            treePane.hidden = !isTree;
            hint.textContent = isTree
                ? '按 楼栋 › 楼层 › 房间 展开，也可直选楼栋或楼层。'
                : (isSearch
                    ? '输入房间名即可命中；同名房间用路径区分。'
                    : '点“搜索”或“按楼层浏览”选择位置；不选则留空。');
            if (isTree) buildTree();
            if (!isSearch) closeResults();
            if (isSearch) input.focus();
        }
        // Clicking the open entry again collapses it, so the field can be hidden.
        tabSearch.addEventListener('click', () => setMode(openMode === 'search' ? null : 'search'));
        tabTree.addEventListener('click', () => setMode(openMode === 'tree' ? null : 'tree'));

        if (legacyValue && native.value === 'legacy') {
            const legacy = el('div', 'loc-legacy');
            legacy.append(el('span', 'loc-current-label', '历史记录'), el('span', 'loc-legacy-text', legacyLabel));
            const keep = el('button', 'button button-secondary', '保留原位置');
            keep.type = 'button';
            keep.addEventListener('click', () => { setValue('legacy'); });
            legacy.append(keep);
            ui.insertBefore(legacy, searchPane);
        }

        openPickers.add({ searchWrap, closeResults });
        native.addEventListener('change', renderCurrent);
        setMode(null);
        renderRecent();
        renderCurrent();
        const initial = selectedOption();
        if (initial) input.value = initial.name;
    }

    function initAll() {
        for (const root of document.querySelectorAll('[data-location-picker]')) initPicker(root);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
    else initAll();
    // The host re-renders panels via fetch; re-scan so new dialogs get enhanced.
    window.projectOInitIssueLocations = initAll;
    document.addEventListener('projecto:content-updated', initAll);
})();
